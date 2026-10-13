<?php

namespace App\Repositories\Contracts;

use App\Models\CancellationPolicy;
use Illuminate\Database\Eloquent\Collection;

interface CancellationPolicyRepositoryInterface extends RepositoryInterface
{
    public function getByProduct(int $productId): Collection;
}
