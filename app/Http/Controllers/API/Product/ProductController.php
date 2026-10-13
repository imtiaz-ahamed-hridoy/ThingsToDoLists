<?php

namespace App\Http\Controllers\API\Product;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductListResource;
use App\Repositories\Contracts\CategoriesRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\GlobalTix\GlobalTixPriceSyncService;
use App\Traits\ApiResponse;
use App\Models\Product;
use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private CategoriesRepositoryInterface $categoriesRepository
    ) {}

    /**
     * Product list endpoint with pagination, filters and sorting.
     */
    public function index(Request $request)
    {
        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));
        $sort = strtolower((string) $request->input('sort', 'popular'));

        $filters = array_filter([
            'category_id' => $request->input('category_id'),
            'provider' => $request->input('provider'),
            'price_min' => $request->input('price_min'),
            'price_max' => $request->input('price_max'),
            'city' => $request->input('city'),
            'country' => $request->input('country'),
        ], fn($v) => $v !== null && $v !== '');

        // Build cache key from pagination params
        $page = (int) $request->input('page', 1);
        $cacheKey = 'product_list:' . md5(serialize($filters) . '|' . $sort . '|' . $perPage . '|' . $page);

        $products = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($filters, $sort, $perPage) {
            return $this->productRepository->getPublishedProducts($filters, $sort, $perPage);
        });

        $products->appends($request->query());

        return $this->pagination(
            'Data fetched successfully',
            ProductListResource::collection($products->getCollection()),
            $products,
            200
        );
    }

    /**
     * Category-wise products endpoint for frontend category tabs/filters.
     */
    public function categoryWiseProducts(Request $request)
    {
        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));
        $sort = strtolower((string) $request->input('sort', 'popular'));

        $activeProductFilter = fn ($query) => $query
            ->where('published', true)
            ->where('status', 'active');

        $categories = $this->categoriesRepository
            ->getActiveWithProductCount($activeProductFilter)
            ->map(fn ($category) => new CategoryResource($category))
            ->values();

        $selectedCategory = null;

        if ($request->filled('category_id') || $request->filled('category_slug')) {
            $query = $this->categoriesRepository->query()
                ->where('status', 1);

            if ($request->filled('category_id')) {
                $query->where('id', (int) $request->input('category_id'));
            }
            if ($request->filled('category_slug')) {
                $query->where('slug', (string) $request->input('category_slug'));
            }

            $selectedCategory = $query->first();

            if (!$selectedCategory) {
                return $this->error('Category not found', 404);
            }

            $selectedCategory->loadCount(['products as products_count' => $activeProductFilter]);
        }

        $productsQuery = $this->productRepository->getPublishedBaseQuery([
            'location',
            'vendor',
            'category',
            'options.pricingCategories',
            'prices',
            'productImages' => fn ($q) => $q->active()->ordered(),
        ]);

        if ($selectedCategory) {
            $productsQuery->where('category_id', $selectedCategory->id);
        }

        if ($request->filled('provider')) {
            $productsQuery->where('provider', strtolower((string) $request->input('provider')));
        }

        match ($sort) {
            'price_low' => $productsQuery->orderBy('from_price')->orderByDesc('reviewCount'),
            'price_high' => $productsQuery->orderByDesc('from_price')->orderByDesc('reviewCount'),
            'newest' => $productsQuery->orderByDesc('created_at'),
            'rating' => $productsQuery->orderByDesc('reviewRating')->orderByDesc('reviewCount'),
            default => $productsQuery->orderByDesc('reviewCount')->orderByDesc('reviewRating'),
        };

        $products = $productsQuery->paginate($perPage)->appends($request->query());

        return response()->json([
            'status' => 200,
            'message' => 'Category wise products fetched successfully',
            'data' => [
                'categories' => $categories,
                'selected_category' => $selectedCategory ? new CategoryResource($selectedCategory) : null,
                'products' => ProductListResource::collection($products->getCollection())->resolve($request),
            ],
            'pagination' => $this->paginationPayload($products),
        ]);
    }

    /**
     * Product detail endpoint.
     */
    public function show(Request $request, $id = null)
    {
        $productApiId = $request->input('product_api_id');
        $platform = $request->input('platform');

        if ($id === null && (!$productApiId || !$platform)) {
            return $this->error('Product identifier required', 400);
        }

        $productId = ($id !== null && is_numeric($id) && !$productApiId) ? (int) $id : null;

        // Cache product detail for 10 minutes — changes infrequently
        $cacheKey = 'product_detail:' . ($productId ?? ($productApiId . '|' . $platform));

        $product = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($productId, $productApiId, $platform) {
            return $this->productRepository->getPublishedProductDetail(
                $productId,
                $productId === null ? $productApiId : null,
                $productId === null ? $platform : null
            );
        });

        if (!$product) {
            return $this->error('Product not found in local database', 404);
        }

        if (strtolower((string) ($product->provider ?: $product->platform ?: '')) === 'globaltix') {
            $syncRes = app(GlobalTixPriceSyncService::class)->syncProductLivePrice($product);
            if (!empty($syncRes['from_price']) && $syncRes['from_price'] > 0) {
                $product->from_price = $syncRes['from_price'];
                $product->original_price = $syncRes['original_price'] ?? $syncRes['from_price'];
            }
        }

        return $this->success('Data fetched successfully', new ProductDetailResource($product), 200);
    }

    private function paginationPayload($pagination): array
    {
        return [
            'current_page' => $pagination->currentPage(),
            'last_page' => $pagination->lastPage(),
            'per_page' => $pagination->perPage(),
            'total' => $pagination->total(),
            'first_page_url' => $pagination->url(1),
            'last_page_url' => $pagination->url($pagination->lastPage()),
            'next_page_url' => $pagination->nextPageUrl(),
            'prev_page_url' => $pagination->previousPageUrl(),
            'from' => $pagination->firstItem(),
            'to' => $pagination->lastItem(),
            'path' => $pagination->path(),
        ];
    }

    /**
     * Get similar products based on category and location (city or country).
     */
    public function similarProducts($id)
    {
        $product = Product::with('location')->find($id);

        if (!$product) {
            return $this->error('Product not found', 404);
        }

        $city = $product->location?->city;
        $country = $product->location?->country;
        $categoryId = $product->category_id;

        $query = Product::where('status', 'active')
            ->where('published', true)
            ->where('id', '!=', $product->id)
            ->where('category_id', $categoryId)
            ->with(['location', 'category', 'price', 'productImages' => fn($q) => $q->active()->ordered()]);

        if ($city) {
            $query->whereHas('locations', fn($q) => $q->where('city', $city));
        } elseif ($country) {
            $query->whereHas('locations', fn($q) => $q->where('country', $country));
        }

        $products = $query->orderByDesc('reviewRating')
            ->orderByDesc('reviewCount')
            ->take(4)
            ->get();

        // Fallback: if no products found by location, just use the same category
        if ($products->isEmpty()) {
            $products = Product::where('status', 'active')
                ->where('published', true)
                ->where('id', '!=', $product->id)
                ->where('category_id', $categoryId)
                ->with(['location', 'category', 'price', 'productImages' => fn($q) => $q->active()->ordered()])
                ->orderByDesc('reviewRating')
                ->orderByDesc('reviewCount')
                ->take(4)
                ->get();
        }

        return $this->success('Similar experiences fetched successfully', ProductListResource::collection($products), 200);
    }

    /**
     * Get top products in the same city.
     */
    public function topInCity($id)
    {
        $product = Product::with('location')->find($id);

        if (!$product) {
            return $this->error('Product not found', 404);
        }

        $city = $product->location?->city;

        if (!$city) {
            return $this->success('Top things to do fetched successfully', [], 200);
        }

        $products = Product::where('status', 'active')
            ->where('published', true)
            ->where('id', '!=', $product->id)
            ->whereHas('locations', fn($q) => $q->where('city', $city))
            ->with(['location', 'category', 'price', 'productImages' => fn($q) => $q->active()->ordered()])
            ->orderByDesc('is_top')
            ->orderByDesc('reviewRating')
            ->orderByDesc('reviewCount')
            ->take(10)
            ->get();

        return $this->success("Top things to do in $city fetched successfully", ProductListResource::collection($products), 200);
    }

    /**
     * Get nearby cities (same country or popular ones).
     */
    public function nearbyCities($id)
    {
        $product = Product::with('location')->find($id);

        if (!$product) {
            return $this->error('Product not found', 404);
        }

        $country = $product->location?->country;

        if (!$country) {
            $cities = City::where('item_status', 1)->inRandomOrder()->take(4)->get();
        } else {
            $cities = City::where('item_status', 1)
                ->where('country_name', $country)
                ->where('city_name', '!=', $product->location?->city)
                ->inRandomOrder()
                ->take(4)
                ->get();

            if ($cities->count() < 4) {
                $moreCities = City::where('item_status', 1)
                    ->where('country_name', '!=', $country)
                    ->inRandomOrder()
                    ->take(4 - $cities->count())
                    ->get();
                $cities = $cities->merge($moreCities);
            }
        }

        $formattedCities = $cities->map(function ($city) {
            return [
                'id' => $city->id,
                'title' => 'Things to do in ' . $city->city_name,
                'city_name' => $city->city_name,
                'country_name' => $city->country_name,
                'slug' => $city->slug,
                'image' => $city->thumbnail_image,
            ];
        });

        return $this->success('Nearby cities fetched successfully', $formattedCities, 200);
    }
}
