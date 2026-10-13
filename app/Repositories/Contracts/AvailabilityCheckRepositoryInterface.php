<?php

namespace App\Repositories\Contracts;

use App\Models\AvailabilityCheck;
use Illuminate\Database\Eloquent\Collection;

interface AvailabilityCheckRepositoryInterface extends RepositoryInterface
{
    public function getByProduct(int $productId): Collection;

    public function getByOption(int $productOptionId): Collection;

    public function getAvailable(int $productOptionId): Collection;

    public function getByDate(int $productOptionId, string $date): ?AvailabilityCheck;
}
