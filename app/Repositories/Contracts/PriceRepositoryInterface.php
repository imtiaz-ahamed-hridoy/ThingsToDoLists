<?php

namespace App\Repositories\Contracts;

use App\Models\Price;
use Illuminate\Database\Eloquent\Collection;

interface PriceRepositoryInterface extends RepositoryInterface
{
    public function getByProduct(int $productId): Collection;

    public function getByOption(int $productOptionId): Collection;

    public function getDefaultForOption(int $productOptionId): ?Price;

    public function getDefaultForProduct(int $productId): ?Price;
}
