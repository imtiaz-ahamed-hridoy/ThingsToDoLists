<?php

namespace App\Repositories\Contracts;

use App\Models\Vendor;
use Illuminate\Support\Collection;

interface VendorRepositoryInterface extends RepositoryInterface
{
    public function findByName(string $name): ?Vendor;

    public function findByUserId(int $userId): ?Vendor;

    public function getWithProductsCount(): Collection;

    public function getActive(): Collection;

    public function getForDropdown(): Collection;

    public function firstOrCreate(array $attributes, array $values = []): Vendor;
}
