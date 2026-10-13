<?php

namespace App\Repositories\Contracts;

use App\Models\ChunkUploadSession;

interface ChunkUploadSessionRepositoryInterface extends RepositoryInterface
{
    public function findByTempId(string $tempId): ?ChunkUploadSession;
}
