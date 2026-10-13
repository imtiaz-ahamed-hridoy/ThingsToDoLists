<?php

namespace App\Repositories\Contracts;

use App\Models\Keyword;
use Illuminate\Database\Eloquent\Collection;

interface KeywordRepositoryInterface extends RepositoryInterface
{
    public function getByProduct(int $productId): Collection;

    public function getByType(string $type): Collection;
}
