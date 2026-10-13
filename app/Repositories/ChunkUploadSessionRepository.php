<?php

namespace App\Repositories;

use App\Models\ChunkUploadSession;
use App\Repositories\Contracts\ChunkUploadSessionRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class ChunkUploadSessionRepository extends BaseRepository implements ChunkUploadSessionRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new ChunkUploadSession();
    }

    public function findByTempId(string $tempId): ?ChunkUploadSession
    {
        return $this->model->newQuery()->where('temp_id', $tempId)->first();
    }
}
