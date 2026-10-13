<?php

namespace App\Repositories\Contracts;

use App\Models\HomePageBanner;
use Illuminate\Database\Eloquent\Collection;

interface HomePageBannerRepositoryInterface extends RepositoryInterface
{
    public function getActive(): Collection;

    public function findFirst(): ?HomePageBanner;

    public function updateOrCreate(array $attributes, array $values = []): HomePageBanner;
}
