<?php

namespace App\Repositories;

use App\Models\City;
use App\Repositories\Contracts\CityRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class CityRepository extends BaseRepository implements CityRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new City();
    }

    public function findBySlug(string $slug): ?City
    {
        return $this->model->newQuery()->where('slug', $slug)->first();
    }

    public function findByShortCode(string $shortCode): ?City
    {
        return $this->model->newQuery()->where('city_short_code', $shortCode)->first();
    }

    public function getActive(): Collection
    {
        return $this->model->newQuery()
            ->where('item_status', 1)
            ->orderBy('city_name')
            ->get();
    }

    public function search(string $term, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->where(function ($q) use ($term) {
                $q->where('city_name', 'LIKE', "%{$term}%")
                  ->orWhere('country_name', 'LIKE', "%{$term}%");
            })
            ->orderBy('city_name')
            ->paginate($perPage);
    }

    public function getByCountry(string $countryName): Collection
    {
        return $this->model->newQuery()
            ->where('country_name', $countryName)
            ->orderBy('city_name')
            ->get();
    }

    public function getForDropdown(): Collection
    {
        return $this->model->newQuery()
            ->orderBy('city_name')
            ->get(['id', 'city_name', 'country_name', 'slug']);
    }

    public function findByCityNameLike(string $name): ?City
    {
        return $this->model->newQuery()
            ->where('city_name', 'LIKE', '%' . $name . '%')
            ->first();
    }

    public function findByCountrySlug(string $slug): ?City
    {
        return $this->model->newQuery()
            ->where('country_slug', $slug)
            ->first();
    }

    public function findByCountryNameLike(string $name): ?City
    {
        return $this->model->newQuery()
            ->where('country_name', 'LIKE', '%' . $name . '%')
            ->first();
    }

    public function pluckIdsByCityName(string $cityName): array
    {
        return $this->model->newQuery()
            ->where('city_name', $cityName)
            ->pluck('id')
            ->toArray();
    }

    public function pluckIdsByCountryName(string $countryName): array
    {
        return $this->model->newQuery()
            ->where('country_name', $countryName)
            ->pluck('id')
            ->toArray();
    }
}
