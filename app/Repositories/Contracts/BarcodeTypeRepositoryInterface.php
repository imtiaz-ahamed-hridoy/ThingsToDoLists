<?php

namespace App\Repositories\Contracts;

use App\Models\BarcodeType;
use Illuminate\Database\Eloquent\Collection;

interface BarcodeTypeRepositoryInterface extends RepositoryInterface
{
    public function getForDropdown(): Collection;
}
