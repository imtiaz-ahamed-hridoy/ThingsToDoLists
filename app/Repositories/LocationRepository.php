<?php

namespace App\Repositories;

use App\Models\Location;
use App\Repositories\Contracts\LocationRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class LocationRepository extends BaseRepository implements LocationRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new Location();
    }

    public function findByCity(string $city): ?Location
    {
        return $this->model->newQuery()
            ->where('city', $city)
            ->whereHas('product', fn(Builder $product) => $product
                ->where('status', 'active')
                ->where('published', true))
            ->first();
    }

    public function findByCountry(string $country): ?Location
    {
        return $this->model->newQuery()
            ->where('country', $country)
            ->whereHas('product', fn(Builder $product) => $product
                ->where('status', 'active')
                ->where('published', true))
            ->first();
    }

    public function findFirstByCityOrCountry(string $term): ?Location
    {
        return $this->model->newQuery()
            ->where(function (Builder $q) use ($term) {
                $q->where('city', 'LIKE', '%' . $term . '%')
                  ->orWhere('city', 'LIKE', '%' . str_replace('-', ' ', $term) . '%')
                  ->orWhere('country', 'LIKE', '%' . $term . '%')
                  ->orWhere('country', 'LIKE', '%' . str_replace('-', ' ', $term) . '%');
            })
            ->whereHas('product', fn(Builder $product) => $product
                ->where('status', 'active')
                ->where('published', true))
            ->first();
    }

    public function getPopularDestinations(int $limit = 5): Collection
    {
        return $this->model->newQuery()
            ->select('city', 'country', 'countryCode', 'city_id')
            ->selectRaw('COUNT(DISTINCT product_id) as products_count')
            ->whereHas('product', fn(Builder $product) => $product
                ->where('status', 'active')
                ->where('published', true))
            ->whereNotNull('city')
            ->groupBy('city', 'country', 'countryCode', 'city_id')
            ->orderByDesc('products_count')
            ->limit($limit)
            ->get();
    }

    public function searchDestinations(string $term, int $limit = 5): Collection
    {
        $query = $this->model->newQuery()
            ->select('city', 'country', 'countryCode', 'city_id')
            ->selectRaw('COUNT(DISTINCT product_id) as products_count')
            ->whereHas('product', fn(Builder $product) => $product
                ->where('status', 'active')
                ->where('published', true))
            ->where(function (Builder $q) {
                $q->whereNotNull('city')
                  ->orWhereNotNull('country');
            });

        if ($term !== '') {
            $like = '%' . $term . '%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('city', 'LIKE', $like)
                  ->orWhere('country', 'LIKE', $like)
                  ->orWhere('countryCode', 'LIKE', $like)
                  ->orWhere('address', 'LIKE', $like)
                  ->orWhere('label', 'LIKE', $like);
            });
        }

        return $query
            ->groupBy('city', 'country', 'countryCode', 'city_id')
            ->orderByDesc('products_count')
            ->orderBy('city')
            ->limit($limit)
            ->get();
    }

    public function getDestinationImageProduct(string $city, ?string $country = null): ?Location
    {
        $query = $this->model->newQuery()
            ->whereHas('product', fn(Builder $product) => $product
                ->where('status', 'active')
                ->where('published', true));

        if ($city) {
            $query->where('city', $city);
        }
        if ($country) {
            $query->where('country', $country);
        }

        return $query->first();
    }

    public function getByProduct(int $productId): Collection
    {
        return $this->model->newQuery()
            ->where('product_id', $productId)
            ->orderBy('sort_order')
            ->get();
    }

    public function getPrimaryForProduct(int $productId): ?Location
    {
        return $this->model->newQuery()
            ->where('product_id', $productId)
            ->where('is_primary', true)
            ->first();
    }
}
