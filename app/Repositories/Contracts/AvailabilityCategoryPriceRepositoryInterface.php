<?php

namespace App\Repositories\Contracts;

use App\Models\AvailabilityCategoryPrice;
use Illuminate\Database\Eloquent\Collection;

interface AvailabilityCategoryPriceRepositoryInterface extends RepositoryInterface
{
    public function getByAvailability(int $availabilityId): Collection;

    public function getByCategoryCode(int $availabilityId, string $categoryCode): ?AvailabilityCategoryPrice;
}
