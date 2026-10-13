<?php

namespace App\Repositories\Contracts;

use App\Models\HomePageSection;
use Illuminate\Database\Eloquent\Collection;

interface HomePageSectionRepositoryInterface extends RepositoryInterface
{
    public function getActiveOrdered(): Collection;
}
