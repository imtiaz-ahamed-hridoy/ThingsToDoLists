<?php

namespace App\Repositories;

use App\Models\Review;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class ReviewRepository extends BaseRepository implements ReviewRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new Review();
    }

    public function getByProductId(int $productId, array $columns = ['*']): Collection
    {
        return $this->model->newQuery()
            ->where('product_id', $productId)
            ->orderByDesc('created_at')
            ->get($columns);
    }

    public function getByUserId(int $userId, array $columns = ['*']): Collection
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get($columns);
    }

    public function getActiveByProductId(int $productId, int $perPage = 10): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->where('product_id', $productId)
            ->where(function ($q) {
                $q->where('status', 'Active')
                  ->orWhere('status', 'active')
                  ->orWhere('status', 'approved');
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function getProductRatingSummary(int $productId): array
    {
        $row = $this->model->newQuery()
            ->selectRaw('COUNT(*) as count, AVG(ratings) as avg')
            ->where('product_id', $productId)
            ->where(function ($q) {
                $q->where('status', 'Active')
                  ->orWhere('status', 'active')
                  ->orWhere('status', 'approved');
            })
            ->first();

        $count = (int) ($row->count ?? 0);
        $avg = (float) ($row->avg ?? 0);

        if ($count === 0) {
            $row = $this->model->newQuery()
                ->selectRaw('COUNT(*) as count, AVG(ratings) as avg')
                ->where('product_id', $productId)
                ->first();

            $count = (int) ($row->count ?? 0);
            $avg = (float) ($row->avg ?? 0);
        }

        return ['count' => $count, 'average' => round($avg, 1)];
    }

    public function getLatest(int $limit = 10): Collection
    {
        return $this->model->newQuery()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function search(string $term, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->where(function ($q) use ($term) {
                $q->where('comment', 'LIKE', "%{$term}%")
                  ->orWhereHas('product', fn($p) => $p->where('title', 'LIKE', "%{$term}%"))
                  ->orWhereHas('users', fn($u) => $u->where('name', 'LIKE', "%{$term}%"));
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
