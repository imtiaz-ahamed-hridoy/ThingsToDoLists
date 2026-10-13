<?php

namespace App\Repositories;

use App\Models\HotelKeyword;
use App\Repositories\Contracts\HotelKeywordRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class HotelKeywordRepository extends BaseRepository implements HotelKeywordRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new HotelKeyword();
    }

    public function getByProduct(int $productId): Collection
    {
        return $this->model->newQuery()
            ->where('product_id', $productId)
            ->orderBy('sort_order')
            ->get();
    }
}
