<?php

namespace App\Repositories\Contracts;

use App\Models\EmailLog;
use Illuminate\Database\Eloquent\Collection;

interface EmailLogRepositoryInterface extends RepositoryInterface
{
    public function getByUser(int $userId): Collection;

    public function getByStatus(string $status): Collection;

    public function getRecent(int $limit = 10): Collection;
}
