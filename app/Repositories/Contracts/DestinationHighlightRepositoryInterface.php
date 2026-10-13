<?php

namespace App\Repositories\Contracts;

use App\Models\DestinationHighlight;
use Illuminate\Database\Eloquent\Collection;

interface DestinationHighlightRepositoryInterface extends RepositoryInterface
{
    public function getActiveHighlightsByCityIds(array $cityIds): Collection;

    public function getActiveHighlightsByCountryIds(array $cityIds): Collection;

    public function getHighlightWithProducts(int $id): ?DestinationHighlight;

    public function getProductsForDestination(int $cityId, string $destinationType): Collection;

    public function syncProducts(DestinationHighlight $highlight, array $productIds): void;
}
