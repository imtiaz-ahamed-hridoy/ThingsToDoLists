<?php

namespace App\Repositories\Contracts;

use App\Models\PassengerField;
use Illuminate\Database\Eloquent\Collection;

interface PassengerFieldRepositoryInterface extends RepositoryInterface
{
    public function getByProduct(int $productId): Collection;
}
