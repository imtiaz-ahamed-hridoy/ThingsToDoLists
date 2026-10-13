<?php

namespace App\Http\Controllers\Admin\Product;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Log};
use App\Http\Controllers\Controller;
use App\Jobs\BulkDeleteTempProductsJob;
use App\Jobs\BulkImportTempProductsJob;
use App\Jobs\ImportProviderProductIdsChunkJob;
use App\Jobs\QueueBokunActiveActivitiesImportJob;
use App\Jobs\QueueTempProductSearchJob;
use App\Jobs\SyncGlobalTixCatalogJob;
use App\Models\{TempProduct};
use App\Services\Bokun\{TempProductService};
use App\Services\Import\TempProductImportService;
use Yajra\DataTables\Facades\DataTables;
use App\Services\GlobalTix\GlobalTixProductService;
use App\Services\Provider\ProviderFactory;
use App\Services\Provider\ProductSyncFactory;
use App\Services\GlobalTix\GlobalTixPriceSyncService;

class TempProductController extends Controller
{
    protected TempProductService $tempProductService;
    protected GlobalTixProductService $globalTixProductService;
    protected TempProductImportService $tempProductImportService;
    protected GlobalTixPriceSyncService $priceSyncService;

    public function __construct(
        TempProductService $tempProductService,
        GlobalTixProductService $globalTixProductService,
        TempProductImportService $tempProductImportService,
        GlobalTixPriceSyncService $priceSyncService
    )
    {
        $this->tempProductService = $tempProductService;
        $this->globalTixProductService = $globalTixProductService;
        $this->tempProductImportService = $tempProductImportService;
        $this->priceSyncService = $priceSyncService;
    }

    // Route for DataTable AJAX
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $products = TempProduct::orderBy('id', 'desc');

            return DataTables::of($products)
                ->addIndexColumn()
                ->addColumn('checkbox', function ($row) {
                    return '<input type="checkbox" class="row-checkbox" value="' . $row->id . '">';
                })
                ->editColumn('title', function ($row) {
                    $fullTitle = (string) ($row->title ?? '');
                    return '<span title="' . e($fullTitle) . '">' . e(\Illuminate\Support\Str::limit($fullTitle, 50, '...')) . '</span>';
                })
                ->addColumn('thumbnail', function ($row) {
                    if (empty($row->thumbnail)) {
                        return 'N/A';
                    }
                    return '<img src="' . $row->thumbnail . '" width="50" height="50" style="object-fit:cover;border-radius:6px;" alt="' . e($row->title) . '">';
                })
                ->addColumn('provider', function ($row) {
                    return ucfirst(strtolower((string) ($row->api_platform ?? 'bokun')));
                })
                ->addColumn('price', function ($row) {
                    $platform = strtolower((string) ($row->api_platform ?? 'globaltix'));
                    if ($platform === 'globaltix') {
                        $syncResult = $this->priceSyncService->syncTempProductPrice($row);
                        $priceVal = (float) ($syncResult['price'] ?? $row->price ?? 0);
                        if ($priceVal > 0) {
                            return '<strong class="text-success">' . number_format($priceVal, 2) . ' EUR</strong>';
                        }
                    } else {
                        $currency = $row->raw_data['currency'] ?? $row->raw_data['currencyCode'] ?? 'EUR';
                        $country = $row->raw_data['country'] ?? $row->raw_data['countryCode'] ?? null;
                        $city = $row->city ?? $row->raw_data['city'] ?? null;
                        $priceVal = (float) ($row->price ?? 0);
                        $cs = app(\App\Services\CurrencyService::class);

                        if ($priceVal > 0) {
                            $converted = $cs->convertToEur($priceVal, $currency, $country, $city);
                            if ($converted !== null && $converted > 0 && abs($converted - $priceVal) > 0.01) {
                                $row->update(['price' => $converted]);
                                $priceVal = $converted;
                            }
                            return '<strong class="text-success">' . number_format($priceVal, 2) . ' EUR</strong>';
                        }
                    }

                    return '<span class="text-muted">0.00 EUR</span>';
                })
                ->addColumn('import_result', function ($row) {
                    $status = strtolower((string) ($row->last_import_status ?? ''));
                    $reason = trim((string) ($row->last_import_reason ?? ''));
                    $attemptedAt = $row->last_import_attempted_at?->format('Y-m-d H:i');

                    if ($status === '') {
                        return '<span class="badge bg-secondary">Pending review</span>';
                    }

                    $badgeClass = match ($status) {
                        'skipped' => 'bg-warning text-dark',
                        'failed' => 'bg-danger',
                        default => 'bg-secondary',
                    };

                    $html = '<div class="d-flex flex-column gap-1">';
                    $html .= '<span class="badge ' . $badgeClass . '">' . e(ucfirst($status)) . '</span>';

                    if ($reason !== '') {
                        $html .= '<small class="text-muted">' . e($reason) . '</small>';
                    }

                    if ($attemptedAt !== null) {
                        $html .= '<small class="text-muted">Tried: ' . e($attemptedAt) . '</small>';
                    }

                    $html .= '</div>';

                    return $html;
                })
                ->addColumn('action', function ($row) {
                    $platform = strtolower((string) ($row->api_platform ?? 'globaltix'));
                    $isGt = ($platform === 'globaltix');
                    $btnTitle = $isGt ? 'Live GlobalTix API (Raw Activity Data, Timeslots & Prices)' : 'Live Raw Activity Data';

                    return '
                <div class="btn-group">
                    <button onclick="viewProduct(' . $row->id . ', event)" class="btn btn-sm btn-primary" title="View Details">
                        <i class="fe fe-eye"></i>
                    </button>
                    <button onclick="viewLiveGlobalTixRaw(' . $row->id . ', \'' . e($row->product_api_id) . '\', \'' . e($platform) . '\', event)" class="btn btn-sm btn-info text-white" title="' . $btnTitle . '">
                        <i class="fe fe-activity"></i>
                    </button>
                    <button onclick="showDeleteConfirm(' . $row->id . ')" class="btn btn-sm btn-danger" title="Delete">
                        <i class="fe fe-trash"></i>
                    </button>
                </div>';
                })
                ->rawColumns(['checkbox', 'title', 'thumbnail', 'price', 'import_result', 'action'])
                ->make(true);
        }

        return view('backend.layouts.tempProduct.index');
    }

    /**
     * Fetch 100% live GlobalTix raw API data, options, ticket types, timeslots, and prices.
     */
    public function getLiveRawActivityData($id, Request $request)
    {
        try {
            $product = TempProduct::findOrFail($id);
            $platform = strtolower((string) ($product->api_platform ?? 'globaltix'));
            $apiId = (string) $product->product_api_id;
            $currencyService = app(\App\Services\CurrencyService::class);

            if ($platform === 'globaltix') {
                $currency = $request->query('currency', 'EUR');
                $lang = $request->query('lang', 'en');

                // 1. Fetch live product info
                $infoRes = $this->globalTixProductService->getProductInfo($apiId, $lang, $currency);
                $infoData = $infoRes['data'] ?? $infoRes;

                // 2. Fetch live product options
                $optionsRes = $this->globalTixProductService->getProductOptions($apiId, true, $lang, $currency);
                $optionsData = $optionsRes['data'] ?? [];
                if (empty($optionsData)) {
                    $optionsRes = $this->globalTixProductService->getProductOptions($apiId, false, $lang, $currency);
                    $optionsData = $optionsRes['data'] ?? [];
                }

                // 3. Extract ticket types and fetch real availability slots
                $dateFrom = now()->toDateString();
                $dateTo   = now()->addDays(14)->toDateString();

                $ticketTypes = [];
                $availabilities = [];

                if (is_array($optionsData)) {
                    $optionsList = array_is_list($optionsData) ? $optionsData : [$optionsData];
                    foreach ($optionsList as $opt) {
                        $optId = $opt['id'] ?? null;
                        $optName = $opt['name'] ?? 'Option';
                        $optTickets = $opt['ticketType'] ?? [];

                        if (is_array($optTickets)) {
                            foreach ($optTickets as $t) {
                                if (!empty($t['id'])) {
                                    $ticketTypes[] = [
                                        'option_id' => $optId,
                                        'option_name' => $optName,
                                        'ticket_id' => $t['id'],
                                        'ticket_name' => $t['name'] ?? 'Ticket',
                                        'selling_price' => $t['recommendedSellingPrice'] ?? $t['sellingPrice'] ?? null,
                                        'nett_price' => $t['nettPrice'] ?? null,
                                        'original_price' => $t['originalPrice'] ?? null,
                                        'currency' => $t['currency'] ?? $currency,
                                        'converted_eur' => $currencyService->convertToEur(
                                            (float) ($t['recommendedSellingPrice'] ?? $t['sellingPrice'] ?? 0),
                                            $t['currency'] ?? 'EUR',
                                            $product->city,
                                            $product->city
                                        ),
                                    ];
                                }
                            }
                        }
                    }
                }

                // If ticket types exist, fetch live availability for the first ticket type
                if (!empty($ticketTypes)) {
                    $firstTicketId = $ticketTypes[0]['ticket_id'];
                    $availRes = $this->globalTixProductService->getTicketAvailability($firstTicketId, $dateFrom, $dateTo);
                    if ($availRes['status'] ?? false) {
                        $availabilities = $availRes['data'] ?? [];
                    }
                }

                // Resolve lowest Adult price from ticket types
                $resolvedLivePrices = \App\Services\Sync\PriceCalculator::resolveProductDisplayPrices($ticketTypes, $product->price);
                $liveAdultPrice = $resolvedLivePrices['from_price'] ?? $product->price;

                if ($liveAdultPrice !== null && $liveAdultPrice > 0 && abs((float)$product->price - (float)$liveAdultPrice) > 0.01) {
                    $product->update(['price' => $liveAdultPrice]);
                }

                return response()->json([
                    'success' => true,
                    'platform' => 'globaltix',
                    'product' => [
                        'id' => $product->id,
                        'product_api_id' => $product->product_api_id,
                        'title' => $product->title,
                        'city' => $product->city,
                        'stored_price' => $liveAdultPrice ?? $product->price,
                    ],
                    'product_info' => $infoData,
                    'product_options' => $optionsData,
                    'ticket_types' => $ticketTypes,
                    'availability' => $availabilities,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'raw_response' => [
                        'info' => $infoRes,
                        'options' => $optionsRes,
                    ],
                ]);
            } else {
                // Bokun
                $fulldetails = $this->tempProductService->fetchActivityDetails('bokun', $apiId);
                return response()->json([
                    'success' => true,
                    'platform' => 'bokun',
                    'product' => [
                        'id' => $product->id,
                        'product_api_id' => $product->product_api_id,
                        'title' => $product->title,
                        'city' => $product->city,
                        'stored_price' => $product->price,
                    ],
                    'product_info' => $fulldetails['data'] ?? $fulldetails,
                    'product_options' => $fulldetails['data']['rates'] ?? [],
                    'ticket_types' => [],
                    'availability' => [],
                    'raw_response' => $fulldetails,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error fetching live raw activity data: ' . $e->getMessage(), [
                'id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch live data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch live availability timeslots for a specific ticket type.
     */
    public function getLiveTicketAvailability(Request $request)
    {
        $request->validate([
            'ticket_id' => 'required',
            'date_from' => 'required|date_format:Y-m-d',
            'date_to'   => 'required|date_format:Y-m-d',
        ]);

        try {
            $ticketId = $request->input('ticket_id');
            $dateFrom = $request->input('date_from');
            $dateTo   = $request->input('date_to');
            $currency = $request->input('currency', 'EUR');

            $res = $this->globalTixProductService->getTicketAvailability($ticketId, $dateFrom, $dateTo, $currency);

            return response()->json($res);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // Route for AJAX modal view
    public function show($id)
    {
        try {
            $product = TempProduct::findOrFail($id);
            $platform = $product->api_platform ?? 'bokun';

            $fulldetails = $this->tempProductService->fetchActivityDetails(
                strtolower($platform),
                $product->product_api_id
            );

            return response()->json([
                'product' => $product,
                'fulldetails' => $fulldetails,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching product details: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch product details'
            ], 500);
        }
    }


    public function search(\App\Http\Requests\Admin\Product\ActivitySearchRequest $request)
    {
        try {
            $validated = $request->validated();

            if (($validated['api_platform'] ?? 'bokun') === 'globaltix' && empty($validated['product_api_id']) && empty($validated['countryCode'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => [
                        'countryCode' => ['Country code is required for GlobalTix search.'],
                    ],
                ], 422);
            }

            QueueTempProductSearchJob::dispatch($validated);

            return response()->json([
                'success' => true,
                'queued' => true,
                'message' => !empty($validated['product_api_id'])
                    ? 'Product import has been queued. You can leave this page while it runs.'
                    : 'Provider search has been queued. Results will appear in the review table as the job runs.',
                'data'    => [
                    'provider' => strtolower((string) ($validated['api_platform'] ?? 'bokun')),
                    'product_api_id' => $validated['product_api_id'] ?? null,
                ]
            ], 202);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $e->errors()
            ], 422);
        } catch (\Throwable $e) {

            Log::error('Product search failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error. Please try again later.'
            ], 500);
        }
    }


    public function bulkImport(Request $request)
    {
        try {
            $rawIds = $request->json('ids') ?? $request->input('ids') ?? $request->ids;
            $rawIds = is_array($rawIds) ? $rawIds : (is_string($rawIds) ? explode(',', $rawIds) : []);

            $ids = collect($rawIds)
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values();

            if ($ids->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No products selected for import.',
                ], 422);
            }

            $chunks = $ids->chunk(20);

            foreach ($chunks as $chunk) {
                BulkImportTempProductsJob::dispatch($chunk->values()->all());
            }

            return response()->json([
                'success' => true,
                'queued' => true,
                'message' => "Bulk import queued in {$chunks->count()} background chunk(s). You can continue using the admin panel.",
                'chunks' => $chunks->count(),
                'total' => $ids->count(),
            ], 202);
        } catch (\Exception $e) {
            Log::error('Bulk import error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to import products. Please try again.'
            ], 500);
        }
    }

    public function bulkDelete(Request $request)
    {
        try {
            $rawIds = $request->json('ids') ?? $request->input('ids') ?? $request->ids;
            $rawIds = is_array($rawIds) ? $rawIds : (is_string($rawIds) ? explode(',', $rawIds) : []);

            $ids = collect($rawIds)
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values();

            if ($ids->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No products selected for deletion.',
                ], 422);
            }

            // Immediately delete matching TempProducts so UI updates immediately
            $deletedCount = TempProduct::whereIn('id', $ids)->delete();

            // Also dispatch job for queue testing / async listeners
            $chunks = $ids->chunk(100);
            foreach ($chunks as $chunk) {
                BulkDeleteTempProductsJob::dispatch($chunk->values()->all());
            }

            return response()->json([
                'success' => true,
                'queued' => true,
                'message' => "{$deletedCount} product(s) deleted successfully.",
                'count' => $deletedCount,
                'chunks' => $chunks->count(),
                'total' => $ids->count(),
            ], 202);
        } catch (\Exception $e) {
            Log::error('Bulk delete error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete products. Please try again.'
            ], 500);
        }
    }

    public function importByApiId(Request $request)
    {
        try {
            $validated = $request->validate([
                'api_platform' => 'required|string|in:bokun,globaltix',
                'product_api_id' => 'required|string|max:100',
                'force_update' => 'nullable|boolean',
            ]);

            $platform = strtolower($validated['api_platform']);

            ImportProviderProductIdsChunkJob::dispatch(
                $platform,
                [(string) $validated['product_api_id']],
                (bool) ($validated['force_update'] ?? false)
            );

            return response()->json([
                'success' => true,
                'queued' => true,
                'message' => 'Product import has been queued. You can leave this page while it runs.',
                'data' => [
                    'provider' => $platform,
                    'product_api_id' => (string) $validated['product_api_id'],
                ],
            ], 202);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Direct import by API ID failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to import product by API ID.',
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $product = TempProduct::findOrFail($id);
            $product->delete();

            return response()->json([
                'success' => true,
                't-success' => true,
                'message' => 'Product deleted successfully!'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Delete error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product. Please try again.'
            ], 500);
        }
    }
    public function getGlobalTixCategories()
    {
        try {
            $response = $this->globalTixProductService->getCategories();

            // Handle multiple possible response formats from GlobalTix API
            $categories = [];
            if (isset($response['data']) && is_array($response['data'])) {
                if (array_is_list($response['data'])) {
                    $categories = $response['data'];
                } elseif (isset($response['data']['categories'])) {
                    $categories = $response['data']['categories'];
                } elseif (isset($response['data']['list'])) {
                    $categories = $response['data']['list'];
                }
            } elseif (array_is_list($response) && !empty($response)) {
                $categories = $response;
            } elseif (isset($response['categories'])) {
                $categories = $response['categories'];
            }

            if (empty($categories)) {
                Log::warning('GlobalTix categories empty/unrecognized format', [
                    'keys' => is_array($response) ? array_keys($response) : gettype($response),
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $categories,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch GlobalTix categories', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch categories: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getGlobalTixCountries()
    {
        try {
            $response = $this->globalTixProductService->getCountries();

            return response()->json([
                'success' => true,
                'data' => $response['data'] ?? []
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch GlobalTix countries', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch countries: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getGlobalTixCities(Request $request)
    {
        try {
            $countryCode = $request->get('countryCode');

            if (!$countryCode) {
                return response()->json(['success' => false, 'message' => 'Country code required'], 400);
            }

            // We fetch countries because the cities are nested within them in the API response
            // and the independent city endpoint returns all cities globally without filtering.
            $countriesResponse = $this->globalTixProductService->getCountries();
            $countries = $countriesResponse['data'] ?? [];

            $cities = [];
            foreach ($countries as $country) {
                if (($country['code'] ?? '') === $countryCode) {
                    $cities = $country['cities'] ?? [];
                    break;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $cities
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch GlobalTix cities', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch cities: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getGlobalTixNewProductIds(Request $request)
    {
        try {
            $validated = $request->validate([
                'startDate' => 'required|date',
                'endDate' => 'required|date|after_or_equal:startDate',
            ]);

            $response = $this->globalTixProductService->getNewProducts(
                $validated['startDate'],
                $validated['endDate']
            );

            if (($response['success'] ?? true) === false) {
                return response()->json([
                    'success' => false,
                    'message' => $response['message'] ?? 'GlobalTix did not return valid products for the selected date range.',
                ], 422);
            }

            $productIds = $this->globalTixProductService->extractProductIdsFromResponse($response);
            $totalFromApi = $response['size'] ?? $response['total'] ?? count($productIds);

            return response()->json([
                'success' => true,
                'message' => count($productIds) > 0
                    ? "Found " . count($productIds) . " GlobalTix product ID(s) to queue for review."
                    : 'No new GlobalTix products found for the selected date range.',
                'productIds' => $productIds,
                'total' => $totalFromApi,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('GlobalTix product IDs fetch error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch GlobalTix product IDs.',
            ], 500);
        }
    }

    /**
     * Get detailed activity data (proxy to full details fetch)
     */
    public function getActivity($id)
    {
        return $this->show($id);
    }

    /**
     * Get activity availability for the next 30 days
     */
    public function getAvailability($id)
    {
        try {
            $product = TempProduct::findOrFail($id);
            $platform = strtolower($product->api_platform ?? 'bokun');

            $provider = ProviderFactory::make($platform);
            $details = $provider->getFullProductData($product->product_api_id);

            return response()->json([
                'success' => true,
                'availabilities' => $details['data']['priceList']['pricesByDateRange'][0]['rates'] ?? [],
                'raw_availabilities' => $details['data']['availabilities'] ?? []
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching availability: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch availability details'
            ], 500);
        }
    }

    /**
     * Get all temporary products (proxy for list view if needed)
     */
    public function getAllProduct(Request $request)
    {
        return $this->index($request);
    }

    public function syncGlobalTixCatalog(Request $request)
    {
        try {
            $validated = $request->validate([
                'startDate' => 'required|date',
                'endDate' => 'required|date|after_or_equal:startDate',
            ]);

            SyncGlobalTixCatalogJob::dispatch($validated['startDate'], $validated['endDate']);

            return response()->json([
                'success' => true,
                'queued' => true,
                'message' => 'GlobalTix catalog sync has been queued. New products will appear as background chunks finish.',
                'data' => [
                    'startDate' => $validated['startDate'],
                    'endDate' => $validated['endDate'],
                ],
            ], 202);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('GlobalTix new products sync failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'GlobalTix sync failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getActiveBokunIds(\App\Services\Bokun\BokunService $bokunService)
    {
        try {
            $response = $bokunService->getActiveActivityIds();
            $activityIds = [];
            
            if (!empty($response['suppliers'])) {
                foreach ($response['suppliers'] as $supplier) {
                    if (!empty($supplier['activityIds'])) {
                        $activityIds = array_merge($activityIds, $supplier['activityIds']);
                    }
                }
            }

            $activityIds = array_values(array_unique(array_map(fn ($id) => (string) $id, $activityIds)));

            return response()->json([
                'success' => true,
                'activityIds' => $activityIds,
            ]);
        } catch (\Exception $e) {
            Log::error('Bokun Active IDs error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch active IDs from Bokun',
            ], 500);
        }
    }

    public function queueActiveBokunActivities()
    {
        QueueBokunActiveActivitiesImportJob::dispatch();

        return response()->json([
            'success' => true,
            'queued' => true,
            'message' => 'Bokun active activities import has been queued. You can leave this page while it runs.',
        ], 202);
    }

    public function processBokunIdChunk(Request $request)
    {
        try {
            $validated = $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'required',
            ]);

            $summary = $this->processProviderIdChunk('bokun', $validated['ids']);

            return response()->json([
                'success' => true,
                'imported' => (int) ($summary['imported'] ?? 0),
                'updated' => (int) ($summary['updated'] ?? 0),
                'skipped' => (int) ($summary['skipped'] ?? 0),
                'failed' => (int) ($summary['failed'] ?? 0),
                'errors' => $summary['errors'] ?? [],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Bokun chunk import error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to import Bokun activity chunk.',
            ], 500);
        }
    }

    public function processGlobalTixIdChunk(Request $request)
    {
        try {
            $validated = $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'required',
            ]);

            $summary = $this->syncTempProductsFromGlobalTixIds($validated['ids']);

            return response()->json([
                'success' => true,
                'created' => (int) ($summary['created'] ?? 0),
                'updated' => (int) ($summary['updated'] ?? 0),
                'imported' => (int) ($summary['created'] ?? 0),
                'skipped' => (int) ($summary['skipped'] ?? 0),
                'failed' => (int) ($summary['failed'] ?? 0),
                'errors' => $summary['errors'] ?? [],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('GlobalTix chunk sync error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync GlobalTix activity chunk: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync a list of GlobalTix product API IDs into the temporary products table.
     */
    public function syncTempProductsFromGlobalTixIds(array $rawIds): array
    {
        set_time_limit(0);

        $productApiIds = array_values(array_unique(array_filter(array_map(
            fn ($id) => trim((string) $id),
            $rawIds
        ))));

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        $existingProductIds = \App\Models\Product::query()
            ->whereIn('product_api_id', $productApiIds)
            ->where(function ($q) {
                $q->where('platform', 'globaltix')
                  ->orWhere('provider', 'globaltix');
            })
            ->pluck('product_api_id')
            ->map(fn($id) => (string)$id)
            ->toArray();

        foreach ($productApiIds as $productApiId) {
            if ($productApiId === '' || in_array($productApiId, $existingProductIds, true)) {
                $skipped++;
                continue;
            }

            try {
                $infoResponse = $this->globalTixProductService->getProductInfo($productApiId);
                $fullInfo = is_array($infoResponse['data'] ?? null) ? $infoResponse['data'] : [];

                if ($fullInfo === []) {
                    $failed++;
                    $errors[] = "Product {$productApiId}: Empty data returned from GlobalTix.";
                    continue;
                }

                $thumbnail = null;
                if (!empty($fullInfo['image'])) {
                    $thumbnail = $this->globalTixProductService->getImage($fullInfo['image']);
                } elseif (!empty($fullInfo['media'][0]['name'])) {
                    $thumbnail = $fullInfo['media'][0]['name'];
                }

                $city = $fullInfo['city'] ?? $fullInfo['cityName'] ?? $fullInfo['destination'] ?? 'N/A';
                $country = $fullInfo['country'] ?? $fullInfo['countryCode'] ?? null;
                $currency = $fullInfo['currency'] ?? $fullInfo['currencyCode'] ?? 'EUR';

                $resolvedPrice = 0;
                if (!empty($fullInfo['ticketType']) || !empty($fullInfo['tickets']) || !empty($fullInfo['activityOptions'])) {
                    $tickets = $fullInfo['ticketType'] ?? $fullInfo['tickets'] ?? [];
                    if (empty($tickets) && !empty($fullInfo['activityOptions'])) {
                        foreach ($fullInfo['activityOptions'] as $opt) {
                            if (!empty($opt['ticketType'])) {
                                $tickets = array_merge($tickets, $opt['ticketType']);
                            }
                        }
                    }
                    if (!empty($tickets)) {
                        $resolved = \App\Services\Sync\PriceCalculator::resolveProductDisplayPrices($tickets, null, null, $country, $city);
                        $resolvedPrice = (float) ($resolved['from_price'] ?? 0);
                    }
                }
                if ($resolvedPrice <= 0) {
                    $rawPrice = (float) ($fullInfo['fromPrice'] ?? $fullInfo['originalPrice'] ?? 0);
                    if ($rawPrice > 0) {
                        $cs = app(\App\Services\CurrencyService::class);
                        $converted = $cs->convertToEur($rawPrice, $currency, $country, $city);
                        $resolvedPrice = ($converted !== null && $converted > 0) ? $converted : $rawPrice;
                    }
                }

                $tempProduct = TempProduct::updateOrCreate(
                    [
                        'product_api_id' => $productApiId,
                        'api_platform' => 'globaltix',
                    ],
                    [
                        'title' => $fullInfo['name'] ?? $fullInfo['title'] ?? 'Untitled',
                        'description' => $fullInfo['description'] ?? null,
                        'price' => $resolvedPrice,
                        'city' => $city,
                        'thumbnail' => $thumbnail,
                        'status' => 'pending',
                        'raw_data' => $fullInfo,
                        'last_import_status' => null,
                        'last_import_reason' => null,
                        'last_import_attempted_at' => null,
                    ]
                );

                if ($tempProduct->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "Product {$productApiId}: " . $e->getMessage();
                Log::error('GlobalTix temp sync product info failed', [
                    'product_api_id' => $productApiId,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    protected function processProviderIdChunk(string $provider, array $rawIds): array
    {
        set_time_limit(0);

        $ids = array_values(array_unique(array_map(fn ($id) => (string) $id, $rawIds)));

        $imported = 0;
        $updated = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];
        $importedIds = [];
        $updatedIds = [];
        $skippedIds = [];
        $skippedReasons = [];
        $failedIds = [];
        $succeededIds = [];

        $syncService = ProductSyncFactory::make($provider);

        foreach ($ids as $id) {
            try {
                $result = $syncService->importOrUpdateByApiId($provider, $id);

                if (!($result['success'] ?? false)) {
                    $failed++;
                    $failedIds[] = $id;
                    $errors[] = strtoupper($provider) . " activity {$id} failed: " . ($result['message'] ?? 'Unknown error');
                    continue;
                }

                if (($result['status'] ?? '') === 'created') {
                    $imported++;
                    $importedIds[] = $id;
                    $succeededIds[] = $id;
                } elseif (($result['status'] ?? '') === 'updated') {
                    $updated++;
                    $updatedIds[] = $id;
                    $succeededIds[] = $id;
                } else {
                    $skipped++;
                    $skippedIds[] = $id;
                    $succeededIds[] = $id;
                    $skippedReasons[$id] = $result['message'] ?? 'Skipped';
                }
            } catch (\Throwable $e) {
                Log::error(ucfirst($provider) . " activity import failed for {$id}", [
                    'error' => $e->getMessage(),
                ]);

                $failed++;
                $failedIds[] = $id;
                $errors[] = strtoupper($provider) . " activity {$id} failed: {$e->getMessage()}";
            }
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'errors' => $errors,
            'imported_ids' => $importedIds,
            'updated_ids' => $updatedIds,
            'skipped_ids' => $skippedIds,
            'skipped_reasons' => $skippedReasons,
            'failed_ids' => $failedIds,
            'succeeded_ids' => $succeededIds,
        ];
    }
}
