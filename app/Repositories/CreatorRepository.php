<?php

namespace App\Repositories;

use App\Models\Creator;
use App\Repositories\Contracts\CreatorRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class CreatorRepository extends BaseRepository implements CreatorRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new Creator();
    }

    public function findByUserId(int $userId): ?Creator
    {
        return $this->model->newQuery()->where('user_id', $userId)->first();
    }
}
