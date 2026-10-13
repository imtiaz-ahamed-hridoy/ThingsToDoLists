<?php

namespace App\Repositories;

use App\Models\ProductImage;
use App\Repositories\Contracts\ProductImageRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ProductImageRepository extends BaseRepository implements ProductImageRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new ProductImage();
    }

    public function getByProduct(int $productId): Collection
    {
        return $this->model->newQuery()
            ->where('product_id', $productId)
            ->ordered()
            ->get();
    }

    public function getPrimaryForProduct(int $productId): ?ProductImage
    {
        return $this->model->newQuery()
            ->where('product_id', $productId)
            ->primary()
            ->active()
            ->first();
    }

    public function getActiveByProduct(int $productId): Collection
    {
        return $this->model->newQuery()
            ->where('product_id', $productId)
            ->active()
            ->ordered()
            ->get();
    }
}
