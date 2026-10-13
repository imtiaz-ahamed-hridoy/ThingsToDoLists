<?php

namespace App\Http\Controllers\API\Home;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\FeatureTypeRepositoryInterface;
use App\Traits\AppliesProductRequestState;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class FeatureTypeApiController extends Controller
{
    use ApiResponse;
    use AppliesProductRequestState;

    public function __construct(
        private FeatureTypeRepositoryInterface $featureTypeRepository
    ) {}

    /**
     * List feature types with pagination (cached, withCount for N+1 fix).
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        $page = (int) $request->input('page', 1);

        $cacheKey = 'feature_types:page:' . $page . '|per_page:' . $perPage;

        $data = Cache::remember($cacheKey, now()->addHour(), function () use ($perPage) {
            $paginator = $this->featureTypeRepository->query()
                ->where('status', true)
                ->withCount('products')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->paginate(max(1, min($perPage, 200)));

            return [
                'items' => $paginator->getCollection()->map(function ($type) {
                    return [
                        'id' => $type->id,
                        'name' => $type->name,
                        'slug' => $type->slug,
                        'description' => $type->description,
                        'sort_order' => $type->sort_order,
                        'product_count' => (int) $type->products_count,
                    ];
                }),
                'paginator' => $paginator,
            ];
        });

        return $this->pagination('Feature types fetched successfully.', $data['items'], $data['paginator']);
    }

    /**
     * List products for a specific feature type (by slug) with pagination (cached).
     */
    public function products(Request $request, string $slug)
    {
        $type = $this->featureTypeRepository->findActiveBySlug($slug);
        if (! $type) {
            return $this->error('Feature type not found', 404);
        }

        $perPage = (int) $request->input('per_page', 12);
        $page = (int) $request->input('page', 1);
        $cacheKey = 'feature_type:products:' . $slug . '|page:' . $page . '|per_page:' . $perPage;

        $data = Cache::remember($cacheKey, now()->addHour(), function () use ($type, $perPage) {
            $query = $type->products()
                ->select([
                    'id', 'title', 'slug', 'thumbnail', 'from_price', 'original_price',
                    'default_currency', 'reviewRating', 'reviewCount',
                    'durationText', 'duration',
                ])
                ->with(['primaryImage', 'location']);

            $paginator = $query->paginate(max(1, min($perPage, 200)));

            $items = collect($paginator->items())->map(function ($product) {
                $image = $product->relationLoaded('primaryImage') && $product->primaryImage
                    ? ($product->primaryImage->original_url ?? $product->primaryImage->large_url ?? null)
                    : null;

                $displayPrices = $product->getDisplayPrices();
                $fromPrice     = $displayPrices['from_price'] ?? $product->from_price;
                $originalPrice = $displayPrices['original_price'] ?? $product->original_price;

                return [
                    'id' => $product->id,
                    'title' => $product->title,
                    'slug' => $product->slug,
                    'thumbnail' => $product->thumbnail,
                    'image' => $image,
                    'from_price' => $fromPrice,
                    'original_price' => $originalPrice,
                    'fromPrice' => $fromPrice,
                    'originalPrice' => $originalPrice,
                    'price' => $fromPrice,
                    'default_currency' => $product->default_currency,
                    'currency' => $product->default_currency,
                    'is_wishlisted' => false,
                    'reviewRating' => $product->reviewRating,
                    'reviewCount' => $product->reviewCount,
                    'duration' => $product->durationText ?? $product->duration,
                    'location' => $product->location ? [
                        'city' => $product->location->city,
                        'country' => $product->location->country,
                    ] : null,
                ];
            })->values();

            return [
                'items' => $items,
                'paginator' => $paginator,
            ];
        });

        $data['items'] = $data['items']
            ->map(fn (array $item) => $this->applyProductRequestState($item))
            ->values();

        return $this->pagination('Featured products fetched successfully.', $data['items'], $data['paginator']);
    }
}
