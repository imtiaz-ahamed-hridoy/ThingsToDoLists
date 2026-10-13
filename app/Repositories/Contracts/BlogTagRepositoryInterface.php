<?php

namespace App\Repositories\Contracts;

use App\Models\BlogTag;
use Illuminate\Database\Eloquent\Collection;

interface BlogTagRepositoryInterface extends RepositoryInterface
{
    public function findBySlug(string $slug): ?BlogTag;

    public function firstOrCreate(array $attributes, array $values = []): BlogTag;
}
