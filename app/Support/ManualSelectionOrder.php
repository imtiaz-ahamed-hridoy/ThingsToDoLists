<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class ManualSelectionOrder
{
    public static function ids(array $ids): array
    {
        return collect($ids)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public static function syncPayload(array $ids, string $column = 'position'): array
    {
        return collect(self::ids($ids))
            ->mapWithKeys(fn (int $id, int $index) => [$id => [$column => $index + 1]])
            ->all();
    }

    public static function sortModelsByIds(Collection|EloquentCollection $models, array $ids): Collection|EloquentCollection
    {
        $positions = array_flip(self::ids($ids));

        return $models
            ->sortBy(fn ($model) => $positions[(int) $model->id] ?? PHP_INT_MAX)
            ->values();
    }
}
