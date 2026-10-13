<?php

namespace App\Observers;

use App\Models\HomePageSectionLocation;
use Illuminate\Support\Facades\Cache;

class HomePageSectionLocationObserver
{
    public function saved(HomePageSectionLocation $location): void
    {
        $this->clearCache($location);
    }

    public function deleted(HomePageSectionLocation $location): void
    {
        $this->clearCache($location);
    }

    private function clearCache(HomePageSectionLocation $location): void
    {
        Cache::forget('homepage:sections:active');
        Cache::forget('homepage:section:' . $location->section_id);
    }
}
