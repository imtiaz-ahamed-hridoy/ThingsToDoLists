<?php

namespace App\Repositories\Contracts;

use App\Models\BookingQuestion;
use Illuminate\Database\Eloquent\Collection;

interface BookingQuestionRepositoryInterface extends RepositoryInterface
{
    /**
     * Get booking questions for a specific product.
     */
    public function getByProduct(int $productId): Collection;
}
