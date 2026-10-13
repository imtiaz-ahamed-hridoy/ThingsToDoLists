<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface extends RepositoryInterface
{
    public function findWithRelations(int $id, array $relations = []): ?Product;

    public function findBySlug(string $slug): ?Product;

    public function slugExists(string $slug, ?int $excludeId = null): bool;

    public function getPublished(array $columns = ['*']): Collection;

    public function getAvailable(array $columns = ['*']): Collection;

    public function getDataTableQuery();

    public function bulkDelete(array $ids): int;

    public function getByIds(array $ids, array $columns = ['*']): Collection;

    public function search(string $term, int $perPage = 15): LengthAwarePaginator;

    public function getPublishedProducts(array $filters = [], string $sort = 'popular', int $perPage = 20): LengthAwarePaginator;

    public function getPublishedProductDetail(?int $id = null, ?string $productApiId = null, ?string $platform = null): ?Product;

    public function getCategoryStats(int $categoryId, string $fallbackCurrency = 'EUR'): ?Model;

    public function getRepresentativeProduct(int $categoryId, array $columns = ['*']): ?Product;

    public function getDestinationImageProduct(array $columns = ['id', 'thumbnail']): ?Product;

    public function getDestinationProductsQuery(string $location, bool $isCity): Builder;

    public function getPublishedBaseQuery(array $relations = []): Builder;
}
