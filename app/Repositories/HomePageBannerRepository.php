<?php

namespace App\Repositories;

use App\Models\HomePageBanner;
use App\Repositories\Contracts\HomePageBannerRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class HomePageBannerRepository extends BaseRepository implements HomePageBannerRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new HomePageBanner();
    }

    public function getActive(): Collection
    {
        return $this->model->newQuery()
            ->where('status', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function findFirst(): ?HomePageBanner
    {
        return $this->model->newQuery()->first();
    }

    public function updateOrCreate(array $attributes, array $values = []): HomePageBanner
    {
        return HomePageBanner::updateOrCreate($attributes, $values);
    }
}
