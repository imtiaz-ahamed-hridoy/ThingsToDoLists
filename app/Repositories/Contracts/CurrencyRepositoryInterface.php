<?php

namespace App\Repositories\Contracts;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Collection;

interface CurrencyRepositoryInterface extends RepositoryInterface
{
    public function getActive(): Collection;

    public function findByCode(string $code): ?Currency;
}
