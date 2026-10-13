<?php

namespace App\Repositories\Contracts;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Collection;

interface FaqRepositoryInterface extends RepositoryInterface
{
    public function getActive(): Collection;

    public function search(string $term): Collection;
}
