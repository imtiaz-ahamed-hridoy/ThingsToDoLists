<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface RecentlyViewedRepositoryInterface extends RepositoryInterface
{
    public function recordView(int $userId, int $productId): void;

    public function getUserViewedProductIds(int $userId, int $limit = 20): array;
}
