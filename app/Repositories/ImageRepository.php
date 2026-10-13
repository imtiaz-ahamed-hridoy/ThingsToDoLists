<?php

namespace App\Repositories;

use App\Models\Image;
use App\Repositories\Contracts\ImageRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ImageRepository extends BaseRepository implements ImageRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new Image();
    }

    public function getByProduct(int $productId): Collection
    {
        return $this->model->newQuery()
            ->where('product_id', $productId)
            ->orderBy('sort_order')
            ->get();
    }

    public function getPrimaryForProduct(int $productId): ?Image
    {
        return $this->model->newQuery()
            ->where('product_id', $productId)
            ->where('sort_order', 0)
            ->first();
    }
}
