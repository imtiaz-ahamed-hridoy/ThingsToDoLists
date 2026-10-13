<?php

namespace App\Repositories;

use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class BaseRepository implements RepositoryInterface
{
    protected Model $model;

    public function __construct()
    {
        $this->model = $this->resolveModel();
    }

    abstract protected function resolveModel(): Model;

    public function all(array $columns = ['*']): Collection
    {
        return $this->model->all($columns);
    }

    public function find(int $id, array $columns = ['*']): ?Model
    {
        return $this->model->find($id, $columns);
    }

    public function findOrFail(int $id, array $columns = ['*']): Model
    {
        return $this->model->findOrFail($id, $columns);
    }

    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    public function update(Model $model, array $data): Model
    {
        $model->update($data);
        return $model->fresh();
    }

    public function delete(Model $model): bool
    {
        return $model->delete();
    }

    public function deleteById(int $id): bool
    {
        return $this->findOrFail($id)->delete();
    }

    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->model->paginate($perPage, $columns);
    }

    public function where(array $conditions, array $columns = ['*']): Collection
    {
        $query = $this->model->newQuery();
        foreach ($conditions as $column => $value) {
            $query->where($column, $value);
        }
        return $query->get($columns);
    }

    public function whereFirst(array $conditions, array $columns = ['*']): ?Model
    {
        $query = $this->model->newQuery();
        foreach ($conditions as $column => $value) {
            $query->where($column, $value);
        }
        return $query->first($columns);
    }

    public function count(): int
    {
        return $this->model->count();
    }

    /**
     * Get a new query builder instance.
     */
    protected function newQuery()
    {
        return $this->model->newQuery();
    }

    /**
     * Get a new query builder instance (public).
     */
    public function query()
    {
        return $this->model->newQuery();
    }
}
