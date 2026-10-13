<?php

namespace App\Repositories\Contracts;

use App\Models\Location;
use Illuminate\Database\Eloquent\Collection;

interface LocationRepositoryInterface extends RepositoryInterface
{
    public function findByCity(string $city): ?Location;

    public function findByCountry(string $country): ?Location;

    public function findFirstByCityOrCountry(string $term): ?Location;

    public function getPopularDestinations(int $limit = 5): Collection;

    public function searchDestinations(string $term, int $limit = 5): Collection;

    public function getDestinationImageProduct(string $city, ?string $country = null): ?Location;

    public function getByProduct(int $productId): Collection;

    public function getPrimaryForProduct(int $productId): ?Location;
}
