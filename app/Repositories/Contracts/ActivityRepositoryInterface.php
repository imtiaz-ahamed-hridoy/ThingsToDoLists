<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface ActivityRepositoryInterface extends RepositoryInterface
{
    public function getLatestWithCauser(): Collection;
}
