<?php

namespace App\Repositories;

use App\Models\Testimonial;
use App\Repositories\Contracts\TestimonialRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class TestimonialRepository extends BaseRepository implements TestimonialRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new Testimonial();
    }

    public function getActive(): Collection
    {
        return $this->model->newQuery()
            ->where('status', true)
            ->latest()
            ->get();
    }

    public function getLatest(): Collection
    {
        return $this->model->newQuery()
            ->latest()
            ->get();
    }
}
