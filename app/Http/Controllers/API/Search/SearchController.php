<?php

namespace App\Http\Controllers\API\Search;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Categories;
use App\Models\City;
use App\Models\Location;
use App\Models\Product;
use App\Repositories\Contracts\CategoriesRepositoryInterface;
use App\Repositories\Contracts\CityRepositoryInterface;
use App\Repositories\Contracts\LocationRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\RecentlyViewedRepositoryInterface;
use App\Traits\AppliesProductRequestState;
use App\Traits\HasCurrency;
use App\Traits\HasLanguage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    use AppliesProductRequestState, HasLanguage, HasCurrency;

    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private CategoriesRepositoryInterface $categoriesRepository,
        private LocationRepositoryInterface $locationRepository,
        private CityRepositoryInterface $cityRepository,
        private RecentlyViewedRepositoryInterface $recentlyViewedRepository
    ) {}

    /**
     * Lightweight Headout-style autocomplete endpoint.
     */
    public function search(Request $request)
    {
        $payload = $this->getSearchPayload('suggestions', $request, function () use ($request) {
            return $this->buildSuggestionResponse($request);
        });

        $this->appendWishlistStatus($payload);

        return response()->json($payload, $payload['status'] ?? 200);
    }

    /**
     * Full Headout-style search results page endpoint.
     */
    public function cardSearch(Request $request)
    {
        $payload = $this->getSearchPayload('results', $request, function () use ($request) {
            return $this->buildGlobalSearchResponse($request);
        });

        $this->appendWishlistStatus($payload);

        return response()->json($payload, $payload['status'] ?? 200);
    }

    /**
     * Headout-style city page endpoint.
     */
    public function cityPage(Request $request, string $city)
    {
        $payload = $this->getSearchPayload('city_page', $request, function () use ($request, $city) {
            $context = $this->normalizeSearchRequest($request, 24, 100);
            $decodedCity = urldecode($city);

            $baseProducts = $this->baseProductQuery(true)
                ->whereHas('locations', fn(Builder $location) => $location->where('city', 'LIKE', '%' . $decodedCity . '%'));

            $matchingCategoryIds = $this->matchingCategoryIds($baseProducts);
            $productsQuery = clone $baseProducts;
            $this->applySorting($productsQuery, $context['sort'], '');

            $products = $productsQuery
                ->paginate($context['limit'])
                ->appends($request->except('tab'));

            $collectionCards = $this->categorySuggestions('', 100, $context['currency'], $context['language'], $decodedCity, $matchingCategoryIds);
            $productCards = $this->productCardRows($products->getCollection(), $context['currency'], $context['language']);

            $collectionCardsPayload = [
                'items' => $collectionCards,
                'nextUrl' => null,
                'total' => count($collectionCards),
                'nextOffset' => null,
            ];

            $productCardsPayload = $this->cardsPayload($productCards, $products);

            $location = $this->locationRepository->findByCity($decodedCity);
            $imageUrl = $location ? $this->destinationImageUrl($location) : null;

            return [
                'success' => true,
                'status' => 200,
                'message' => 'City page retrieved successfully.',
                'data' => [
                    'query' => $decodedCity,
                    'heading' => "Discover the best things to do in " . ucwords($decodedCity),
                    'subheading' => "A curated collection of the city's top-rated tours, iconic attractions, and unmissable things to do.",
                    'imageUrl' => $imageUrl,
                    'language' => $context['language'],
                    'currency' => $context['currency'],
                    'sections' => [
                        [
                            'type' => 'COLLECTIONS',
                            'title' => "Top categories in " . ucwords($decodedCity),
                            'items' => $collectionCardsPayload['items'],
                        ],
                        [
                            'type' => 'PRODUCTS',
                            'title' => "Top products in " . ucwords($decodedCity),
                            'items' => $productCardsPayload['items'],
                        ],
                    ],
                    'entityType' => 'CITY',
                    'entityId' => $decodedCity,
                    'total' => $products->total(),
                ],
                'pagination' => $this->paginationPayload($products),
            ];
        }, md5($city));

        $this->appendWishlistStatus($payload);

        return response()->json($payload, $payload['status'] ?? 200);
    }

    /**
     * Headout-style collection page endpoint.
     */
    public function collectionPage(Request $request, string $slug)
    {
        $payload = $this->getSearchPayload('collection_page', $request, function () use ($request, $slug) {
            $context = $this->normalizeSearchRequest($request, 24, 100);

            $category = $this->categoriesRepository->findBySlug($slug);

            if (!$category) {
                return [
                    'success' => false,
                    'status' => 404,
                    'message' => 'Collection not found.',
                ];
            }

            $baseProducts = $this->baseProductQuery(true)
                ->where('category_id', $category->id);

            $productsQuery = clone $baseProducts;
            $this->applySorting($productsQuery, $context['sort'], '');

            $products = $productsQuery
                ->paginate($context['limit'])
                ->appends($request->except('tab'));

            $productCards = $this->productCardRows($products->getCollection(), $context['currency'], $context['language']);

            $productCardsPayload = $this->cardsPayload($productCards, $products);
            $displayName = $this->categoryDisplayName($category);

            $stats = $this->categoryStats($category, $context['currency']);
            $representativeProduct = $this->categoryRepresentativeProduct($category);
            $imageUrl = $representativeProduct ? $this->primaryImageUrl($representativeProduct) : null;

            return [
                'success' => true,
                'status' => 200,
                'message' => 'Collection page retrieved successfully.',
                'data' => [
                    'query' => $slug,
                    'heading' => $displayName,
                    'subheading' => $category->search_subtitle ?: "Explore our top rated $displayName.",
                    'imageUrl' => $imageUrl,
                    'ratings' => [
                        'count' => $stats['ratingsCount'],
                        'value' => $stats['ratingsValue'],
                    ],
                    'listingPrice' => [
                        'listingPrice' => $stats['listingPrice'],
                        'originalListingPrice' => $stats['originalListingPrice'],
                        'currency' => $stats['currency'],
                    ],
                    'language' => $context['language'],
                    'currency' => $context['currency'],
                    'sections' => [
                        [
                            'type' => 'PRODUCTS',
                            'title' => "All products",
                            'items' => $productCardsPayload['items'],
                        ],
                    ],
                    'entityType' => 'COLLECTION',
                    'entityId' => $category->id,
                    'total' => $products->total(),
                ],
                'pagination' => $this->paginationPayload($products),
            ];
        }, md5($slug));

        $this->appendWishlistStatus($payload);

        return response()->json($payload, $payload['status'] ?? 200);
    }

    /**
     * Unified destination landing page (city or country).
     */
    public function destinationPage(Request $request, string $slug)
    {
        $payload = $this->getSearchPayload('destination_page', $request, function () use ($request, $slug) {
            $context = $this->normalizeSearchRequest($request, 24, 100);

            // Resolve destination using shared helper
            $resolved = $this->resolveDestination($slug);
            if (!$resolved['success']) {
                return [
                    'success' => false,
                    'status' => 404,
                    'message' => 'Destination not found.',
                ];
            }

            $location = $resolved['location'];
            $isCity = $resolved['isCity'];
            $destinationName = $resolved['destinationName'];
            $destinationSlug = $this->destinationSlugFromLocation($location, $slug);

            // Filter products based on city or country
            $baseProducts = $this->destinationProductsQuery($location, $isCity);

            // Build destination object and collections
            $collectionSlug = $request->input('collection');
            $destination = $this->buildDestinationObject($location, $isCity, $slug);
            $collectionsPayload = $this->getDestinationCollections($location, $isCity, $destinationName, $destinationSlug, $collectionSlug);

            $productsQuery = clone $baseProducts;
            
            // Filter by collection (category slug) if present
            if ($collectionSlug) {
                $productsQuery->whereHas('category', fn ($q) => $q->where('slug', $collectionSlug));
            }
            
            $this->applySorting($productsQuery, $context['sort'], '');

            $products = $productsQuery
                ->paginate($context['limit'])
                ->appends($request->except('tab'));

            $productCards = $this->destinationProductCardRows($products->getCollection(), $context['currency'], $context['language']);
            $productCardsPayload = $this->cardsPayload($productCards, $products);
            $imageUrl = $this->destinationHeroImageUrl($baseProducts);

            $typeLabel = $isCity ? 'City' : 'Country';
            $displayName = ucwords($destinationName);

            return [
                'success' => true,
                'status' => 200,
                'message' => "{$typeLabel} page retrieved successfully.",
                'data' => [
                    'query' => $slug,
                    'hero' => [
                        'title' => "Discover the best things to do in " . $displayName,
                        'subtitle' => "A curated collection of the {$typeLabel}'s top-rated tours, iconic attractions, and unmissable things to do.",
                        'image' => $imageUrl,
                        'language' => $context['language'],
                        'currency' => $context['currency'],
                        'destination' => [
                            'flag' => $destination['flag'],
                            'city' => $destination['city'],
                            'country' => $destination['country'],
                        ],
                    ],
                    'sections' => [
                        [
                            'type' => 'COLLECTIONS',
                            'title' => 'Collections',
                            'selectedCollection' => $collectionsPayload['selectedCollection'],
                            'items' => $collectionsPayload['items'],
                        ],
                        [
                            'type' => 'PRODUCTS',
                            'title' => "Top products in " . $displayName,
                            'items' => $productCardsPayload['items'],
                        ],
                    ],
                    'entityType' => strtoupper($typeLabel),
                    'entityId' => $destinationName,
                    'total' => $products->total(),
                ],
                'pagination' => $this->paginationPayload($products),
            ];
        }, md5($slug));

        $this->appendWishlistStatus($payload);

        return response()->json($payload, $payload['status'] ?? 200);
    }

    /**
     * Trending / empty-state search suggestions.
     */
    public function trending(Request $request)
    {
        $payload = $this->getSearchPayload('trending', $request, function () use ($request) {
            $context = $this->normalizeSearchRequest($request, 10, 20);

            $destinations = $this->locationRepository->getPopularDestinations(5)
                ->map(fn(Location $location) => $this->cityPayload($location))
                ->values()
                ->all();

            // Fetch top 5 popular experiences (highest review count / rating)
            $experiences = $this->baseProductQuery(true)
                ->orderByDesc('reviewCount')
                ->orderByDesc('reviewRating')
                ->limit(5)
                ->get();

            $experienceCards = $this->productCardRows($experiences, $context['currency'], $context['language']);
            $collections = $this->categorySuggestions('', 100, $context['currency'], $context['language']);

            return [
                'success' => true,
                'status' => 200,
                'message' => 'Trending search suggestions retrieved successfully.',
                'data' => [
                    'destinations' => $destinations,
                    'collections' => $collections,
                    'products' => $experienceCards,
                ],
            ];
        });

        $this->appendWishlistStatus($payload);

        return response()->json($payload, $payload['status'] ?? 200);
    }

    /**
     * Guest recently viewed products (hydrated from localStorage IDs).
     */
    public function recentlyViewedPublic(Request $request)
    {
        $context = $this->normalizeSearchRequest($request, 24, 100);
        $idsStr = $request->input('ids', '');
        
        $ids = collect(explode(',', $idsStr))
            ->map(fn($id) => (int) trim($id))
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'No recently viewed products found.',
                'data' => [
                    'items' => [],
                    'total' => 0,
                ],
            ]);
        }

        $products = $this->baseProductQuery(true)
            ->whereIn('id', $ids)
            ->get();

        // Sort products in the exact order of the IDs requested
        $sortedProducts = collect($ids)
            ->map(fn($id) => $products->firstWhere('id', $id))
            ->filter()
            ->values();

        $productCards = $this->productCardRows($sortedProducts, $context['currency'], $context['language']);

        $payload = [
            'success' => true,
            'status' => 200,
            'message' => 'Recently viewed products retrieved successfully.',
            'data' => [
                'items' => $productCards,
                'total' => count($productCards),
            ],
        ];

        $this->appendWishlistStatus($payload);

        return response()->json($payload);
    }

    /**
     * Record a product view for an authenticated user.
     */
    public function recordRecentlyViewed(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
        ]);

        $userId = $request->user()->id;
        $productId = (int) $request->input('product_id');

        $this->recentlyViewedRepository->recordView($userId, $productId);

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Product view recorded successfully.',
        ]);
    }

    /**
     * Get recently viewed products for an authenticated user.
     */
    public function getRecentlyViewedAuth(Request $request)
    {
        $context = $this->normalizeSearchRequest($request, 24, 100);
        $userId = $request->user()->id;

        $viewedProductIds = $this->recentlyViewedRepository->getUserViewedProductIds($userId, 20);

        if (empty($viewedProductIds)) {
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'No recently viewed products found.',
                'data' => [
                    'items' => [],
                    'total' => 0,
                ],
            ]);
        }

        $products = $this->baseProductQuery(true)
            ->whereIn('id', $viewedProductIds)
            ->get();

        // Sort in the correct descending order of viewed_at / updated_at
        $sortedProducts = collect($viewedProductIds)
            ->map(fn($id) => $products->firstWhere('id', $id))
            ->filter()
            ->values();

        $productCards = $this->productCardRows($sortedProducts, $context['currency'], $context['language']);

        $payload = [
            'success' => true,
            'status' => 200,
            'message' => 'Recently viewed products retrieved successfully.',
            'data' => [
                'items' => $productCards,
                'total' => count($productCards),
            ],
        ];

        $this->appendWishlistStatus($payload);

        return response()->json($payload);
    }

    private function buildSuggestionResponse(Request $request): array
    {
        $context = $this->normalizeSearchRequest($request, 8, 20);
        $suggestionLimit = min(5, $context['limit']);

        $productsQuery = $this->baseProductQuery(false);
        $this->applySearchTerm($productsQuery, $context['term']);
        $this->applyFilters($productsQuery, $request);
        $this->applySorting($productsQuery, $context['sort'], $context['term']);

        $matchingCategoryIds = $this->matchingCategoryIds($productsQuery);
        $products = $productsQuery->limit($context['limit'])->get();

        // Resolve destination/city filter for categories suggestions
        $locationFilter = null;
        if ($request->filled('city')) {
            $locationFilter = $request->input('city');
        } elseif ($request->filled('country')) {
            $locationFilter = $request->input('country');
        } elseif ($request->filled('destination')) {
            $locationFilter = $request->input('destination');
        }

        $cities = $this->destinationSuggestions($context['term'], $suggestionLimit);
        $collectionCards = $this->categorySuggestions($context['term'], 100, $context['currency'], $context['language'], $locationFilter, $matchingCategoryIds);

        return [
            'success' => true,
            'status' => 200,
            'message' => 'Search suggestions retrieved successfully.',
            'data' => [
                'query' => $context['term'],
                'language' => $context['language'],
                'currency' => $context['currency'],
                'results' => [
                    [
                        'type' => 'CITY',
                        'values' => $cities,
                    ],
                    [
                        'type' => 'PRODUCT',
                        'values' => $this->productAutocompleteRows($products),
                    ],
                    [
                        'type' => 'COLLECTION',
                        'values' => $this->collectionAutocompleteRows($collectionCards),
                    ],
                ],
                'action' => $this->searchActionPayload($context['term']),
            ],
        ];
    }

    private function buildGlobalSearchResponse(Request $request): array
    {
        $context = $this->normalizeSearchRequest($request, 24, 100);

        $baseProducts = $this->baseProductQuery(true);
        $this->applySearchTerm($baseProducts, $context['term']);
        $this->applyFilters($baseProducts, $request);

        $matchingCategoryIds = $this->matchingCategoryIds($baseProducts);
        $productsQuery = clone $baseProducts;
        $this->applySorting($productsQuery, $context['sort'], $context['term']);

        $products = $productsQuery
            ->paginate($context['limit'])
            ->appends($request->except('tab'));

        $collectionCards = $this->categorySuggestions($context['term'], 100, $context['currency'], $context['language'], null, $matchingCategoryIds);
        $productCards = $this->productCardRows($products->getCollection(), $context['currency'], $context['language']);

        $collectionCardsPayload = [
            'items' => $collectionCards,
            'nextUrl' => null,
            'total' => count($collectionCards),
            'nextOffset' => null,
        ];

        $productCardsPayload = $this->cardsPayload($productCards, $products);

        return [
            'success' => true,
            'status' => 200,
            'message' => 'Search results retrieved successfully.',
            'data' => [
                'query' => $context['term'],
                'heading' => $this->searchHeading($context['term']),
                'language' => $context['language'],
                'currency' => $context['currency'],
                'sections' => [
                    [
                        'type' => 'COLLECTIONS',
                        'title' => 'World Wide Collections',
                        'items' => $collectionCardsPayload['items'],
                    ],
                    [
                        'type' => 'PRODUCTS',
                        'title' => 'World Wide Products',
                        'items' => $productCardsPayload['items'],
                    ],
                ],
                'entityType' => null,
                'entityId' => null,
                'sortType' => null,
                'sortOrder' => null,
                'filterType' => null,
                'total' => $products->total(),
                'experimentationInfo' => null,
            ],
            'pagination' => $this->paginationPayload($products),
        ];
    }

    private function normalizeSearchRequest(Request $request, int $defaultLimit, int $maxLimit): array
    {
        $validated = $request->validate([
            'query' => 'nullable|string|max:255',
            'search' => 'nullable|string|max:255',
            'q' => 'nullable|string|max:255',
            'language' => 'nullable|string|max:10',
            'currency' => 'nullable|string|max:10',
            'limit' => 'nullable|integer|min:1|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort' => 'nullable|string|max:50',
            'provider' => 'nullable|string|max:50',
            'category_id' => 'nullable|integer|exists:categories,id',
            'category_slug' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'destination' => 'nullable|string|max:255',
            'price_min' => 'nullable|numeric|min:0',
            'price_max' => 'nullable|numeric|min:0',
            'duration' => 'nullable|string|max:255',
            'private_tour' => 'nullable|boolean',
            'recent_product_ids' => 'nullable|string|max:1000',
        ]);

        $term = trim((string) ($validated['query'] ?? $validated['search'] ?? $validated['q'] ?? ''));
        $limit = (int) ($validated['limit'] ?? $validated['per_page'] ?? $defaultLimit);
        $limit = max(1, min($maxLimit, $limit));

        return [
            'term' => $term,
            'limit' => $limit,
            'sort' => strtolower((string) ($validated['sort'] ?? 'relevance')),
            'language' => strtoupper($this->resolveLanguage()),
            'currency' => strtoupper($this->resolveCurrency()),
        ];
    }

    private function getSearchPayload(string $scope, Request $request, callable $resolver, string $extraKey = ''): array
    {
        $queryParams = $request->query();
        ksort($queryParams);
        $currency = $this->resolveCurrency();
        $language = $this->resolveLanguage();
        $cacheKey = 'search:' . $scope . ':' . md5(json_encode($queryParams) . ':' . $extraKey . ':' . $currency . ':' . $language);

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($resolver) {
            return $resolver();
        });
    }

    private function searchHeading(string $term): string
    {
        return $term === ''
            ? 'Showing results'
            : 'Showing results for ' . mb_strtolower($term);
    }

    private function baseProductQuery(bool $includeDetails = true): Builder
    {
        $query = $this->productRepository->query()
            ->where('status', 'active')
            ->where('published', true);

        if ($includeDetails) {
            return $query
                ->with([
                    'location',
                    'category',
                    'price',
                    'productImages' => fn($query) => $query->active()->ordered(),
                ])
                ->withCount([
                    'options as options_count',
                    'prices as prices_count',
                ]);
        }

        return $query
            ->with([
                'location',
                'productImages' => fn($query) => $query->active()->ordered(),
            ]);
    }

    private function applySearchTerm(Builder $query, string $term): void
    {
        if ($term === '') {
            return;
        }

        $like = '%' . $term . '%';
        $apiIds = $this->searchApiIds($term);

        if ($this->isProductApiIdListSearch($term, $apiIds)) {
            $query->where(function (Builder $query) use ($apiIds) {
                $query->whereIn('product_api_id', $apiIds)
                    ->orWhereIn('reference_code', $apiIds);
            });

            return;
        }

        $query->where(function (Builder $query) use ($like, $apiIds, $term) {
            $query->where(fn(Builder $phrase) => $this->applyProductSearchFields($phrase, $like, $term));

            if ($apiIds !== []) {
                $query->orWhereIn('product_api_id', $apiIds)
                    ->orWhereIn('reference_code', $apiIds);
            }
        });
    }

    private function applyProductSearchFields(Builder $query, string $like, string $rawTerm = ''): void
    {
        if ($rawTerm !== '') {
            $cleanTerm = preg_replace('/[^\pL\pN\s]+/u', ' ', $rawTerm);
            $cleanTerm = trim(preg_replace('/\s+/', ' ', $cleanTerm ?? ''));

            if ($cleanTerm !== '') {
                $words = explode(' ', $cleanTerm);
                $matchTerm = '+' . implode('* +', $words) . '*';

                $query->whereRaw(
                    'MATCH(title, description, full_description, short_introduction) AGAINST(? IN BOOLEAN MODE)',
                    [$matchTerm]
                )->orWhere(function(Builder $q) use ($like) {
                    $q->where('slug', 'LIKE', $like)
                      ->orWhere('product_api_id', 'LIKE', $like)
                      ->orWhere('reference_code', 'LIKE', $like)
                      ->orWhere('provider', 'LIKE', $like)
                      ->orWhere('platform', 'LIKE', $like)
                      ->orWhere('merchant_name', 'LIKE', $like)
                      ->orWhere('product_category', 'LIKE', $like)
                      ->orWhere('activityType', 'LIKE', $like)
                      ->orWhereIn('vendor_id', function ($sub) use ($like) {
                          $sub->select('id')->from('vendors')->where('name', 'LIKE', $like);
                      })
                      ->orWhereIn('category_id', function ($sub) use ($like) {
                          $sub->select('id')->from('categories')->where(function($c) use ($like) {
                              $c->where('name', 'LIKE', $like)
                                ->orWhere('slug', 'LIKE', $like)
                                ->orWhere('type', 'LIKE', $like)
                                ->orWhere('search_label', 'LIKE', $like)
                                ->orWhere('search_subtitle', 'LIKE', $like);
                          });
                      })
                      ->orWhereExists(function ($sub) use ($like) {
                          $sub->selectRaw('1')
                              ->from('keywords')
                              ->whereColumn('keywords.product_id', 'products.id')
                              ->where('name', 'LIKE', $like);
                      })
                      ->orWhereExists(function ($sub) use ($like) {
                          $sub->selectRaw('1')
                              ->from('locations')
                              ->whereColumn('locations.product_id', 'products.id')
                              ->where(function($l) use ($like) {
                                  $l->where('city', 'LIKE', $like)
                                    ->orWhere('country', 'LIKE', $like)
                                    ->orWhere('countryCode', 'LIKE', $like)
                                    ->orWhere('address', 'LIKE', $like)
                                    ->orWhere('label', 'LIKE', $like);
                              });
                      });
                });
                return;
            }
        }

        // Fallback for short/invalid terms
        $query->where('title', 'LIKE', $like)
            ->orWhere('slug', 'LIKE', $like)
            ->orWhere('product_api_id', 'LIKE', $like)
            ->orWhere('reference_code', 'LIKE', $like)
            ->orWhere('provider', 'LIKE', $like)
            ->orWhere('platform', 'LIKE', $like)
            ->orWhere('merchant_name', 'LIKE', $like)
            ->orWhere('product_category', 'LIKE', $like)
            ->orWhere('activityType', 'LIKE', $like)
            ->orWhereIn('vendor_id', function ($sub) use ($like) {
                $sub->select('id')->from('vendors')->where('name', 'LIKE', $like);
            })
            ->orWhereIn('category_id', function ($sub) use ($like) {
                $sub->select('id')->from('categories')->where(function($c) use ($like) {
                    $c->where('name', 'LIKE', $like)
                      ->orWhere('slug', 'LIKE', $like)
                      ->orWhere('type', 'LIKE', $like);
                });
            })
            ->orWhereExists(function ($sub) use ($like) {
                $sub->selectRaw('1')
                    ->from('keywords')
                    ->whereColumn('keywords.product_id', 'products.id')
                    ->where('name', 'LIKE', $like);
            })
            ->orWhereExists(function ($sub) use ($like) {
                $sub->selectRaw('1')
                    ->from('locations')
                    ->whereColumn('locations.product_id', 'products.id')
                    ->where(function($l) use ($like) {
                        $l->where('city', 'LIKE', $like)
                          ->orWhere('country', 'LIKE', $like);
                    });
            });
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('provider')) {
            $query->where('provider', strtolower((string) $request->input('provider')));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->input('category_id'));
        }

        if ($request->filled('category_slug')) {
            $query->whereHas('category', fn(Builder $category) => $category->where('slug', (string) $request->input('category_slug')));
        }

        if ($request->filled('city')) {
            $city = (string) $request->input('city');
            $query->whereHas('locations', fn(Builder $location) => $location->where('city', 'LIKE', '%' . $city . '%'));
        }

        if ($request->filled('country')) {
            $country = (string) $request->input('country');
            $query->whereHas('locations', fn(Builder $location) => $location->where('country', 'LIKE', '%' . $country . '%'));
        }

        if ($request->filled('destination')) {
            $destination = (string) $request->input('destination');
            $query->whereHas('locations', function (Builder $location) use ($destination) {
                $location->where('city', 'LIKE', '%' . $destination . '%')
                    ->orWhere('country', 'LIKE', '%' . $destination . '%');
            });
        }

        if ($request->filled('price_min')) {
            $query->where('from_price', '>=', (float) $request->input('price_min'));
        }

        if ($request->filled('price_max')) {
            $query->where('from_price', '<=', (float) $request->input('price_max'));
        }

        if ($request->filled('duration')) {
            $duration = (string) $request->input('duration');
            $query->where(function (Builder $query) use ($duration) {
                $query->where('duration', 'LIKE', '%' . $duration . '%')
                    ->orWhere('durationText', 'LIKE', '%' . $duration . '%')
                    ->orWhere('durationType', 'LIKE', '%' . $duration . '%');
            });
        }

        if ($request->has('private_tour')) {
            $isPrivate = $request->boolean('private_tour');
            $query->where(function (Builder $query) use ($isPrivate) {
                $query->where('privateActivity', $isPrivate)
                    ->orWhere('private_tour', $isPrivate ? '1' : '0')
                    ->orWhere('private_tour', $isPrivate ? 'yes' : 'no');
            });
        }
    }

    private function applySorting(Builder $query, string $sort, string $term): void
    {
        if ($sort === 'relevance' && $term !== '') {
            $needle = strtolower($term);

            $query->orderByRaw(
                'CASE
                    WHEN LOWER(title) = ? THEN 0
                    WHEN LOWER(title) LIKE ? THEN 1
                    WHEN LOWER(slug) LIKE ? THEN 2
                    ELSE 3
                END',
                [$needle, $needle . '%', '%' . $needle . '%']
            );
        }

        match ($sort) {
            'price_low' => $query->orderBy('from_price')->orderByDesc('reviewCount'),
            'price_high' => $query->orderByDesc('from_price')->orderByDesc('reviewCount'),
            'newest' => $query->orderByDesc('created_at'),
            'rating' => $query->orderByDesc('reviewRating')->orderByDesc('reviewCount'),
            default => $query->orderByDesc('reviewCount')->orderByDesc('reviewRating')->orderByDesc('created_at'),
        };
    }

    private function destinationSuggestions(string $term, int $limit): array
    {
        return $this->locationRepository->searchDestinations($term, $limit)
            ->map(fn(Location $location) => $this->cityPayload($location))
            ->values()
            ->all();
    }

    private function categorySuggestions(string $term, int $limit, string $currency, string $language, ?string $locationFilter = null, array $matchingCategoryIds = []): array
    {
        $matchingCategoryIds = collect($matchingCategoryIds)
            ->filter(fn($id) => is_numeric($id))
            ->map(fn($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $activeProductFilter = fn(Builder $product) => $product
            ->where('status', 'active')
            ->where('published', true);

        // When a location filter is provided, products must also be located in that city or country
        $scopedProductFilter = $locationFilter
            ? function (Builder $product) use ($activeProductFilter, $locationFilter) {
                $activeProductFilter($product);
                $product->whereHas('locations', fn(Builder $loc) => $loc->where('city', 'LIKE', '%' . $locationFilter . '%')->orWhere('country', 'LIKE', '%' . $locationFilter . '%'));
            }
            : $activeProductFilter;

        $query = $this->categoriesRepository->query()
            ->where('status', '1')
            ->where(function (Builder $query) {
                $query->where('show_in_search', 1)
                    ->orWhereNull('show_in_search');
            })
            ->withCount(['products as products_count' => $scopedProductFilter]);

        if ($term === '') {
            // Empty global search is browse state. Location pages are scoped to product/category location.
            if ($locationFilter !== null) {
                $query->where(function (Builder $query) use ($matchingCategoryIds, $locationFilter) {
                    if ($matchingCategoryIds !== []) {
                        $query->whereIn('id', $matchingCategoryIds)
                            ->orWhere(fn(Builder $locationQuery) => $this->applyCategoryLocationFields($locationQuery, $locationFilter));
                    } else {
                        $query->where(fn(Builder $locationQuery) => $this->applyCategoryLocationFields($locationQuery, $locationFilter));
                    }
                });
            }
        } else {
            // When searching, include categories that either match directly or belong to matching products.
            if ($locationFilter !== null) {
                $query->whereHas('products', $scopedProductFilter);
            }

            $like = '%' . $term . '%';

            $query->where(function (Builder $query) use ($like, $scopedProductFilter, $matchingCategoryIds, $term) {
                if ($matchingCategoryIds !== []) {
                    $query->whereIn('id', $matchingCategoryIds)
                        ->orWhere(fn(Builder $phrase) => $this->applyCategorySearchFields($phrase, $like));
                } else {
                    $query->where(fn(Builder $phrase) => $this->applyCategorySearchFields($phrase, $like));
                }

                $query->orWhereHas('products', function (Builder $product) use ($like, $scopedProductFilter, $term) {
                    $scopedProductFilter($product);

                    $product->where(function (Builder $productSearch) use ($like, $term) {
                        $productSearch->where(fn(Builder $phrase) => $this->applyProductSearchFields($phrase, $like, $term));
                    });
                });
            });
        }

        return $query
            ->orderBy('search_sort_order')
            ->orderByDesc('products_count')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn(Categories $category) => $this->collectionPayload($category, $currency, $language))
            ->values()
            ->all();
    }

    private function productCardRows(Collection $products, string $currency, string $language): array
    {
        return $products
            ->map(fn(Product $product) => $this->productCardPayload($product, $currency, $language))
            ->values()
            ->all();
    }

    private function productAutocompleteRows(Collection $products): array
    {
        return $products
            ->map(fn(Product $product) => $this->productAutocompletePayload($product))
            ->values()
            ->all();
    }

    private function collectionAutocompleteRows(array $collections): array
    {
        return collect($collections)
            ->map(fn(array $collection) => $this->collectionAutocompletePayload($collection))
            ->values()
            ->all();
    }

    private function matchingCategoryIds(Builder $productsQuery, int $limit = 100): array
    {
        return (clone $productsQuery)
            ->withoutEagerLoads()
            ->select('category_id')
            ->reorder()
            ->whereNotNull('category_id')
            ->distinct()
            ->limit($limit)
            ->pluck('category_id')
            ->map(fn($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function cityPayload(Location $location): array
    {
        $displayName = (string) ($location->city ?: $location->country);
        $countryDisplayName = $location->country ? (string) $location->country : null;
        $countryCode = $this->countryCode($location->countryCode, $countryDisplayName);

        return [
            'code' => $this->cityCode($location->city, $location->city_id, $countryCode),
            'displayName' => $displayName,
            'country' => [
                'code' => $countryCode,
                'displayName' => $countryDisplayName,
            ],
            'imageUrl' => $this->destinationImageUrl($location),
            'url' => $this->absoluteApiUrl('search/destination/' . trim((string) $this->destinationPathSlug($location), '/')),
        ];
    }

    private function productAutocompletePayload(Product $product): array
    {
        return [
            'id' => $product->id,
            'displayName' => $product->title,
            'city' => $product->location ? $this->cityReference($product->location) : null,
            'country' => $product->location ? $this->countryReference($product->location) : null,
            'imageUrl' => $this->primaryImageUrl($product),
        ];
    }

    private function collectionAutocompletePayload(array $collection): array
    {
        return [
            'id' => $collection['id'],
            'displayName' => $collection['displayName'],
            'city' => $collection['city'],
            'country' => $collection['country'],
            'imageUrl' => $collection['imageUrl'],
            'url' => $collection['url'],
            'productsCount' => $collection['productsCount'] ?? 0,
        ];
    }

    private function collectionPayload(Categories $category, string $currency, string $language): array
    {
        $stats = $this->categoryStats($category, $currency);
        $product = $this->categoryRepresentativeProduct($category);
        $location = $product?->location;
        $displayName = $this->categoryDisplayName($category);
        $imageUrl = $product ? $this->primaryImageUrl($product) : null;

        $cityRef = $location ? $this->cityReference($location) : null;
        $countryRef = $location ? $this->countryReference($location) : null;

        // Apply explicitly configured city (if any)
        if ($category->city_id) {
            $cityObj = $this->cityRepository->find($category->city_id);
            if ($cityObj) {
                $cityRef = [
                    'code' => $this->cityCode($cityObj->city_name, $cityObj->id, $cityObj->country_short_code),
                    'displayName' => $cityObj->city_name,
                ];
                $countryRef = [
                    'code' => $this->countryCode($cityObj->country_short_code, $cityObj->country_name),
                    'displayName' => $cityObj->country_name,
                ];
            }
        } elseif ($category->location_country && $category->location_country_code) {
            // Apply explicitly configured country (without specific city)
            $countryRef = [
                'code' => $this->countryCode($category->location_country_code, $category->location_country),
                'displayName' => $category->location_country,
            ];
            // If the collection is country-wide, it shouldn't be bound to a single city.
            $cityRef = null;
        }

        return [
            'id' => $category->id,
            'displayName' => $displayName,
            'alternateDisplayName' => $displayName,
            'city' => $cityRef,
            'country' => $countryRef,
            'primaryCity' => $cityRef,
            'language' => $language,
            'imageUrl' => $imageUrl,
            'medias' => $imageUrl ? [$this->mediaPayload($imageUrl, $displayName, optional($product?->created_at)->toDateString())] : [],
            'listingPrice' => [
                'originalListingPrice' => $stats['originalListingPrice'],
                'listingPrice' => $stats['listingPrice'],
                'currency' => $stats['currency'],
                'tourGroupId' => $product?->id ?? $category->id,
                'bestDiscount' => $this->discountPercent($stats['originalListingPrice'], $stats['listingPrice']),
            ],
            'ratings' => [
                'count' => $stats['ratingsCount'],
                'value' => $stats['ratingsValue'],
            ],
            'url' => $this->absoluteApiUrl('search/collection/' . trim((string) $category->slug, '/')),
            'personas' => [],
            'content' => [
                'subtext' => $category->search_subtitle ?: $this->productsText($stats['productsCount']),
            ],
            'durationInMinutes' => null,
            'subcategory' => null,
            'category' => new CategoryResource($category),
            'lastMonthBookingCount' => null,
            'guestCount' => null,
            'productsCount' => $stats['productsCount'],
            'apiQuery' => [
                'search' => $displayName,
                'category_slug' => $category->slug,
            ],
        ];
    }

    private function productCardPayload(Product $product, string $currency, string $language): array
    {
        return $this->lightweightProductCardPayload($product, $currency, $language);
    }

    private function destinationProductCardRows(Collection $products, string $currency, string $language): array
    {
        return $products
            ->map(fn(Product $product) => $this->lightweightProductCardPayload($product, $currency, $language))
            ->values()
            ->all();
    }

    private function lightweightProductCardPayload(Product $product, string $currency, string $language): array
    {
        $duration = $this->durationDisplay($product);
        $image = $this->primaryImageUrl($product);

        $displayPrices = $product->getDisplayPrices();
        $fromPrice = $this->normalizeNumeric($displayPrices['from_price'] ?? $product->from_price);
        $originalPrice = $this->normalizeNumeric($displayPrices['original_price'] ?? $product->original_price);

        return [
            'id' => $product->id,
            'title' => $product->title,
            'image' => $image,
            'rating' => round((float) ($this->normalizeNumeric($product->reviewRating) ?? 0), 1),
            'reviewCount' => (int) ($product->reviewCount ?? 0),
            'duration' => $duration,
            'city' => $product->location ? (string) ($product->location->city ?: $product->location->country) : null,
            'price' => $fromPrice,
            'from_price' => $fromPrice,
            'original_price' => $originalPrice,
            'fromPrice' => $fromPrice,
            'originalPrice' => $originalPrice,
            'default_currency' => strtoupper((string) ($product->default_currency ?: $currency ?: 'EUR')),
            'currency' => strtoupper((string) ($product->default_currency ?: $currency ?: 'EUR')),
            'collection' => $product->category ? $this->categoryDisplayName($product->category) : null,
            'is_wishlisted' => false,
        ];
    }

    private function destinationSlugFromLocation(Location $location, string $fallbackSlug): string
    {
        $slug = $this->destinationPathSlug($location);

        return $slug ? trim($slug, '/') : trim(urldecode($fallbackSlug), '/');
    }

    private function apiBaseUrl(): string
    {
        return rtrim((string) config('app.url', url('/')), '/') . '/api/v1';
    }

    private function absoluteApiUrl(string $path): string
    {
        return $this->apiBaseUrl() . '/' . ltrim($path, '/');
    }

    private function destinationCollectionUrl(string $destinationSlug, string $collectionSlug): string
    {
        return $this->absoluteApiUrl('search/destination/' . trim($destinationSlug, '/') . '?collection=' . trim($collectionSlug, '/'));
    }

    private function destinationProductsQuery(Location $location, bool $isCity): Builder
    {
        return $this->productRepository->getDestinationProductsQuery(
            $isCity ? $location->city : $location->country,
            $isCity
        );
    }

    private function destinationHeroImageUrl(Builder $baseProducts): ?string
    {
        $product = (clone $baseProducts)
            ->reorder()
            ->select(['products.id', 'products.thumbnail'])
            ->with(['productImages' => fn($query) => $query->select(['id', 'product_id', 'preview_url', 'large_url', 'original_url', 'is_primary', 'sort_order'])->active()->ordered()])
            ->orderByDesc('reviewCount')
            ->orderByDesc('reviewRating')
            ->first();

        return $product ? $this->primaryImageUrl($product) : null;
    }

    private function listingPricePayload(Product $product, string $fallbackCurrency): array
    {
        $displayPrices = $product->getDisplayPrices();
        $finalPrice = $this->normalizeNumeric($displayPrices['from_price'] ?? $product->from_price);
        $originalPrice = $this->normalizeNumeric($displayPrices['original_price'] ?? $product->original_price);
        $currency = strtoupper((string) ($product->default_currency ?: $fallbackCurrency ?: 'EUR'));

        return [
            'currencyCode' => $currency,
            'originalPrice' => $originalPrice,
            'original_price' => $originalPrice,
            'finalPrice' => $finalPrice,
            'fromPrice' => $finalPrice,
            'from_price' => $finalPrice,
            'price' => $finalPrice,
            'minimumPayablePrice' => $finalPrice,
            'type' => 'PER_PERSON',
            'otherPricesExist' => (int) ($product->prices_count ?? 0) > 1
                || (int) ($product->options_count ?? 0) > 1,
            'bestDiscount' => ($originalPrice !== null && $finalPrice !== null && $originalPrice > $finalPrice)
                ? $this->discountPercent($originalPrice, $finalPrice)
                : 0.0,
            'cashbackValue' => 0.0,
            'cashbackType' => 'PERCENTAGE',
            'tourId' => $product->id,
        ];
    }

    private function categoryStats(Categories $category, string $fallbackCurrency): array
    {
        // Use pre-calculated cache if available (populated by PrecalculateCategoryStats job).
        // Fallback to live query for first-run after deploy or cache expiry.
        $cached = Cache::get("category:stats:{$category->id}");

        $raw = $cached !== null
            ? (object) $cached
            : $this->productRepository->getCategoryStats($category->id, $fallbackCurrency);

        $listingPrice = $this->normalizeNumeric($raw->listing_price ?? null);
        $originalListingPrice = $this->normalizeNumeric($raw->original_listing_price ?? null) ?? $listingPrice;

        if ($listingPrice !== null && $originalListingPrice !== null && $originalListingPrice < $listingPrice) {
            $originalListingPrice = $listingPrice;
        }

        return [
            'productsCount' => (int) ($category->products_count ?? $raw->experiences_count ?? 0),
            'listingPrice' => $listingPrice,
            'originalListingPrice' => $originalListingPrice,
            'currency' => strtoupper((string) ($raw->detected_currency ?: $fallbackCurrency ?: 'EUR')),
            'ratingsCount' => (int) ($raw->ratings_count ?? 0),
            'ratingsValue' => round((float) ($this->normalizeNumeric($raw->ratings_value) ?? 0), 1),
        ];
    }

    private function categoryRepresentativeProduct(Categories $category): ?Product
    {
        return Cache::remember("category:representative:{$category->id}", now()->addMinutes(30), function () use ($category) {
            return $this->productRepository->getRepresentativeProduct($category->id);
        });
    }

    private function destinationImageUrl(Location $location): ?string
    {
        $product = $this->productRepository->getPublishedBaseQuery()
            ->whereHas('locations', function (Builder $query) use ($location) {
                if ($location->city) {
                    $query->where('city', $location->city);
                }
                if ($location->country) {
                    $query->where('country', $location->country);
                }
            })
            ->with(['productImages' => fn($query) => $query->select(['id', 'product_id', 'preview_url', 'large_url', 'original_url', 'is_primary', 'sort_order'])->active()->ordered()])
            ->orderByDesc('reviewCount')
            ->orderByDesc('reviewRating')
            ->first();

        return $product ? $this->primaryImageUrl($product) : null;
    }

    private function productMediaPayloads(Product $product): array
    {
        $images = $product->relationLoaded('productImages') ? $product->productImages : collect();

        $payloads = $images
            ->map(function ($image) {
                $url = $image->large_url ?? $image->original_url ?? $image->preview_url;

                return $url ? $this->mediaPayload($url, $image->alt_text, optional($image->created_at)->toDateString()) : null;
            })
            ->filter()
            ->values();

        if ($payloads->isEmpty() && $product->thumbnail) {
            $payloads->push($this->mediaPayload($product->thumbnail, $product->title, optional($product->created_at)->toDateString()));
        }

        return $payloads->all();
    }

    private function mediaPayload(string $url, ?string $altText = null, ?string $uploadDate = null): array
    {
        return [
            'url' => $url,
            'type' => 'IMAGE',
            'metadata' => [
                'altText' => $altText,
                'videoDuration' => null,
                'uploadDate' => $uploadDate,
            ],
        ];
    }

    private function primaryImageUrl(Product $product): ?string
    {
        $image = $product->relationLoaded('productImages') ? $product->productImages->first() : null;

        return $product->thumbnail
            ?? $image?->preview_url
            ?? $image?->large_url
            ?? $image?->original_url;
    }

    private function descriptorPayloads(Product $product): array
    {
        $descriptors = [];
        $duration = $this->durationDisplay($product);

        if ($duration) {
            $descriptors[] = $this->descriptor(
                'DURATION',
                'Duration',
                'Duration',
                $duration,
                'https://cdn-imgix.headout.com/assets/svg/descriptors/clock.svg'
            );
        }

        if ((bool) $product->is_cancellable) {
            $descriptors[] = $this->descriptor(
                'FREE_CANCELLATION',
                'Free Cancellation',
                'Free cancellation',
                'Free cancellation available for this experience',
                'https://cdn-imgix.headout.com/assets/svg/descriptors/cancellation.svg'
            );
        }

        if ((bool) $product->is_instant_confirmation) {
            $descriptors[] = $this->descriptor(
                'INSTANT_CONFIRMATION',
                'Instant Confirmation',
                'Instant confirmation',
                'Receive confirmation immediately after booking',
                'https://cdn-imgix.headout.com/assets/svg/descriptors/instant-confirmation.svg'
            );
        }

        if ($product->guide_type) {
            $descriptors[] = $this->descriptor(
                'GUIDED_TOUR',
                'Guided Tour',
                'Guided tour',
                (string) $product->guide_type,
                'https://cdn-imgix.headout.com/assets/svg/descriptors/tour-guide.svg',
                'INCLUSION_BASED'
            );
        }

        if ((bool) $product->privateActivity || in_array(strtolower((string) $product->private_tour), ['1', 'yes', 'true'], true)) {
            $descriptors[] = $this->descriptor(
                'PRIVATE_TOUR',
                'Private Tour',
                'Private tour',
                'Private experience available',
                'https://cdn-imgix.headout.com/assets/svg/descriptors/private-tour.svg',
                'INCLUSION_BASED'
            );
        }

        return $descriptors;
    }

    private function descriptor(
        string $code,
        string $name,
        string $displayName,
        ?string $description,
        string $iconUrl,
        string $type = 'STANDARD'
    ): array {
        return [
            'code' => $code,
            'name' => $name,
            'displayName' => $displayName,
            'iconUrl' => $iconUrl,
            'description' => $description,
            'type' => $type,
        ];
    }

    private function durationDisplay(Product $product): ?string
    {
        if ($product->durationText) {
            return (string) $product->durationText;
        }

        if ($product->duration) {
            return (string) $product->duration;
        }

        if ($product->durationMinutes) {
            return $product->durationMinutes . ' mins';
        }

        if ($product->durationHours) {
            return $product->durationHours . ' hrs';
        }

        if ($product->durationDays) {
            return $product->durationDays . ' days';
        }

        if ($product->durationWeeks) {
            return $product->durationWeeks . ' weeks';
        }

        return null;
    }

    private function durationInMilliseconds(Product $product): ?int
    {
        $minutes = $product->durationMinutes
            ?? ($product->durationHours ? $product->durationHours * 60 : null)
            ?? ($product->durationDays ? $product->durationDays * 1440 : null)
            ?? ($product->durationWeeks ? $product->durationWeeks * 10080 : null);

        return $minutes ? (int) $minutes * 60000 : null;
    }

    private function cardsPayload(array $items, LengthAwarePaginator $pagination): array
    {
        return [
            'items' => $items,
            'nextUrl' => $pagination->nextPageUrl(),
            'total' => $pagination->total(),
            'nextOffset' => $pagination->hasMorePages()
                ? $pagination->currentPage() * $pagination->perPage()
                : null,
        ];
    }

    private function categoryDisplayName(Categories $category): string
    {
        $searchLabel = trim((string) $category->search_label);

        if ($searchLabel !== '') {
            return $searchLabel;
        }

        return $this->humanizedDisplayName($category->name);
    }

    private function humanizedDisplayName(?string $value): string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return '';
        }

        if (! str_contains($text, '_') && ! str_contains($text, '-') && $text !== mb_strtoupper($text)) {
            return $text;
        }

        $smallWords = ['and', 'or', 'of', 'to', 'from', 'by', 'with', 'in', 'on', 'for', 'the', 'a', 'an'];

        return collect(preg_split('/[\s_-]+/', $text) ?: [])
            ->map(fn(string $word) => mb_strtolower(trim($word)))
            ->filter()
            ->values()
            ->map(function (string $word, int $index) use ($smallWords) {
                if ($index > 0 && in_array($word, $smallWords, true)) {
                    return $word;
                }

                return mb_strtoupper(mb_substr($word, 0, 1)) . mb_substr($word, 1);
            })
            ->implode(' ');
    }

    private function productsText(int $count): string
    {
        return $count . ' ' . ($count === 1 ? 'product' : 'products');
    }

    private function searchActionPayload(string $term): ?array
    {
        if ($term === '') {
            return null;
        }

        return [
            'type' => 'ACTION',
            'displayName' => 'Search ' . $term,
            'query' => $term,
            'url' => $this->absoluteApiUrl('search?c=worldwide&q=' . rawurlencode($term)),
            'apiQuery' => [
                'query' => $term,
                'c' => 'worldwide',
            ],
        ];
    }

    private function cityReference(Location $location): array
    {
        return [
            'code' => $this->cityCode($location->city, $location->city_id, $location->countryCode),
            'displayName' => (string) ($location->city ?: $location->country),
        ];
    }

    private function countryReference(Location $location): array
    {
        return [
            'code' => $this->countryCode($location->countryCode, $location->country),
            'displayName' => $location->country,
        ];
    }

    private function cityCode(?string $city, ?string $cityId = null, ?string $countryCode = null): string
    {
        if ($cityId) {
            return strtoupper((string) $cityId);
        }

        if ($city) {
            return strtoupper(str_replace('-', '_', Str::slug($city)));
        }

        return strtoupper((string) ($countryCode ?: 'GLOBAL'));
    }

    private function countryCode(?string $countryCode, ?string $country): ?string
    {
        if ($countryCode) {
            return strtoupper($countryCode);
        }

        if (! $country) {
            return null;
        }

        return strtoupper(substr(Str::slug($country, ''), 0, 2));
    }

    private function destinationPathSlug(Location $location): ?string
    {
        $city = $this->cityForLocation($location);

        if ($city?->slug) {
            return $this->slugPath($city->slug);
        }

        if ($city?->country_slug) {
            return $this->slugPath($city->country_slug);
        }

        $country = $this->countryForLocation($location);

        return $this->slugPath($country?->country_slug);
    }

    private function cityForLocation(Location $location): ?City
    {
        if ($location->city) {
            $cityQuery = $this->cityRepository->query()
                ->where('city_name', $location->city);

            if ($location->countryCode) {
                $cityQuery->where('country_short_code', $location->countryCode);
            } elseif ($location->country) {
                $cityQuery->where('country_name', $location->country);
            }

            $city = $cityQuery->first();

            if ($city) {
                return $city;
            }
        }

        return null;
    }

    private function countryForLocation(Location $location): ?City
    {
        $query = $this->cityRepository->query()->whereNotNull('country_slug');

        if ($location->countryCode) {
            $query->where('country_short_code', $location->countryCode);
        } elseif ($location->country) {
            $query->where('country_name', $location->country);
        } else {
            return null;
        }

        return $query->first();
    }

    private function slugPath(?string $slug): ?string
    {
        $slug = trim((string) $slug);

        if ($slug === '') {
            return null;
        }

        return '/' . trim($slug, '/') . '/';
    }

    private function applyDestinationSearchFields(Builder $query, string $like): void
    {
        $query->where('city', 'LIKE', $like)
            ->orWhere('country', 'LIKE', $like)
            ->orWhere('countryCode', 'LIKE', $like)
            ->orWhere('address', 'LIKE', $like)
            ->orWhere('label', 'LIKE', $like);
    }

    private function applyCategorySearchFields(Builder $query, string $like): void
    {
        $query->where('name', 'LIKE', $like)
            ->orWhere('slug', 'LIKE', $like)
            ->orWhere('type', 'LIKE', $like)
            ->orWhere('search_label', 'LIKE', $like)
            ->orWhere('search_subtitle', 'LIKE', $like);
    }

    private function applyCategoryLocationFields(Builder $query, string $location): void
    {
        $like = '%' . $location . '%';

        $query->where('location_country', 'LIKE', $like)
            ->orWhere('location_country_code', 'LIKE', $like)
            ->orWhereHas('city', function (Builder $city) use ($like) {
                $city->where('city_name', 'LIKE', $like)
                    ->orWhere('country_name', 'LIKE', $like)
                    ->orWhere('country_short_code', 'LIKE', $like)
                    ->orWhere('slug', 'LIKE', $like)
                    ->orWhere('country_slug', 'LIKE', $like);
            });
    }

    private function searchTokens(string $term): array
    {
        $normalized = preg_replace('/[^\pL\pN]+/u', ' ', $term) ?: '';

        return collect(explode(' ', $normalized))
            ->map(fn(string $token) => trim($token))
            ->filter(fn(string $token) => mb_strlen($token) >= 2)
            ->unique(fn(string $token) => mb_strtolower($token))
            ->take(8)
            ->values()
            ->all();
    }

    private function searchApiIds(string $term): array
    {
        return collect(explode(',', $term))
            ->map(fn(string $id) => trim($id))
            ->filter(fn(string $id) => $id !== '')
            ->unique()
            ->take(25)
            ->values()
            ->all();
    }

    private function isProductApiIdListSearch(string $term, array $apiIds): bool
    {
        if (! str_contains($term, ',') || count($apiIds) < 2) {
            return false;
        }

        return collect($apiIds)->every(fn(string $id) => preg_match('/^[A-Za-z0-9._:-]+$/', $id) === 1)
            && collect($apiIds)->contains(fn(string $id) => preg_match('/[\d._:-]/', $id) === 1);
    }

    private function normalizeNumeric(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function discountPercent(?float $originalPrice, ?float $finalPrice): float
    {
        if ($originalPrice === null || $finalPrice === null || $originalPrice <= 0 || $originalPrice <= $finalPrice) {
            return 0.0;
        }

        return round((($originalPrice - $finalPrice) / $originalPrice) * 100, 2);
    }

    private function paginationPayload(LengthAwarePaginator $pagination): array
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
     * New endpoint: products for a destination filtered by collection.
     * GET /api/v1/search/destination/{slug}/collections/{collectionSlug}
     */
    public function destinationCollectionPage(Request $request, string $slug, string $collectionSlug)
    {
        $context = $this->normalizeSearchRequest($request, 24, 100);

        // Resolve destination (resolveDestination handles urldecode internally)
        $resolved = $this->resolveDestination($slug);
        if (!$resolved['success']) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Destination not found.',
            ], 404);
        }

        $location = $resolved['location'];
        $isCity = $resolved['isCity'];
        $destinationName = $resolved['destinationName'];
        $destinationSlug = $this->destinationSlugFromLocation($location, $slug);

        // Find the collection (category) — only needed columns
        $collection = $this->categoriesRepository->findActiveBySlug($collectionSlug);

        if (!$collection) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Collection not found.',
            ], 404);
        }

        // Products filtered by destination AND collection
        $productsQuery = $this->destinationProductsQuery($location, $isCity)
            ->where('category_id', $collection->id);

        $this->applySorting($productsQuery, $context['sort'], '');

        $products = $productsQuery
            ->paginate($context['limit'])
            ->appends($request->except('tab'));

        $productCards = $this->destinationProductCardRows($products->getCollection(), $context['currency'], $context['language']);
        $productCardsPayload = $this->cardsPayload($productCards, $products);

        $destination = $this->buildDestinationObject($location, $isCity, $slug);
        $collectionsPayload = $this->getDestinationCollections($location, $isCity, $destinationName, $destinationSlug, $collectionSlug);
        $imageUrl = $this->destinationHeroImageUrl($this->destinationProductsQuery($location, $isCity));

        $payload = [
            'success' => true,
            'status' => 200,
            'message' => 'Collection retrieved successfully.',
            'data' => [
                'query' => $slug,
                'hero' => [
                    'title' => "Discover the best things to do in " . ucwords($destinationName),
                    'subtitle' => "A curated collection of the " . ($isCity ? 'City' : 'Country') . "'s top-rated tours, iconic attractions, and unmissable things to do.",
                    'image' => $imageUrl,
                    'language' => $context['language'],
                    'currency' => $context['currency'],
                    'destination' => [
                        'flag' => $destination['flag'],
                        'city' => $destination['city'],
                        'country' => $destination['country'],
                    ],
                ],
                'sections' => [
                    [
                        'type' => 'COLLECTIONS',
                        'title' => 'Collections',
                        'selectedCollection' => $collectionsPayload['selectedCollection'],
                        'items' => $collectionsPayload['items'],
                    ],
                    [
                        'type' => 'PRODUCTS',
                        'title' => $this->categoryDisplayName($collection),
                        'items' => $productCardsPayload['items'],
                    ],
                ],
                'collection' => [
                    'id' => $collection->id,
                    'slug' => $collection->slug,
                    'displayName' => $this->categoryDisplayName($collection),
                    'active' => true,
                ],
            ],
            'pagination' => $this->paginationPayload($products),
        ];

        $this->appendWishlistStatus($payload);

        return response()->json($payload);
    }

    // ─── Destination Page Helpers ───────────────────────────────────────

    /**
     * Resolve a destination slug to a Location and determine city/country context.
     */
    private function resolveDestination(string $slug): array
    {
        $decodedSlug = urldecode($slug);

        $location = $this->locationRepository->findFirstByCityOrCountry($decodedSlug);

        $isCity = true;
        $destinationName = '';

        if ($location) {
            $destinationName = $location->city;
        } else {
            $location = $this->locationRepository->query()
                ->where(function(Builder $q) use ($decodedSlug) {
                    $q->where('country', 'LIKE', '%' . $decodedSlug . '%')
                      ->orWhere('country', 'LIKE', '%' . str_replace('-', ' ', $decodedSlug) . '%');
                })
                ->whereHas('product', fn($q) => $q->where('status', 'active')->where('published', true))
                ->first();

            if ($location) {
                $isCity = false;
                $destinationName = $location->country;
            }
        }

        if (!$location) {
            return [
                'success' => false,
                'message' => 'Destination not found.',
                'location' => null,
                'isCity' => true,
                'destinationName' => '',
            ];
        }

        return [
            'success' => true,
            'message' => 'Destination found.',
            'location' => $location,
            'isCity' => $isCity,
            'destinationName' => $destinationName,
        ];
    }

    /**
     * Build the destination object for the API response.
     */
    private function buildDestinationObject(Location $location, bool $isCity, string $slug): array
    {
        // Try to find the corresponding city record
        $city = null;
        if ($location->city) {
            $cityQuery = $this->cityRepository->query()
                ->where('city_name', $location->city);

            if ($location->countryCode) {
                $cityQuery->where('country_short_code', $location->countryCode);
            }

            $city = $cityQuery->first();
        }

        $cityId = $city?->id ?? $location->city_id;
        $countryCode = strtoupper((string) ($location->countryCode ?: $city?->country_short_code ?: ''));
        $country = $location->country ?: $city?->country_name ?? '';

        return [
            'id' => $cityId ? (int) $cityId : null,
            'slug' => $slug,
            'city' => $isCity ? ($location->city ?: '') : ($city?->city_name ?? $location->city ?? ''),
            'country' => $country,
            'countryCode' => $countryCode,
            'flag' => $this->getFlagUrl($countryCode),
        ];
    }

    /**
     * Generate a flag image URL from a country code.
     */
    private function getFlagUrl(?string $countryCode): ?string
    {
        $code = strtoupper(trim((string) $countryCode));
        $code = preg_replace('/[^A-Z]/', '', $code) ?: '';

        if ($code === '') {
            return null;
        }

        if (strlen($code) !== 2) {
            $code = substr($code, 0, 2);
        }

        return 'https://flagcdn.com/' . strtolower($code) . '.svg';
    }

    /**
     * Get collections scoped to a destination with product counts.
     */
    private function getDestinationCollections(Location $location, bool $isCity, string $destinationName, string $destinationSlug, ?string $selectedCollectionSlug = null): array
    {
        $destinationScope = function ($query) use ($location, $isCity) {
            $query->where('status', 'active')
                ->where('published', true)
                ->whereHas('locations', function ($loc) use ($location, $isCity) {
                    if ($isCity) {
                        $loc->where('city', 'LIKE', '%' . $location->city . '%');
                    } else {
                        $loc->where('country', 'LIKE', '%' . $location->country . '%');
                    }
                });
        };

        $collections = $this->categoriesRepository->query()
            ->select(['id', 'slug', 'name', 'search_label', 'search_sort_order', 'search_icon as icon', 'status', 'show_in_search', 'city_id', 'location_country'])
            ->where('status', '1')
            ->where(function ($q) {
                $q->where('show_in_search', 1)
                  ->orWhereNull('show_in_search');
            })
            ->where(function ($q) use ($destinationScope, $location, $isCity) {
                $q->whereHas('products', $destinationScope)
                  ->orWhere(function ($qExplicit) use ($location, $isCity) {
                      if ($isCity) {
                          $qExplicit->whereHas('city', function ($qCity) use ($location) {
                              $qCity->where('city_name', 'LIKE', '%' . $location->city . '%');
                          });
                      } else {
                          $qExplicit->where('location_country', 'LIKE', '%' . $location->country . '%');
                      }
                  });
            })
            ->withCount(['products as products_count' => function ($q) {
                // Count all active, published products for this collection 
                // since if it's returned here, it belongs to the destination context.
                $q->where('status', 'active')->where('published', true);
            }])
            ->orderBy('search_sort_order')
            ->orderByDesc('products_count')
            ->orderBy('name')
            ->get();

        $defaultCollectionSlug = 'best-activities';
        $selectedSlug = $selectedCollectionSlug ?: ($collections->first()?->slug ?: $defaultCollectionSlug);

        $items = $collections->map(function ($category) use ($selectedSlug, $destinationSlug) {
            return [
                'id' => $category->id,
                'name' => $this->categoryDisplayName($category),
                'slug' => $category->slug,
                'active' => $category->slug === $selectedSlug,
                'icon' => $category->icon ?? null,
                'productCount' => (int) $category->products_count,
                'url' => $this->destinationCollectionUrl($destinationSlug, $category->slug),
            ];
        })->values()->all();

        $selectedItem = collect($items)->firstWhere('active');

        return [
            'selectedCollection' => $selectedItem,
            'items' => $items,
        ];
    }
}
