<?php

namespace App\Repositories\Contracts;

use App\Models\CreatorCommunityApplication;
use Illuminate\Database\Eloquent\Collection;

interface CreatorCommunityApplicationRepositoryInterface extends RepositoryInterface
{
    public function getByStatus(string $status): Collection;
}
