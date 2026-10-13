<?php

namespace App\Repositories\Contracts;

use App\Models\CancellationPolicyDetail;
use Illuminate\Database\Eloquent\Collection;

interface CancellationPolicyDetailRepositoryInterface extends RepositoryInterface
{
    public function getByPolicy(int $cancellationPolicyId): Collection;
}
