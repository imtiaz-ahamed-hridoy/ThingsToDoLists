<?php

namespace App\Repositories;

use App\Models\Keyword;
use App\Repositories\Contracts\KeywordRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class KeywordRepository extends BaseRepository implements KeywordRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new Keyword();
    }

    public function getByProduct(int $productId): Collection
    {
        return $this->model->newQuery()
            ->where('product_id', $productId)
            ->orderBy('sort_order')
            ->get();
    }

    public function getByType(string $type): Collection
    {
        return $this->model->newQuery()
            ->where('type', $type)
            ->orderBy('sort_order')
            ->get();
    }
}
