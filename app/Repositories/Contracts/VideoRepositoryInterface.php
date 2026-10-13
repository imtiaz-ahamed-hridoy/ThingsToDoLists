<?php

namespace App\Repositories\Contracts;

use App\Models\Video;
use Illuminate\Database\Eloquent\Collection;

interface VideoRepositoryInterface extends RepositoryInterface
{
    public function getByProduct(int $productId): Collection;
}
