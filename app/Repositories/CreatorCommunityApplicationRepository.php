<?php

namespace App\Repositories;

use App\Models\CreatorCommunityApplication;
use App\Repositories\Contracts\CreatorCommunityApplicationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class CreatorCommunityApplicationRepository extends BaseRepository implements CreatorCommunityApplicationRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new CreatorCommunityApplication();
    }

    public function getByStatus(string $status): Collection
    {
        return $this->model->newQuery()
            ->where('status', $status)
            ->orderByDesc('created_at')
            ->get();
    }
}
