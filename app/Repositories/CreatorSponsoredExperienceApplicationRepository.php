<?php

namespace App\Repositories;

use App\Models\CreatorSponsoredExperienceApplication;
use App\Repositories\Contracts\CreatorSponsoredExperienceApplicationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class CreatorSponsoredExperienceApplicationRepository extends BaseRepository implements CreatorSponsoredExperienceApplicationRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new CreatorSponsoredExperienceApplication();
    }

    public function getByStatus(string $status): Collection
    {
        return $this->model->newQuery()
            ->where('status', $status)
            ->orderByDesc('created_at')
            ->get();
    }

    public function getByUserId(int $userId): Collection
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get();
    }
}
