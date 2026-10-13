<?php

namespace App\Repositories;

use App\Models\Currency;
use App\Repositories\Contracts\CurrencyRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class CurrencyRepository extends BaseRepository implements CurrencyRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new Currency();
    }

    public function getActive(): Collection
    {
        return $this->model->newQuery()
            ->where('is_active', true)
            ->get();
    }

    public function findByCode(string $code): ?Currency
    {
        return $this->model->newQuery()
            ->where('code', strtoupper($code))
            ->first();
    }
}
