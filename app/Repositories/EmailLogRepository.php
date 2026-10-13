<?php

namespace App\Repositories;

use App\Models\EmailLog;
use App\Repositories\Contracts\EmailLogRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class EmailLogRepository extends BaseRepository implements EmailLogRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new EmailLog();
    }

    public function getByUser(int $userId): Collection
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->orderByDesc('sent_at')
            ->get();
    }

    public function getByStatus(string $status): Collection
    {
        return $this->model->newQuery()
            ->where('status', $status)
            ->orderByDesc('sent_at')
            ->get();
    }

    public function getRecent(int $limit = 10): Collection
    {
        return $this->model->newQuery()
            ->orderByDesc('sent_at')
            ->limit($limit)
            ->get();
    }
}
