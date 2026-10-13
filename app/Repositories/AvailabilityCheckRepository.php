<?php

namespace App\Repositories;

use App\Models\AvailabilityCheck;
use App\Repositories\Contracts\AvailabilityCheckRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class AvailabilityCheckRepository extends BaseRepository implements AvailabilityCheckRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new AvailabilityCheck();
    }

    public function getByProduct(int $productId): Collection
    {
        return $this->model->newQuery()
            ->where('product_id', $productId)
            ->orderBy('available_date')
            ->orderBy('start_time')
            ->get();
    }

    public function getByOption(int $productOptionId): Collection
    {
        return $this->model->newQuery()
            ->where('product_option_id', $productOptionId)
            ->orderBy('available_date')
            ->orderBy('start_time')
            ->get();
    }

    public function getAvailable(int $productOptionId): Collection
    {
        return $this->model->newQuery()
            ->where('product_option_id', $productOptionId)
            ->where('is_active', true)
            ->where('sold_out', false)
            ->where('is_closed', false)
            ->orderBy('available_date')
            ->orderBy('start_time')
            ->get();
    }

    public function getByDate(int $productOptionId, string $date): ?AvailabilityCheck
    {
        return $this->model->newQuery()
            ->where('product_option_id', $productOptionId)
            ->where('available_date', $date)
            ->first();
    }
}
