<?php

namespace App\Observers;

use App\Models\BlogCategory;
use Illuminate\Support\Facades\Cache;

class BlogCategoryObserver
{
    public function saved(BlogCategory $category): void
    {
        $this->clearCache();
    }

    public function deleted(BlogCategory $category): void
    {
        $this->clearCache();
    }

    private function clearCache(): void
    {
        Cache::forget('blog_categories:active');
        // Also clear blog index caches since they may filter by category
        foreach ([6, 9, 12, 15, 20] as $perPage) {
            for ($page = 1; $page <= 5; $page++) {
                Cache::forget('blogs:index:' . md5("{$perPage}|{$page}||"));
            }
        }
    }
}
