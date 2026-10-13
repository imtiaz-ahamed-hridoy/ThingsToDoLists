<?php

namespace App\Repositories;

use App\Models\DynamicPage;
use App\Repositories\Contracts\DynamicPageRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class DynamicPageRepository extends BaseRepository implements DynamicPageRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new DynamicPage();
    }

    public function findBySlug(string $slug): ?DynamicPage
    {
        return $this->model->newQuery()
            ->where('page_slug', $slug)
            ->first();
    }

    public function getActive()
    {
        return $this->model->newQuery()
            ->where('status', true)
            ->paginate(20);
    }
}
