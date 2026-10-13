<?php

namespace App\Repositories;

use App\Models\BlogPost;
use App\Repositories\Contracts\BlogArticleRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Repository for blog article (child BlogPost) operations.
 *
 * BlogArticle was merged into BlogPost (see parent_id field).
 * This repository scopes queries to child posts only.
 */
class BlogArticleRepository extends BaseRepository implements BlogArticleRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new BlogPost();
    }

    public function getByParentPost(int $parentId): Collection
    {
        return $this->model->newQuery()
            ->where('parent_id', $parentId)
            ->orderBy('sort_order')
            ->get();
    }

    public function getPublishedByParentPost(int $parentId): Collection
    {
        return $this->model->newQuery()
            ->where('parent_id', $parentId)
            ->where('published', true)
            ->orderBy('sort_order')
            ->get();
    }
}
