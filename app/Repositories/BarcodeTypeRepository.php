<?php

namespace App\Repositories;

use App\Models\BarcodeType;
use App\Repositories\Contracts\BarcodeTypeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class BarcodeTypeRepository extends BaseRepository implements BarcodeTypeRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new BarcodeType();
    }

    public function getForDropdown(): Collection
    {
        return $this->model->newQuery()
            ->orderBy('name')
            ->pluck('name', 'id');
    }
}
