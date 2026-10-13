<?php

namespace App\Repositories\Contracts;

use App\Models\DynamicPage;

interface DynamicPageRepositoryInterface extends RepositoryInterface
{
    public function findBySlug(string $slug): ?DynamicPage;

    public function getActive();
}
