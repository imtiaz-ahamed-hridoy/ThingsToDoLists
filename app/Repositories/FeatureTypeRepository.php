<?php

namespace App\Repositories;

use App\Models\FeatureType;
use App\Repositories\Contracts\FeatureTypeRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class FeatureTypeRepository extends BaseRepository implements FeatureTypeRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new FeatureType();
    }

    public function findBySlug(string $slug): ?FeatureType
    {
        return $this->model->newQuery()->where('slug', $slug)->first();
    }

    public function findActiveBySlug(string $slug): ?FeatureType
    {
        return $this->model->newQuery()
            ->where('slug', $slug)
            ->where('status', true)
            ->first();
    }

    public function getActive(): Collection
    {
        return $this->model->newQuery()
            ->where('status', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function getWithProductsCount(): Collection
    {
        return $this->model->newQuery()
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function getForDropdown(): Collection
    {
        return $this->model->newQuery()
            ->orderBy('name')
            ->pluck('name', 'id');
    }
}
