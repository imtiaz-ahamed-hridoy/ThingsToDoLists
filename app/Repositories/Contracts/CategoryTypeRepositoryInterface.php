<?php

namespace App\Repositories\Contracts;

use App\Models\CategoryType;
use Illuminate\Database\Eloquent\Collection;

interface CategoryTypeRepositoryInterface extends RepositoryInterface
{
    public function getActive(): Collection;

    public function findBySlug(string $slug): ?CategoryType;

    public function getWithCategories(): Collection;
}
