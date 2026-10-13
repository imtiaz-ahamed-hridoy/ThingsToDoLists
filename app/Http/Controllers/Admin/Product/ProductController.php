<?php

namespace App\Http\Controllers\Admin\Product;

use App\Services\Bokun\TempProductService;
use App\Services\CurrencyService;
use App\Services\GlobalTix\GlobalTixProductService;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\{Keyword, Product};
use App\Repositories\Contracts\CategoriesRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\VendorRepositoryInterface;
use App\Services\ProductService;
use App\Traits\ChunkFileUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class ProductController extends Controller
{
    use ChunkFileUpload;

    public function __construct(
        private ProductService $productService,
        private ProductRepositoryInterface $productRepository,
        private VendorRepositoryInterface $vendorRepository,
        private CategoriesRepositoryInterface $categoriesRepository
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $products = $this->productRepository->getDataTableQuery();

            return DataTables::eloquent($products)
                ->addColumn('checkbox', function ($row) {
                    return '<input type="checkbox" class="row-checkbox" value="' . $row->id . '">';
                })
                ->addIndexColumn()
                ->editColumn('title', function ($row) {
                    $fullTitle = (string) ($row->title ?? '');
                    return '<span title="' . e($fullTitle) . '">' . e(\Illuminate\Support\Str::limit($fullTitle, 50, '...')) . '</span>';
                })
                ->addColumn('city', function ($row) {
                    $city = $row->location?->city;
                    if (empty($city) || strtolower(trim($city)) === 'unknown city' || strtolower(trim($city)) === 'n/a') {
                        return '<span class="text-muted">N/A</span>';
                    }
                    return e($city);
                })
                ->addColumn('thumbnail', function ($row) {
                    $thumb = $row->thumbnail;
                    if (!empty($thumb)) {
                        $url = filter_var($thumb, FILTER_VALIDATE_URL) ? $thumb : $this->resolveMediaUrl($thumb);
                    } else {
                        $url = asset('frontend/default-avatar-profile.jpg');
                    }
                    return '<img src="' . $url . '" width="50" height="50" style="object-fit:cover;border-radius:6px;" alt="' . e($row->title) . '">';
                })
                ->addColumn('provider', function ($row) {
                    return ucfirst($row->provider ?? $row->platform ?? 'Bokun');
                })
                ->addColumn('is_top', function ($row) {
                    $checked = $row->is_top ? 'checked' : '';
                    return '
                    <label class="custom-switch form-switch mb-0">
                        <input type="checkbox" name="custom-switch-radio" class="custom-switch-input toggle-top" data-id="' . $row->id . '" ' . $checked . '>
                        <span class="custom-switch-indicator"></span>
                    </label>';
                })
                ->addColumn('price', function ($row) {
                    $curr = $row->default_currency ?: 'EUR';
                    $fromVal = (float) ($row->getRawOriginal('from_price') ?? $row->from_price ?? 0);
                    $origVal = (float) ($row->getRawOriginal('original_price') ?? $row->original_price ?? 0);

                    $country = $row->location?->country ?? $row->location?->countryCode;
                    $city = $row->location?->city;

                    $cs = app(\App\Services\CurrencyService::class);
                    if ($fromVal > 0) {
                        $convertedFrom = $cs->convertToEur($fromVal, $curr, $country, $city);
                        if ($convertedFrom !== null && $convertedFrom > 0 && abs($convertedFrom - $fromVal) > 0.01) {
                            $row->from_price = $convertedFrom;
                            $fromVal = $convertedFrom;
                        }
                    }

                    if ($origVal > 0) {
                        $convertedOrig = $cs->convertToEur($origVal, $curr, $country, $city);
                        if ($convertedOrig !== null && $convertedOrig > 0 && abs($convertedOrig - $origVal) > 0.01) {
                            $row->original_price = $convertedOrig;
                            $origVal = $convertedOrig;
                        }
                    }

                    $from = ($fromVal > 0) ? 'EUR ' . number_format($fromVal, 2) : '<span class="text-muted">N/A</span>';
                    $orig = ($origVal > 0) ? 'EUR ' . number_format($origVal, 2) : '<span class="text-muted">N/A</span>';
                    return '<div style="font-size: 13px; line-height: 1.4;">
                        <div><strong class="text-success">From:</strong> ' . $from . '</div>
                        <div><strong class="text-muted">Orig:</strong> ' . $orig . '</div>
                    </div>';
                })
                ->addColumn('action', function ($row) {
                    $platform = strtolower((string) ($row->provider ?? $row->platform ?? 'globaltix'));
                    $isGt = ($platform === 'globaltix');
                    $btnTitle = $isGt ? 'Live GlobalTix API (Raw Activity Data, Timeslots & Prices)' : 'Live Raw Activity Data';

                    return '
                    <div class="btn-group btn-group-sm" role="group">
                        <a href="' . route('product.show', $row) . '" class="btn btn-info fs-14 text-white" title="View Details">
                            <i class="fe fe-eye"></i>
                        </a>
                        <button type="button" onclick="viewLiveProductRaw(' . $row->id . ', \'' . e($row->product_api_id) . '\', \'' . e($platform) . '\', event)" class="btn btn-teal text-white fs-14" style="background-color: #008080;" title="' . $btnTitle . '">
                            <i class="fe fe-activity"></i>
                        </button>
                        <a href="' . route('product.edit', $row) . '" class="btn btn-primary fs-14 text-white" title="Edit">
                            <i class="fe fe-edit"></i>
                        </a>
                        <button type="button" onclick="showDeleteConfirm(' . $row->id . ')" class="btn btn-danger fs-14 text-white" title="Delete">
                            <i class="fe fe-trash"></i>
                        </button>
                    </div>';
                })
                ->rawColumns(['checkbox', 'title', 'city', 'thumbnail', 'provider', 'price', 'is_top', 'action'])
                ->make(true);
        }

        return view('backend.layouts.product.index');
    }

    /**
     * Fetch 100% live GlobalTix / Bokun raw activity data, options, timeslots and prices for Product.
     */
    public function getLiveRawActivityData($id, Request $request)
    {
        try {
            $product = Product::findOrFail($id);
            $platform = strtolower((string) ($product->provider ?? $product->platform ?? 'globaltix'));
            $apiId = (string) $product->product_api_id;
            $currencyService = app(CurrencyService::class);
            $globalTixProductService = app(GlobalTixProductService::class);
            $tempProductService = app(TempProductService::class);

            if ($platform === 'globaltix') {
                $currency = $request->query('currency', 'EUR');
                $lang = $request->query('lang', 'en');

                // 1. Fetch live product info
                $infoRes = $globalTixProductService->getProductInfo($apiId, $lang, $currency);
                $infoData = $infoRes['data'] ?? $infoRes;

                // 2. Fetch live product options
                $optionsRes = $globalTixProductService->getProductOptions($apiId, true, $lang, $currency);
                $optionsData = $optionsRes['data'] ?? [];
                if (empty($optionsData)) {
                    $optionsRes = $globalTixProductService->getProductOptions($apiId, false, $lang, $currency);
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
                                            $product->location?->country ?? $product->location?->city,
                                            $product->location?->city
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
                    $availRes = $globalTixProductService->getTicketAvailability($firstTicketId, $dateFrom, $dateTo);
                    if ($availRes['status'] ?? false) {
                        $availabilities = $availRes['data'] ?? [];
                    }
                }

                // Resolve lowest Adult price from ticket types
                $resolvedLivePrices = \App\Services\Sync\PriceCalculator::resolveProductDisplayPrices($ticketTypes, $product->from_price, $product->original_price);
                $liveAdultFromPrice = $resolvedLivePrices['from_price'] ?? $product->from_price;
                $liveAdultOrigPrice = $resolvedLivePrices['original_price'] ?? $product->original_price;

                if ($liveAdultFromPrice !== null && $liveAdultFromPrice > 0 && abs((float)$product->from_price - (float)$liveAdultFromPrice) > 0.01) {
                    $product->update([
                        'from_price'     => $liveAdultFromPrice,
                        'original_price' => $liveAdultOrigPrice,
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'platform' => 'globaltix',
                    'product' => [
                        'id' => $product->id,
                        'product_api_id' => $product->product_api_id,
                        'title' => $product->title,
                        'city' => $product->location?->city,
                        'stored_price' => $liveAdultFromPrice ?? $product->from_price,
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
                $fulldetails = $tempProductService->fetchActivityDetails('bokun', $apiId);
                return response()->json([
                    'success' => true,
                    'platform' => 'bokun',
                    'product' => [
                        'id' => $product->id,
                        'product_api_id' => $product->product_api_id,
                        'title' => $product->title,
                        'city' => $product->location?->city,
                        'stored_price' => $product->from_price,
                    ],
                    'product_info' => $fulldetails['data'] ?? $fulldetails,
                    'product_options' => $fulldetails['data']['rates'] ?? [],
                    'ticket_types' => [],
                    'availability' => [],
                    'raw_response' => $fulldetails,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error fetching product live raw activity data: ' . $e->getMessage(), [
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
            $globalTixProductService = app(GlobalTixProductService::class);

            $res = $globalTixProductService->getTicketAvailability($ticketId, $dateFrom, $dateTo, $currency);

            return response()->json($res);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $vendors = $this->vendorRepository->getForDropdown();
        $categories = $this->categoriesRepository->getForDropdown();
        $allKeywords = Keyword::all();

        return view('backend.layouts.product.create', compact('vendors', 'categories', 'allKeywords'));
    }

    /**
     * Handle AJAX chunked file upload for product videos.
     */
    public function uploadAjaxChunk(Request $request)
    {
        $request->validate([
            'chunk' => 'required|file',
            'index' => 'required|integer|min:1',
            'total_chunks' => 'required|integer|min:1',
            'file_name' => 'required|string',
            'temp_id' => 'required|string',
        ]);

        try {
            $result = $this->handleChunkedUploadPublic(
                $request->file('chunk'),
                [
                    'chunk'        => $request->file('chunk'),
                    'index'        => $request->index,
                    'total_chunks' => $request->total_chunks,
                    'temp_id'      => $request->temp_id,
                    'file_name'    => $request->file_name,
                ],
                'products/videos'
            );

            return response()->json([
                'success' => true,
                'is_completed' => $result['is_completed'] ?? false,
                'file_path' => $result['file_path'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Chunk upload failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Complete video upload (placeholder for legacy/standardized flow).
     */
    public function completeVideoUpload(Request $request)
    {
        return response()->json(['success' => true]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request)
    {
        try {
            $this->productService->create(
                $request->validated(),
                [
                    'thumbnail'      => $request->file('thumbnail'),
                    'product_images' => $request->file('images', []),
                ],
                $request->file('video_files', [])
            );

            return redirect()->route('product.index')
                ->with('success', 'Product created successfully!');
        } catch (\Throwable $e) {
            Log::error('Product store failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return back()
                ->withInput()
                ->with('error', 'Product creation failed: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product)
    {
        $product->load([
            'vendor:id,name,currency,rating,link',
            'category:id,name,slug,type',
            'location',
            'price',
            'prices',
            'options.pricingCategories.tieredPrices',
            'cancellationPolicies',
            'keywords:id,name',
            'productImages',
            'productVideos',
        ]);

        return view('backend.layouts.product.show', compact('product'));
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Product $product)
    {
        $product->load([
            'vendor:id,name,currency,rating,link',
            'category:id,name,slug,type',
            'location',
            'price',
            'prices',
            'options.pricingCategories.tieredPrices',
            'cancellationPolicies',
            'keywords:id,name',
            'productImages',
            'productVideos',
        ]);

        $vendors = $this->vendorRepository->getForDropdown();
        $categories = $this->categoriesRepository->getForDropdown();
        $allKeywords = Keyword::orderBy('name')->get();

        $masterKeywords = old('keywords', $product->keywords->pluck('name')->values()->toArray());
        $hotelKeywords = old('hotel_keywords', []);
        $bookingQuestions = old('booking_questions', $product->bookingQuestions?->toArray() ?? []);

        return view('backend.layouts.product.edit', compact(
            'product', 'vendors', 'categories', 'allKeywords',
            'bookingQuestions', 'masterKeywords', 'hotelKeywords'
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product)
    {
        try {
            $this->productService->update(
                $product,
                array_merge($request->validated(), [
                    'remove_video_ids'  => $request->input('remove_videos', []),
                    'deleted_image_ids' => $request->filled('deleted_images')
                        ? explode(',', $request->deleted_images)
                        : [],
                ]),
                [
                    'thumbnail'  => $request->file('thumbnail'),
                    'new_videos'  => $request->file('video_files', []),
                    'new_images'  => $this->normalizeUpdateImages($request),
                ]
            );

            return redirect()->route('product.index')
                ->with('success', 'Product updated successfully!');
        } catch (\Throwable $e) {
            Log::error('Product update failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return back()
                ->withInput()
                ->with('error', 'Product update failed: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $product = $this->productRepository->findOrFail($id);
            $this->productService->delete($product);

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Product delete failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Delete failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Bulk delete products.
     */
    public function bulkDelete(Request $request)
    {
        // Support both JSON body (application/json) and form-encoded
        $ids = $request->json('ids') ?? $request->input('ids');
        $ids = is_array($ids) ? $ids : [];

        if (empty($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'No products selected.',
            ], 400);
        }

        try {
            $result = $this->productService->bulkDelete($ids);
            return response()->json([
                'success' => true,
                'message' => $result['count'] . ' product(s) deleted successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Product bulk delete failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Bulk delete failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Toggle the is_top status of a product.
     */
    public function toggleTop(Request $request, $id)
    {
        try {
            $product = $this->productRepository->findOrFail($id);
            $product->is_top = !$product->is_top;
            $product->save();

            return response()->json([
                'success' => true,
                'message' => 'Top product status updated successfully.',
                'is_top' => $product->is_top
            ]);
        } catch (\Exception $e) {
            Log::error('Product toggle top failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status.',
            ], 500);
        }
    }

    /**
     * Normalize update images into a format the service expects.
     */
    private function normalizeUpdateImages(Request $request): array
    {
        $images = [];
        foreach ($request->file('images', []) as $index => $row) {
            $images[$index] = [
                'original' => $row['original'] ?? null,
                'large'    => null,
                'preview'  => $row['preview'] ?? null,
            ];
        }
        return $images;
    }

    private function resolveMediaUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (\Illuminate\Support\Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $cleanPath = ltrim($path, '/');
        if (strpos($cleanPath, 'storage/') === 0) {
            $cleanPath = substr($cleanPath, 8);
        }

        return Storage::disk('public')->url($cleanPath);
    }
}
