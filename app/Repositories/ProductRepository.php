<?php

namespace App\Repositories;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new Product();
    }

    public function findWithRelations(int $id, array $relations = []): ?Product
    {
        $query = $this->model->newQuery();

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->find($id);
    }

    public function findBySlug(string $slug): ?Product
    {
        return $this->model->newQuery()
            ->where('slug', $slug)
            ->first();
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $query = $this->model->newQuery()
            ->where('slug', $slug);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function getPublished(array $columns = ['*']): Collection
    {
        return $this->model->newQuery()
            ->where('status', 'active')
            ->get($columns);
    }

    public function getAvailable(array $columns = ['*']): Collection
    {
        return $this->model->newQuery()
            ->where('status', 'active')
            ->where('published', true)
            ->whereHas('availabilities', fn($q) => $q
                ->where('is_active', true)
                ->where('sold_out', false)
                ->where('available_date', '>=', now()->toDateString())
            )
            ->get($columns);
    }

    public function getDataTableQuery()
    {
        return Product::query()
            ->with(['location:id,product_id,city'])
            ->select([
                'id',
                'title',
                'product_api_id',
                'thumbnail',
                'bookingType',
                'provider',
                'platform',
                'status',
                'is_top',
                'from_price',
                'original_price',
                'default_currency',
            ]);
    }

    public function bulkDelete(array $ids): int
    {
        return Product::whereIn('id', $ids)->delete();
    }

    public function getByIds(array $ids, array $columns = ['*']): Collection
    {
        return $this->model->newQuery()
            ->whereIn('id', $ids)
            ->get($columns);
    }

    public function search(string $term, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->where('status', 'active')
            ->where('published', true)
            ->where(function ($q) use ($term) {
                $q->where('title', 'LIKE', "%{$term}%")
                  ->orWhere('short_introduction', 'LIKE', "%{$term}%")
                  ->orWhereHas('location', fn($l) => $l->where('city', 'LIKE', "%{$term}%"));
            })
            ->paginate($perPage);
    }

    public function getPublishedProducts(array $filters = [], string $sort = 'popular', int $perPage = 20): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        // Step 1: Count total matching products (narrow query, no joins)
        $countQuery = $this->model->newQuery()
            ->where('published', true)
            ->where('status', 'active');
        $this->applyProductFilters($countQuery, $filters);
        $total = $countQuery->count();

        // Step 2: Get paginated, ordered IDs (narrow index-only scan, no joins)
        $idQuery = $this->model->newQuery()
            ->where('published', true)
            ->where('status', 'active');
        $this->applyProductFilters($idQuery, $filters);
        $this->applyProductSort($idQuery, $sort);
        $ids = $idQuery->select('id')
            ->forPage($page, $perPage)
            ->pluck('id');

        // Step 3: Fetch full models with relations ONLY for the paginated IDs
        //         (uses PRIMARY KEY lookups for the data + eager loading for relations)
        $models = collect();
        if ($ids->isNotEmpty()) {
            $models = $this->getPublishedBaseQuery([
                'location',
                'vendor',
                'category',
                'options.pricingCategories',
                'prices',
                'productImages' => fn($q) => $q->active()->ordered(),
            ])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

            // Re-sort models to match the exact order from step 2
            $models = $ids->map(fn($id) => $models->get($id))
                ->filter()
                ->values();
        }

        return new LengthAwarePaginator(
            $models,
            $total,
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );
    }

    public function getPublishedProductDetail(?int $id = null, ?string $productApiId = null, ?string $platform = null): ?Product
    {
        $query = $this->getPublishedBaseQuery([
            'options.pricingCategories.tieredPrices',
            'locations',
            'location',
            'prices',
            'cancellationPolicies',
            'productImages' => fn($q) => $q->active()->ordered(),
            'productVideos' => fn($q) => $q->active()->ordered(),
            'keywords',
            'vendor',
            'category',
            'reviews' => fn($q) => $q->where('status', 'Active')->with('users')->latest(),
        ]);

        if ($id !== null) {
            $query->where('id', $id);
        } elseif ($productApiId && $platform) {
            $query->where('product_api_id', $productApiId)
                  ->where('platform', strtolower($platform));
        } else {
            return null;
        }

        return $query->first();
    }

    public function getCategoryStats(int $categoryId, string $fallbackCurrency = 'EUR'): ?Model
    {
        return Product::query()
            ->where('category_id', $categoryId)
            ->where('status', 'active')
            ->where('published', true)
            ->selectRaw('COUNT(*) as experiences_count')
            ->selectRaw('MIN(from_price) as listing_price')
            ->selectRaw('MIN(COALESCE(original_price, from_price)) as original_listing_price')
            ->selectRaw('SUM(COALESCE(reviewCount, 0)) as ratings_count')
            ->selectRaw('
                CASE
                    WHEN SUM(COALESCE(reviewCount, 0)) > 0
                    THEN SUM(COALESCE(reviewRating, 0) * COALESCE(reviewCount, 0)) / SUM(COALESCE(reviewCount, 0))
                    ELSE AVG(reviewRating)
                END as ratings_value
            ')
            ->selectRaw('MIN(NULLIF(default_currency, \'\')) as detected_currency')
            ->first();
    }

    public function getRepresentativeProduct(int $categoryId, array $columns = ['*']): ?Product
    {
        return Product::query()
            ->select(['id', 'slug', 'title', 'thumbnail', 'from_price', 'original_price', 'default_currency', 'reviewRating', 'reviewCount', 'created_at'])
            ->where('category_id', $categoryId)
            ->where('status', 'active')
            ->where('published', true)
            ->with([
                'location',
                'productImages' => fn($query) => $query->active()->ordered(),
            ])
            ->orderByDesc('reviewCount')
            ->orderByDesc('reviewRating')
            ->orderByDesc('created_at')
            ->first();
    }

    public function getDestinationImageProduct(array $columns = ['id', 'thumbnail']): ?Product
    {
        return Product::query()
            ->select($columns)
            ->where('status', 'active')
            ->where('published', true)
            ->with(['productImages' => fn($query) => $query->select(['id', 'product_id', 'preview_url', 'large_url', 'original_url', 'is_primary', 'sort_order'])->active()->ordered()])
            ->orderByDesc('reviewCount')
            ->orderByDesc('reviewRating')
            ->first();
    }

    public function getDestinationProductsQuery(string $location, bool $isCity): Builder
    {
        return Product::query()
            ->where('status', 'active')
            ->where('published', true)
            ->select([
                'products.id',
                'products.category_id',
                'products.slug',
                'products.title',
                'products.thumbnail',
                'products.from_price',
                'products.original_price',
                'products.default_currency',
                'products.reviewRating',
                'products.reviewCount',
                'products.durationText',
                'products.duration',
                'products.durationMinutes',
                'products.durationHours',
                'products.durationDays',
                'products.durationWeeks',
                'products.created_at',
            ])
            ->with([
                'location',
                'category:id,name,search_label,slug,search_icon as icon',
                'productImages' => fn($query) => $query->select(['id', 'product_id', 'preview_url', 'large_url', 'original_url', 'is_primary', 'sort_order'])->active()->ordered(),
            ])
            ->whereHas('locations', function (Builder $locationQuery) use ($location, $isCity) {
                if ($isCity) {
                    $locationQuery->where('city', 'LIKE', '%' . $location . '%');
                } else {
                    $locationQuery->where('country', 'LIKE', '%' . $location . '%');
                }
            });
    }

    public function getPublishedBaseQuery(array $relations = []): Builder
    {
        $query = $this->model->newQuery()
            ->where('published', true)
            ->where('status', 'active');

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query;
    }

    private function applyProductFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (!empty($filters['provider'])) {
            $query->where('provider', strtolower((string) $filters['provider']));
        }

        if (isset($filters['price_min'])) {
            $query->where('from_price', '>=', (float) $filters['price_min']);
        }

        if (isset($filters['price_max'])) {
            $query->where('from_price', '<=', (float) $filters['price_max']);
        }

        if (!empty($filters['city'])) {
            $city = (string) $filters['city'];
            $query->whereHas('locations', fn($q) => $q->where('city', 'like', '%' . $city . '%'));
        }

        if (!empty($filters['country'])) {
            $country = (string) $filters['country'];
            $query->whereHas('locations', fn($q) => $q->where('country', 'like', '%' . $country . '%'));
        }
    }

    private function applyProductSort(Builder $query, string $sort): void
    {
        match ($sort) {
            'price_low' => $query->orderBy('from_price')->orderByDesc('reviewCount'),
            'price_high' => $query->orderByDesc('from_price')->orderByDesc('reviewCount'),
            'newest' => $query->orderByDesc('created_at'),
            'rating' => $query->orderByDesc('reviewRating')->orderByDesc('reviewCount'),
            default => $query->orderByDesc('reviewCount')->orderByDesc('reviewRating'), // popular
        };
    }
}
