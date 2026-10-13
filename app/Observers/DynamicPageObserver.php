<?php

namespace App\Observers;

use App\Models\DynamicPage;
use Illuminate\Support\Facades\Cache;

class DynamicPageObserver
{
    public function saved(DynamicPage $page): void
    {
        $this->clearCache($page);
    }

    public function deleted(DynamicPage $page): void
    {
        $this->clearCache($page);
    }

    private function clearCache(DynamicPage $page): void
    {
        Cache::forget('dynamic_pages:active');
        Cache::forget('dynamic_page:' . $page->id);
    }
}
