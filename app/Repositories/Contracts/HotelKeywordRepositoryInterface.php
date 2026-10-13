<?php

namespace App\Repositories\Contracts;

use App\Models\HotelKeyword;
use Illuminate\Database\Eloquent\Collection;

interface HotelKeywordRepositoryInterface extends RepositoryInterface
{
    /**
     * Get hotel keywords for a specific product.
     */
    public function getByProduct(int $productId): Collection;
}
