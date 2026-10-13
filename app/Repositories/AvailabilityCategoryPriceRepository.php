<?php

namespace App\Repositories;

use App\Models\AvailabilityCategoryPrice;
use App\Repositories\Contracts\AvailabilityCategoryPriceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class AvailabilityCategoryPriceRepository extends BaseRepository implements AvailabilityCategoryPriceRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new AvailabilityCategoryPrice();
    }

    public function getByAvailability(int $availabilityId): Collection
    {
        return $this->model->newQuery()
            ->where('availability_id', $availabilityId)
            ->orderBy('category_code')
            ->get();
    }

    public function getByCategoryCode(int $availabilityId, string $categoryCode): ?AvailabilityCategoryPrice
    {
        return $this->model->newQuery()
            ->where('availability_id', $availabilityId)
            ->where('category_code', strtoupper($categoryCode))
            ->first();
    }
}
