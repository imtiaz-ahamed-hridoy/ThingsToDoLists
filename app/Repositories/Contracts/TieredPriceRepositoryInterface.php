<?php

namespace App\Repositories\Contracts;

use App\Models\TieredPrice;
use Illuminate\Database\Eloquent\Collection;

interface TieredPriceRepositoryInterface extends RepositoryInterface
{
    public function getByCategory(int $pricingCategoryId): Collection;

    public function getTierForPax(int $pricingCategoryId, int $paxCount): ?TieredPrice;
}
