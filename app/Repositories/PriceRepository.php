<?php

namespace App\Repositories;

use App\Models\Price;
use App\Repositories\Contracts\PriceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class PriceRepository extends BaseRepository implements PriceRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new Price();
    }

    public function getByProduct(int $productId): Collection
    {
        return $this->model->newQuery()
            ->where('product_id', $productId)
            ->orderBy('sort_order')
            ->get();
    }

    public function getByOption(int $productOptionId): Collection
    {
        return $this->model->newQuery()
            ->where('product_option_id', $productOptionId)
            ->orderBy('sort_order')
            ->get();
    }

    public function getDefaultForOption(int $productOptionId): ?Price
    {
        return $this->model->newQuery()
            ->where('product_option_id', $productOptionId)
            ->where('is_default', true)
            ->first();
    }

    public function getDefaultForProduct(int $productId): ?Price
    {
        return $this->model->newQuery()
            ->where('product_id', $productId)
            ->where('is_default', true)
            ->first();
    }
}
