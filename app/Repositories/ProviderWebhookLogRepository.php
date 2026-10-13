<?php

namespace App\Repositories;

use App\Models\ProviderWebhookLog;
use App\Repositories\Contracts\ProviderWebhookLogRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ProviderWebhookLogRepository extends BaseRepository implements ProviderWebhookLogRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new ProviderWebhookLog();
    }

    public function getByProvider(string $provider): Collection
    {
        return $this->model->newQuery()
            ->where('provider', $provider)
            ->orderByDesc('created')
            ->get();
    }

    public function getByStatus(string $status): Collection
    {
        return $this->model->newQuery()
            ->where('status', $status)
            ->orderByDesc('created')
            ->get();
    }

    public function getRecent(int $limit = 20): Collection
    {
        return $this->model->newQuery()
            ->orderByDesc('created')
            ->limit($limit)
            ->get();
    }
}
