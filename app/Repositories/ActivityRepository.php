<?php

namespace App\Repositories;

use App\Models\Activity;
use App\Repositories\Contracts\ActivityRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ActivityRepository extends BaseRepository implements ActivityRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new Activity();
    }

    public function getLatestWithCauser(): Collection
    {
        return $this->model->newQuery()
            ->with('causer')
            ->latest()
            ->get();
    }
}
