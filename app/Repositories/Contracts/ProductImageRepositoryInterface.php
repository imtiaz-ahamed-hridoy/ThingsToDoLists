<?php

namespace App\Repositories\Contracts;

use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Collection;

interface ProductImageRepositoryInterface extends RepositoryInterface
{
    public function getByProduct(int $productId): Collection;

    public function getPrimaryForProduct(int $productId): ?ProductImage;

    public function getActiveByProduct(int $productId): Collection;
}
