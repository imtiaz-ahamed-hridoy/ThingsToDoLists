<?php

namespace App\Repositories\Contracts;

use App\Models\BlogCategory;
use Illuminate\Database\Eloquent\Collection;

interface BlogCategoryRepositoryInterface extends RepositoryInterface
{
    /**
     * Find a category by slug or create it with the given attributes.
     *
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $values
     */
    public function firstOrCreate(array $attributes, array $values = []): BlogCategory;

    /**
     * Get all active categories ordered by sort_order.
     */
    public function getActiveOrdered(): Collection;
}
