<?php

namespace App\Repositories;

use App\Models\BlogTag;
use App\Repositories\Contracts\BlogTagRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class BlogTagRepository extends BaseRepository implements BlogTagRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new BlogTag();
    }

    public function findBySlug(string $slug): ?BlogTag
    {
        return $this->model->newQuery()->where('slug', $slug)->first();
    }

    public function firstOrCreate(array $attributes, array $values = []): BlogTag
    {
        return $this->model->newQuery()->firstOrCreate($attributes, $values);
    }
}
