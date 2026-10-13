<?php

namespace App\Repositories;

use App\Models\ProductOption;
use App\Repositories\Contracts\ProductOptionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ProductOptionRepository extends BaseRepository implements ProductOptionRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new ProductOption();
    }

    public function getByProduct(int $productId): Collection
    {
        return $this->model->newQuery()
            ->where('product_id', $productId)
            ->orderBy('sort_order')
            ->get();
    }

    public function getDefault(int $productId): ?ProductOption
    {
        return $this->model->newQuery()
            ->where('product_id', $productId)
            ->where('is_default', true)
            ->first();
    }

    public function getActiveByProduct(int $productId): Collection
    {
        return $this->model->newQuery()
            ->where(function ($q) use ($productId) {
                $q->where('product_id', $productId)
                  ->where('is_default', true)
                  ->orWhere(function ($sub) use ($productId) {
                      $sub->where('product_id', $productId)
                          ->where(function ($dates) {
                              $dates->whereNull('publish_start')
                                    ->orWhere('publish_start', '<=', now());
                          })
                          ->where(function ($dates) {
                              $dates->whereNull('publish_end')
                                    ->orWhere('publish_end', '>=', now());
                          });
                  });
            })
            ->orderBy('sort_order')
            ->get();
    }
}
