<?php

namespace App\Repositories;

use App\Models\BlogPost;
use App\Repositories\Contracts\BlogPostRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class BlogPostRepository extends BaseRepository implements BlogPostRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new BlogPost();
    }

    public function getParents(array $columns = ['*']): Collection
    {
        return $this->model->newQuery()
            ->whereNull('parent_id')
            ->withCount('children as articles_count')
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->get($columns);
    }

    public function findWithChildren(int $id): ?BlogPost
    {
        return $this->model->newQuery()
            ->with('children')
            ->find($id);
    }

    public function getFeatured(): ?BlogPost
    {
        return $this->model->newQuery()
            ->parents()
            ->published()
            ->where('is_featured', true)
            ->first();
    }

    public function getLatest(int $limit = 6, ?int $excludeId = null): Collection
    {
        $query = $this->model->newQuery()
            ->parents()
            ->published()
            ->orderByDesc('published_at')
            ->limit($limit);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->get();
    }

    public function getPublishedBySlug(string $slug): ?BlogPost
    {
        return $this->model->newQuery()
            ->published()
            ->where('slug', $slug)
            ->first();
    }

    public function getRelatedPosts(BlogPost $post, int $limit = 6): Collection
    {
        if (!$post->category_id) {
            return collect();
        }

        return $this->model->newQuery()
            ->published()
            ->whereNull('parent_id')
            ->where('id', '!=', $post->id)
            ->where('category_id', $post->category_id)
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    public function getByCategory(int $categoryId, int $perPage = 12)
    {
        return $this->model->newQuery()
            ->parents()
            ->published()
            ->where('category_id', $categoryId)
            ->orderByDesc('published_at')
            ->paginate($perPage);
    }

    public function maxSortOrder(): int
    {
        return (int) $this->model->newQuery()->max('sort_order');
    }

    public function search(string $term, int $perPage = 12)
    {
        return $this->model->newQuery()
            ->parents()
            ->published()
            ->where(function ($q) use ($term) {
                $q->where('title', 'LIKE', "%{$term}%")
                  ->orWhere('excerpt', 'LIKE', "%{$term}%")
                  ->orWhere('content', 'LIKE', "%{$term}%")
                  ->orWhereHas('category', function ($qc) use ($term) {
                      $qc->where('name', 'LIKE', "%{$term}%");
                  })
                  ->orWhereHas('tags', function ($qt) use ($term) {
                      $qt->where('name', 'LIKE', "%{$term}%");
                  })
                  ->orWhereHas('children', function ($qchild) use ($term) {
                      $qchild->where('title', 'LIKE', "%{$term}%")
                             ->orWhere('subtitle', 'LIKE', "%{$term}%")
                             ->orWhere('excerpt', 'LIKE', "%{$term}%")
                             ->orWhere('content', 'LIKE', "%{$term}%");
                  });
            })
            ->orderByDesc('published_at')
            ->paginate($perPage);
    }

    public function unFeatureAllExcept(int $excludeId): void
    {
        $this->model->newQuery()
            ->parents()
            ->where('is_featured', true)
            ->where('id', '!=', $excludeId)
            ->update(['is_featured' => false]);
    }

    public function unFeatureAll(): void
    {
        $this->model->newQuery()
            ->parents()
            ->where('is_featured', true)
            ->update(['is_featured' => false]);
    }

    public function findPublishedById(int $id, array $relations = []): ?BlogPost
    {
        $query = $this->model->newQuery()
            ->published()
            ->where('id', $id);

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->first();
    }

    public function getParentsPublishedQuery(array $relations = [])
    {
        $query = $this->model->newQuery()
            ->parents()
            ->published();

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query;
    }

    public function getLatestWithCategory(int $limit = 6): Collection
    {
        return $this->model->newQuery()
            ->parents()
            ->published()
            ->with('category')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }
}
