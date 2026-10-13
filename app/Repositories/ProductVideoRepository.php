<?php

namespace App\Repositories;

use App\Models\ProductVideo;
use App\Repositories\Contracts\ProductVideoRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ProductVideoRepository extends BaseRepository implements ProductVideoRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new ProductVideo();
    }

    public function getByProduct(int $productId): Collection
    {
        return $this->model->newQuery()
            ->where('product_id', $productId)
            ->ordered()
            ->get();
    }

    public function getActiveByProduct(int $productId): Collection
    {
        return $this->model->newQuery()
            ->where('product_id', $productId)
            ->active()
            ->ordered()
            ->get();
    }

    public function getPrimaryForProduct(int $productId): ?ProductVideo
    {
        return $this->model->newQuery()
            ->where('product_id', $productId)
            ->where('is_primary', true)
            ->active()
            ->first();
    }
}
