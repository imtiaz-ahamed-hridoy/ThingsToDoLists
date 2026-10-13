<?php

namespace App\Repositories\Contracts;

use App\Models\City;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface CityRepositoryInterface extends RepositoryInterface
{
    public function findBySlug(string $slug): ?City;

    public function findByShortCode(string $shortCode): ?City;

    public function getActive(): Collection;

    public function search(string $term, int $perPage = 15): LengthAwarePaginator;

    public function getByCountry(string $countryName): Collection;

    public function getForDropdown(): Collection;

    public function findByCityNameLike(string $name): ?City;

    public function findByCountrySlug(string $slug): ?City;

    public function findByCountryNameLike(string $name): ?City;

    /**
     * Get all city IDs matching a given city name.
     *
     * @return array<int>
     */
    public function pluckIdsByCityName(string $cityName): array;

    /**
     * Get all city IDs matching a given country name.
     *
     * @return array<int>
     */
    public function pluckIdsByCountryName(string $countryName): array;
}
