<?php

namespace App\Repositories\Contracts;

use App\Models\CreatorSponsoredExperienceApplication;
use Illuminate\Database\Eloquent\Collection;

interface CreatorSponsoredExperienceApplicationRepositoryInterface extends RepositoryInterface
{
    public function getByStatus(string $status): Collection;

    public function getByUserId(int $userId): Collection;
}
