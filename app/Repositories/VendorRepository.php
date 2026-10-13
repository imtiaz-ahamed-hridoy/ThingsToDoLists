<?php

namespace App\Repositories;

use App\Models\Vendor;
use App\Repositories\Contracts\VendorRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class VendorRepository extends BaseRepository implements VendorRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new Vendor();
    }

    public function findByName(string $name): ?Vendor
    {
        return $this->model->newQuery()->where('name', $name)->first();
    }

    public function findByUserId(int $userId): ?Vendor
    {
        return $this->model->newQuery()->where('user_id', $userId)->first();
    }

    public function getWithProductsCount(): Collection
    {
        return $this->model->newQuery()
            ->withCount('products')
            ->orderBy('name')
            ->get();
    }

    public function getActive(): Collection
    {
        return $this->model->newQuery()
            ->orderBy('name')
            ->get();
    }

    public function getForDropdown(): Collection
    {
        return $this->model->newQuery()
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    public function firstOrCreate(array $attributes, array $values = []): Vendor
    {
        return Vendor::firstOrCreate($attributes, $values);
    }
}
