<?php

namespace App\Repositories;

use App\Models\BlogCategory;
use App\Repositories\Contracts\BlogCategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class BlogCategoryRepository extends BaseRepository implements BlogCategoryRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new BlogCategory();
    }

    public function firstOrCreate(array $attributes, array $values = []): BlogCategory
    {
        return $this->model->newQuery()->firstOrCreate($attributes, $values);
    }

    public function getActiveOrdered(): Collection
    {
        return $this->model->newQuery()
            ->where('status', true)
            ->orderBy('sort_order')
            ->get();
    }
}
