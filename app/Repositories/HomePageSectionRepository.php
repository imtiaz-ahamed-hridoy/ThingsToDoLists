<?php

namespace App\Repositories;

use App\Models\HomePageSection;
use App\Repositories\Contracts\HomePageSectionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class HomePageSectionRepository extends BaseRepository implements HomePageSectionRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new HomePageSection();
    }

    public function getActiveOrdered(): Collection
    {
        return $this->model->newQuery()
            ->with('locations.city')
            ->active()
            ->ordered()
            ->get();
    }
}
