<?php

namespace App\Repositories\Contracts;

use App\Models\ProductOption;
use Illuminate\Database\Eloquent\Collection;

interface ProductOptionRepositoryInterface extends RepositoryInterface
{
    public function getByProduct(int $productId): Collection;

    public function getDefault(int $productId): ?ProductOption;

    public function getActiveByProduct(int $productId): Collection;
}
