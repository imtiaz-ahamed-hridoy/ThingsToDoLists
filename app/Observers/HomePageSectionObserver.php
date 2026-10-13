<?php

namespace App\Observers;

use App\Models\HomePageSection;
use Illuminate\Support\Facades\Cache;

class HomePageSectionObserver
{
    public function saved(HomePageSection $section): void
    {
        $this->clearCache($section);
    }

    public function deleted(HomePageSection $section): void
    {
        $this->clearCache($section);
    }

    private function clearCache(HomePageSection $section): void
    {
        Cache::forget('homepage:sections:active');
        Cache::forget('homepage:section:' . $section->id);
    }
}
