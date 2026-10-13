<?php

namespace App\Http\Controllers\API\Home;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\HomePageSectionRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Traits\AppliesProductRequestState;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class HomePageSectionsApiController extends Controller
{
    use ApiResponse;
    use AppliesProductRequestState;

    public function __construct(
        private HomePageSectionRepositoryInterface $homePageSectionRepository,
        private ProductRepositoryInterface $productRepository
    ) {}

    /**
     * Get all active homepage sections with their products.
     */
    public function index()
    {
        try {
            // Cache the entire formatted response for 20 minutes
            $cacheKey = 'homepage:sections:active';
            $sections = \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addMinutes(20), function () {
                $rawSections = $this->homePageSectionRepository->getActiveOrdered();
                
                // Collect all product IDs from all sections
                $allProductIds = $rawSections->flatMap(function ($section) {
                    return $section->product_ids ?? [];
                })->unique()->filter()->toArray();

                // Fetch all products at once (Eager load relationships)
                $allProducts = collect();
                if (!empty($allProductIds)) {
                    $allProducts = $this->productRepository->query()
                        ->with(['primaryImage', 'location'])
                        ->whereIn('id', $allProductIds)
                        ->get()
                        ->keyBy('id');
                }

                // Format the sections using the pre-fetched products
                return $rawSections->map(function ($section) use ($allProducts) {
                    return $this->formatSection($section, $allProducts);
                });
            });

            // Apply request-specific product fields after cache retrieval.
            $sections = $sections->map(function ($section) {
                return $this->applyProductRequestState($section);
            });

            return $this->success('Home page sections fetched successfully.', $sections);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Get a single section with its products.
     */
    public function show(string $id)
    {
        try {
            $cacheKey = 'homepage:section:' . $id;
            $formattedSection = \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addMinutes(20), function () use ($id) {
                $section = $this->homePageSectionRepository->query()->with('locations.city')->findOrFail((int) $id);

                if (! $section->status) {
                    throw new ModelNotFoundException();
                }

                $productIds = $section->product_ids ?? [];
                $allProducts = collect();
                if (!empty($productIds)) {
                    $allProducts = $this->productRepository->query()
                        ->with(['primaryImage', 'location'])
                        ->whereIn('id', $productIds)
                        ->get()
                        ->keyBy('id');
                }

                return $this->formatSection($section, $allProducts);
            });

            // Apply request-specific product fields after cache retrieval.
            $formattedSection = $this->applyProductRequestState($formattedSection);

            return $this->success('Home page section fetched successfully.', $formattedSection);
        } catch (ModelNotFoundException $e) {
            return $this->error('Section not found', 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Format a section for the API response.
     * Handles both 'products' and 'locations' section types.
     */
    private function formatSection($section, $allProducts): array
    {
        $base = [
            'id'                => $section->id,
            'section_type'      => $section->section_type ?? 'products',
            'title'             => $section->title,
            'short_description' => $section->short_description,
            'image'             => $section->image,
            'sort_order'        => $section->sort_order,
        ];

        // ── Location-type section ──────────────────────────────────────────
        if (($section->section_type ?? 'products') === 'locations') {
            $locations = ($section->relationLoaded('locations') ? $section->locations : collect())
                ->map(function ($loc) {
                    $detail = [];

                    if ($loc->location_type === 'city') {
                        // city relation is eager-loaded as locations.city
                        $city = $loc->relationLoaded('city') ? $loc->city : null;
                        $detail = [
                            'name'         => $city?->city_name,
                            'country_name' => $city?->country_name,
                            'slug'         => $city?->slug,
                        ];
                    } else {
                        // country — location_id holds the country name string
                        $detail = [
                            'name'         => $loc->location_id,
                            'country_name' => $loc->location_id,
                            'slug'         => null,
                        ];
                    }

                    return [
                        'id'            => $loc->id,
                        'location_type' => $loc->location_type,
                        'location_id'   => $loc->location_id,
                        'image'         => $loc->image, // resolved by HasMediaUrls accessor
                        'name'          => $detail['name'],
                        'country_name'  => $detail['country_name'],
                        'slug'          => $detail['slug'],
                    ];
                })
                ->values();

            return array_merge($base, [
                'locations'      => $locations,
                'location_count' => $locations->count(),
            ]);
        }

        // ── Product-type section ───────────────────────────────────────────
        $productIds = $section->product_ids ?? [];
        $products   = collect();

        if (!empty($productIds)) {
            $products = collect($productIds)
                ->map(fn ($id) => $allProducts->get($id))
                ->filter()
                ->map(function ($product) {
                    $image = $product->relationLoaded('primaryImage') && $product->primaryImage
                        ? ($product->primaryImage->original_url ?? $product->primaryImage->large_url ?? null)
                        : null;

                    $displayPrices = $product->getDisplayPrices();
                    $fromPrice     = $displayPrices['from_price'] ?? $product->from_price;
                    $originalPrice = $displayPrices['original_price'] ?? $product->original_price;

                    return [
                        'id'               => $product->id,
                        'title'            => $product->title,
                        'slug'             => $product->slug,
                        'thumbnail'        => $product->thumbnail,
                        'image'            => $image,
                        'from_price'       => $fromPrice,
                        'original_price'   => $originalPrice,
                        'fromPrice'        => $fromPrice,
                        'originalPrice'    => $originalPrice,
                        'price'            => $fromPrice,
                        'default_currency' => $product->default_currency,
                        'currency'         => $product->default_currency,
                        'reviewRating'     => $product->reviewRating,
                        'reviewCount'      => $product->reviewCount,
                        'duration'         => $product->durationText ?? $product->duration,
                        'location'         => $product->location ? [
                            'city'    => $product->location->city,
                            'country' => $product->location->country,
                        ] : null,
                    ];
                })
                ->values();
        }

        return array_merge($base, [
            'products'      => $products,
            'product_count' => $products->count(),
        ]);
    }

}
