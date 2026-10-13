<?php

namespace App\Repositories\Contracts;

use App\Models\TempProduct;
use Illuminate\Database\Eloquent\Collection;

interface TempProductRepositoryInterface extends RepositoryInterface
{
    public function getByStatus(string $status): Collection;

    public function getPending(): Collection;

    public function getFailed(): Collection;

    public function findByApiId(string $apiId, string $platform): ?TempProduct;

    public function countByStatus(string $status): int;
}
