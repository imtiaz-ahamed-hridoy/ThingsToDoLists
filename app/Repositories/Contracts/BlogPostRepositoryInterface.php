<?php

namespace App\Repositories\Contracts;

use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Collection;

interface BlogPostRepositoryInterface extends RepositoryInterface
{
    public function getParents(array $columns = ['*']): Collection;

    public function findWithChildren(int $id): ?BlogPost;

    public function getFeatured(): ?BlogPost;

    public function getLatest(int $limit = 6, ?int $excludeId = null): Collection;

    public function getPublishedBySlug(string $slug): ?BlogPost;

    public function getRelatedPosts(BlogPost $post, int $limit = 6): Collection;

    public function getByCategory(int $categoryId, int $perPage = 12);

    public function maxSortOrder(): int;

    public function search(string $term, int $perPage = 12);

    public function findPublishedById(int $id, array $relations = []): ?BlogPost;

    public function getParentsPublishedQuery(array $relations = []);

    public function getLatestWithCategory(int $limit = 6): Collection;
}
