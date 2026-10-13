<?php

namespace App\Observers;

use App\Models\Categories;
use Illuminate\Support\Facades\Cache;

class CategoriesObserver
{
    public function saved(Categories $category): void
    {
        $this->clearCache($category);
    }

    public function deleted(Categories $category): void
    {
        $this->clearCache($category);
    }

    private function clearCache(Categories $category): void
    {
        Cache::forget('category:stats:' . $category->id);
        Cache::forget('category:stats:all');
    }
}
