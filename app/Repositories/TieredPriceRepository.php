<?php

namespace App\Repositories;

use App\Models\TieredPrice;
use App\Repositories\Contracts\TieredPriceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class TieredPriceRepository extends BaseRepository implements TieredPriceRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new TieredPrice();
    }

    public function getByCategory(int $pricingCategoryId): Collection
    {
        return $this->model->newQuery()
            ->where('pricing_category_id', $pricingCategoryId)
            ->orderBy('min_pax')
            ->orderBy('sort_order')
            ->get();
    }

    public function getTierForPax(int $pricingCategoryId, int $paxCount): ?TieredPrice
    {
        return $this->model->newQuery()
            ->where('pricing_category_id', $pricingCategoryId)
            ->where('min_pax', '<=', $paxCount)
            ->where('max_pax', '>=', $paxCount)
            ->first();
    }
}
