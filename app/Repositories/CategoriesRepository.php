<?php

namespace App\Repositories;

use App\Models\Categories;
use App\Repositories\Contracts\CategoriesRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CategoriesRepository extends BaseRepository implements CategoriesRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new Categories();
    }

    public function getForDropdown(): Collection
    {
        return $this->model->newQuery()
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    public function findBySlug(string $slug): ?Categories
    {
        return $this->model->newQuery()
            ->where('slug', $slug)
            ->first();
    }

    public function findByName(string $name): ?Categories
    {
        return $this->model->newQuery()
            ->where('name', $name)
            ->first();
    }

    public function getWithProducts(): Collection
    {
        return $this->model->newQuery()
            ->has('products')
            ->withCount('products')
            ->orderBy('name')
            ->get();
    }

    public function getByType(string $type): Collection
    {
        return $this->model->newQuery()
            ->where('type', $type)
            ->orderBy('name')
            ->get();
    }

    public function getActive(): Collection
    {
        return $this->model->newQuery()
            ->where('status', 1)
            ->orderBy('name')
            ->get();
    }

    public function firstOrCreate(array $attributes, array $values = []): Categories
    {
        return Categories::firstOrCreate($attributes, $values);
    }

    public function getActiveWithProductCount(?callable $productFilter = null): Collection
    {
        $query = $this->model->newQuery()
            ->where('status', 1)
            ->orderBy('name');

        if ($productFilter !== null) {
            $query->withCount(['products as products_count' => $productFilter]);
        } else {
            $query->withCount('products');
        }

        return $query->get();
    }

    public function getSearchableCategories(?callable $productScope = null, int $limit = 100): Collection
    {
        $query = $this->model->newQuery()
            ->where('status', '1')
            ->where(function ($q) {
                $q->where('show_in_search', 1)
                  ->orWhereNull('show_in_search');
            });

        if ($productScope !== null) {
            $query->whereHas('products', $productScope);
            $query->withCount(['products as products_count' => $productScope]);
        } else {
            $query->withCount('products');
        }

        return $query
            ->orderBy('search_sort_order')
            ->orderByDesc('products_count')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    public function findActiveBySlug(string $slug): ?Categories
    {
        return $this->model->newQuery()
            ->where('slug', $slug)
            ->where('status', '1')
            ->select(['id', 'slug', 'name', 'search_label', 'status'])
            ->first();
    }
}
