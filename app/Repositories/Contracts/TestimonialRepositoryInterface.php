<?php

namespace App\Repositories\Contracts;

use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Collection;

interface TestimonialRepositoryInterface extends RepositoryInterface
{
    public function getActive(): Collection;

    public function getLatest(): Collection;
}
