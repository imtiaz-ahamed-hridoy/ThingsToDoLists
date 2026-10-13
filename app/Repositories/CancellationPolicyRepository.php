<?php

namespace App\Repositories;

use App\Models\CancellationPolicy;
use App\Repositories\Contracts\CancellationPolicyRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class CancellationPolicyRepository extends BaseRepository implements CancellationPolicyRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new CancellationPolicy();
    }

    public function getByProduct(int $productId): Collection
    {
        return $this->model->newQuery()
            ->where('product_id', $productId)
            ->orderBy('sort_order')
            ->get();
    }
}
