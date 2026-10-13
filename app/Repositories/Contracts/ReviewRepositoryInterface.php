<?php

namespace App\Repositories\Contracts;

use App\Models\Review;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ReviewRepositoryInterface extends RepositoryInterface
{
    public function getByProductId(int $productId, array $columns = ['*']): Collection;

    public function getByUserId(int $userId, array $columns = ['*']): Collection;

    public function getActiveByProductId(int $productId, int $perPage = 10): LengthAwarePaginator;

    public function getProductRatingSummary(int $productId): array;

    public function getLatest(int $limit = 10): Collection;

    public function search(string $term, int $perPage = 15): LengthAwarePaginator;
}
