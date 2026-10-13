<?php

namespace App\Repositories;

use App\Models\RecentlyViewed;
use App\Repositories\Contracts\RecentlyViewedRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class RecentlyViewedRepository extends BaseRepository implements RecentlyViewedRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new RecentlyViewed();
    }

    public function recordView(int $userId, int $productId): void
    {
        RecentlyViewed::updateOrCreate(
            ['user_id' => $userId, 'product_id' => $productId],
            ['updated_at' => now()]
        );
    }

    public function getUserViewedProductIds(int $userId, int $limit = 20): array
    {
        return RecentlyViewed::where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->pluck('product_id')
            ->all();
    }
}
