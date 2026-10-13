<?php

namespace App\Repositories\Contracts;

use App\Models\FeatureType;
use Illuminate\Support\Collection;

interface FeatureTypeRepositoryInterface extends RepositoryInterface
{
    public function findBySlug(string $slug): ?FeatureType;

    public function findActiveBySlug(string $slug): ?FeatureType;

    public function getActive(): Collection;

    public function getWithProductsCount(): Collection;

    public function getForDropdown(): Collection;
}
