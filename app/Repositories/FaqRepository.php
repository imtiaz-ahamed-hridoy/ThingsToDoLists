<?php

namespace App\Repositories;

use App\Models\Faq;
use App\Repositories\Contracts\FaqRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class FaqRepository extends BaseRepository implements FaqRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new Faq();
    }

    public function getActive(): Collection
    {
        return $this->model->newQuery()
            ->where('status', true)
            ->get();
    }

    public function search(string $term): Collection
    {
        return $this->model->newQuery()
            ->where(function ($q) use ($term) {
                $q->where('question', 'LIKE', "%{$term}%")
                  ->orWhere('answer', 'LIKE', "%{$term}%");
            })
            ->get();
    }
}
