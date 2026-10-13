<?php

namespace App\Repositories\Contracts;

use App\Models\HotelContactField;
use Illuminate\Database\Eloquent\Collection;

interface HotelContactFieldRepositoryInterface extends RepositoryInterface
{
    public function getByProduct(int $productId): Collection;
}
