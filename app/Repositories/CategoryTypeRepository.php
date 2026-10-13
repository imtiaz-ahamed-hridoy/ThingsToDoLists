<?php

namespace App\Repositories;

use App\Models\CategoryType;
use App\Repositories\Contracts\CategoryTypeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class CategoryTypeRepository extends BaseRepository implements CategoryTypeRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new CategoryType();
    }

    public function getActive(): Collection
    {
        return $this->model->newQuery()
            ->where('status', 1)
            ->get();
    }

    public function findBySlug(string $slug): ?CategoryType
    {
        return $this->model->newQuery()->where('slug', $slug)->first();
    }

    public function getWithCategories(): Collection
    {
        return $this->model->newQuery()
            ->with('categories')
            ->get();
    }
}
