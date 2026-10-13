<?php

namespace App\Repositories;

use App\Models\CancellationPolicyDetail;
use App\Repositories\Contracts\CancellationPolicyDetailRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class CancellationPolicyDetailRepository extends BaseRepository implements CancellationPolicyDetailRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new CancellationPolicyDetail();
    }

    public function getByPolicy(int $cancellationPolicyId): Collection
    {
        return $this->model->newQuery()
            ->where('cancellation_policy_id', $cancellationPolicyId)
            ->get();
    }
}
