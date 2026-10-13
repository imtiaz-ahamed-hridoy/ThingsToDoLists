<?php

namespace App\Repositories\Contracts;

use App\Models\Image;
use Illuminate\Database\Eloquent\Collection;

interface ImageRepositoryInterface extends RepositoryInterface
{
    public function getByProduct(int $productId): Collection;

    public function getPrimaryForProduct(int $productId): ?Image;
}
