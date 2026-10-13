<?php

namespace App\Repositories;

use App\Models\PassengerField;
use App\Repositories\Contracts\PassengerFieldRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class PassengerFieldRepository extends BaseRepository implements PassengerFieldRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new PassengerField();
    }

    public function getByProduct(int $productId): Collection
    {
        return $this->model->newQuery()
            ->where('product_id', $productId)
            ->orderBy('sort_order')
            ->get();
    }
}
