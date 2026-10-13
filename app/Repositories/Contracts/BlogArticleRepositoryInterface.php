<?php

namespace App\Repositories\Contracts;

use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Collection;

/**
 * Repository for blog article (child BlogPost) operations.
 *
 * BlogArticle was merged into BlogPost (see parent_id field).
 * This repository scopes queries to child posts only.
 */
interface BlogArticleRepositoryInterface extends RepositoryInterface
{
    /**
     * Get child articles for a given parent post.
     */
    public function getByParentPost(int $parentId): Collection;

    /**
     * Get published child articles for a given parent post.
     */
    public function getPublishedByParentPost(int $parentId): Collection;
}
