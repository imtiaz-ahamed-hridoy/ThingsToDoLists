<?php

namespace App\Repositories;

use App\Models\BookingQuestion;
use App\Repositories\Contracts\BookingQuestionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class BookingQuestionRepository extends BaseRepository implements BookingQuestionRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new BookingQuestion();
    }

    public function getByProduct(int $productId): Collection
    {
        return $this->model->newQuery()
            ->where('product_id', $productId)
            ->orderBy('sort_order')
            ->get();
    }
}
