<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DestinationHighlight\StoreDestinationHighlightRequest;
use App\Http\Requests\DestinationHighlight\UpdateDestinationHighlightRequest;
use App\Models\DestinationHighlight;
use App\Repositories\Contracts\CityRepositoryInterface;
use App\Repositories\Contracts\DestinationHighlightRepositoryInterface;
use App\Services\DestinationHighlightService;
use App\Support\ManualSelectionOrder;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class DestinationHighlightController extends Controller
{
    public function __construct(
        private DestinationHighlightService $service,
        private DestinationHighlightRepositoryInterface $destinationHighlightRepository,
        private CityRepositoryInterface $cityRepository
    ) {}

    /**
     * List all destination highlights (DataTables).
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = $this->destinationHighlightRepository->query()
                ->with('destination:id,city_name,country_name')
                ->withCount('products')
                ->latest()
                ->get();

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('destination', function (DestinationHighlight $row) {
                    $name  = $row->destination_type === 'city'
                        ? $row->destination?->city_name
                        : $row->destination?->country_name;
                    $badge = $row->destination_type === 'city'
                        ? '<span class="badge bg-info">City</span>'
                        : '<span class="badge bg-primary">Country</span>';
                    return $badge . ' ' . e($name ?? 'N/A');
                })
                ->addColumn('image', function (DestinationHighlight $row) {
                    return $row->image;
                })
                ->addColumn('status', function (DestinationHighlight $row) {
                    return $row->status === 'active'
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-danger">Inactive</span>';
                })
                ->addColumn('products_count', fn (DestinationHighlight $row) => $row->products_count)
                ->addColumn('action', function (DestinationHighlight $row) {
                    $edit   = '<a href="' . route('destination-highlights.edit', $row->id) . '" class="btn btn-sm btn-primary me-1">Edit</a>';
                    $delete = '<button onclick="confirmDelete(' . $row->id . ')" class="btn btn-sm btn-danger">Delete</button>';
                    return $edit . $delete;
                })
                ->rawColumns(['destination', 'status', 'action'])
                ->make(true);
        }

        return view('backend.layouts.destination_highlights.index');
    }

    /**
     * Create form.
     */
    public function create()
    {
        $cities    = $this->cityRepository->query()
                        ->select('id', 'city_name', 'country_name', 'country_short_code', 'slug')
                        ->orderBy('city_name')
                        ->get();
        $countries = $this->cityRepository->query()
                        ->whereNotNull('country_name')
                        ->where('country_name', '!=', '')
                        ->select('id', 'country_name', 'country_short_code', 'country_slug')
                        ->distinct('country_name')
                        ->orderBy('country_name')
                        ->get();
        $selectedProductIds = old('product_ids', []);
        $products = ManualSelectionOrder::sortModelsByIds(
            \App\Models\Product::with('category')
                        ->select('id', 'title', 'provider', 'platform', 'product_api_id', 'category_id')
                        ->orderBy('title')
                        ->get(),
            $selectedProductIds
        );

        return view('backend.layouts.destination_highlights.create', compact('cities', 'countries', 'products'));
    }

    /**
     * Store new destination highlight.
     */
    public function store(StoreDestinationHighlightRequest $request)
    {
        $productIds = $this->resolveProductIds($request);

        $this->service->create(
            $request->validated(),
            $productIds,
            $request->file('image')
        );

        return redirect()
            ->route('destination-highlights.index')
            ->with('t-success', 'Destination Highlight created successfully.');
    }

    /**
     * Edit form.
     */
    public function edit(string $id)
    {
        $highlight = $this->destinationHighlightRepository->query()
                        ->with('products:id,title,product_api_id,provider,platform')
                        ->findOrFail((int) $id);

        $cities    = $this->cityRepository->query()
                        ->select('id', 'city_name', 'country_name', 'country_short_code', 'slug')
                        ->orderBy('city_name')
                        ->get();
        $countries = $this->cityRepository->query()
                        ->whereNotNull('country_name')
                        ->where('country_name', '!=', '')
                        ->select('id', 'country_name', 'country_short_code', 'country_slug')
                        ->distinct('country_name')
                        ->orderBy('country_name')
                        ->get();
        $selectedProductIds = old('product_ids', $highlight->products->pluck('id')->toArray());
        $products = ManualSelectionOrder::sortModelsByIds(
            \App\Models\Product::with('category')
                        ->select('id', 'title', 'provider', 'platform', 'product_api_id', 'category_id')
                        ->orderBy('title')
                        ->get(),
            $selectedProductIds
        );

        return view('backend.layouts.destination_highlights.edit', compact(
            'highlight', 'cities', 'countries', 'products'
        ));
    }

    /**
     * Update existing destination highlight.
     */
    public function update(UpdateDestinationHighlightRequest $request, string $id)
    {
        $highlight = $this->destinationHighlightRepository->findOrFail((int) $id);

        $productIds = $this->resolveProductIds($request);

        $this->service->update(
            $highlight,
            $request->validated(),
            $productIds,
            $request->file('image')
        );

        return redirect()
            ->route('destination-highlights.index')
            ->with('t-success', 'Destination Highlight updated successfully.');
    }

    /**
     * Delete a destination highlight.
     */
    public function destroy(string $id)
    {
        $highlight = $this->destinationHighlightRepository->findOrFail((int) $id);
        $this->service->delete($highlight);

        return response()->json(['status' => 'success', 'message' => 'Deleted successfully.']);
    }

    /**
     * AJAX: Load products for a destination (used by create/edit form).
     */
    public function loadProducts(Request $request)
    {
        $request->validate([
            'destination_id'   => 'required|integer|exists:cities,id',
            'destination_type' => 'required|in:city,country',
        ]);

        $products = $this->service->getProductsForDestination(
            (int) $request->destination_id,
            $request->destination_type
        );

        return response()->json([
            'products' => $products->map(fn ($p) => [
                'id'             => $p->id,
                'title'          => $p->title,
                'provider'       => ucfirst($p->provider ?? $p->platform ?? 'manual'),
                'product_api_id' => $p->product_api_id,
            ])->values(),
        ]);
    }

    /**
     * Resolve product IDs from API IDs and selected product IDs.
     */
    private function resolveProductIds(Request $request): array
    {
        $selectedProductIds = collect($request->input('product_ids', []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id);

        $apiIds = $this->parseProductApiIds((string) $request->input('product_api_ids', ''));

        if ($apiIds === []) {
            return ManualSelectionOrder::ids($selectedProductIds->all());
        }

        $productsByApiId = \App\Models\Product::query()
            ->whereIn('product_api_id', $apiIds)
            ->get(['id', 'product_api_id'])
            ->groupBy(fn (\App\Models\Product $product) => (string) $product->product_api_id);

        $apiProductIds = $productsByApiId
            ->flatten(1)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        return ManualSelectionOrder::ids($selectedProductIds
            ->merge($apiProductIds)
            ->all());
    }

    /**
     * Parse comma/newline-separated API IDs from textarea.
     */
    private function parseProductApiIds(string $value): array
    {
        return collect(preg_split('/[\r\n,]+/', $value) ?: [])
            ->map(fn (string $apiId) => trim($apiId))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
