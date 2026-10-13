<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface RepositoryInterface
{
    public function all(array $columns = ['*']): Collection;

    public function find(int $id, array $columns = ['*']): ?Model;

    public function findOrFail(int $id, array $columns = ['*']): Model;

    public function create(array $data): Model;

    public function update(Model $model, array $data): Model;

    public function delete(Model $model): bool;

    public function deleteById(int $id): bool;

    public function paginate(int $perPage = 15, array $columns = ['*']);

    public function where(array $conditions, array $columns = ['*']): Collection;

    public function whereFirst(array $conditions, array $columns = ['*']): ?Model;

    public function count(): int;

    public function query();
}
