<?php

namespace App\Repositories;

use App\Models\HotelContactField;
use App\Repositories\Contracts\HotelContactFieldRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class HotelContactFieldRepository extends BaseRepository implements HotelContactFieldRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new HotelContactField();
    }

    public function getByProduct(int $productId): Collection
    {
        return $this->model->newQuery()
            ->where('product_id', $productId)
            ->orderBy('sort_order')
            ->get();
    }
}
