<?php

namespace App\Repositories\Contracts;

use App\Models\ProductVideo;
use Illuminate\Database\Eloquent\Collection;

interface ProductVideoRepositoryInterface extends RepositoryInterface
{
    public function getByProduct(int $productId): Collection;

    public function getActiveByProduct(int $productId): Collection;

    public function getPrimaryForProduct(int $productId): ?ProductVideo;
}
