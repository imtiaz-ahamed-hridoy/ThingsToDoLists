<?php

namespace App\Repositories\Contracts;

use App\Models\Creator;

interface CreatorRepositoryInterface extends RepositoryInterface
{
    public function findByUserId(int $userId): ?Creator;
}
