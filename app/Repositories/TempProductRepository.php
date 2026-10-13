<?php

namespace App\Repositories;

use App\Models\TempProduct;
use App\Repositories\Contracts\TempProductRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class TempProductRepository extends BaseRepository implements TempProductRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new TempProduct();
    }

    public function getByStatus(string $status): Collection
    {
        return $this->model->newQuery()
            ->where('status', $status)
            ->orderByDesc('created_at')
            ->get();
    }

    public function getPending(): Collection
    {
        return $this->model->newQuery()
            ->where('last_import_status', 'pending')
            ->orWhereNull('last_import_status')
            ->orderByDesc('created_at')
            ->get();
    }

    public function getFailed(): Collection
    {
        return $this->model->newQuery()
            ->where('last_import_status', 'failed')
            ->orderByDesc('last_import_attempted_at')
            ->get();
    }

    public function findByApiId(string $apiId, string $platform): ?TempProduct
    {
        return $this->model->newQuery()
            ->where('product_api_id', $apiId)
            ->where('api_platform', $platform)
            ->first();
    }

    public function countByStatus(string $status): int
    {
        return $this->model->newQuery()
            ->where('status', $status)
            ->count();
    }
}
