<?php

namespace App\Repositories\Contracts;

use App\Models\Categories;
use Illuminate\Support\Collection;

interface CategoriesRepositoryInterface extends RepositoryInterface
{
    public function getForDropdown(): Collection;

    public function findBySlug(string $slug): ?Categories;

    public function findByName(string $name): ?Categories;

    public function getWithProducts(): Collection;

    public function getByType(string $type): Collection;

    public function getActive(): Collection;

    public function firstOrCreate(array $attributes, array $values = []): Categories;

    public function getActiveWithProductCount(?callable $productFilter = null): Collection;

    public function getSearchableCategories(?callable $productScope = null, int $limit = 100): Collection;

    public function findActiveBySlug(string $slug): ?Categories;
}
