<?php

namespace App\Observers;

use App\Models\HomePageBanner;
use Illuminate\Support\Facades\Cache;

class HomePageBannerObserver
{
    public function saved(HomePageBanner $banner): void
    {
        $this->clearCache();
    }

    public function deleted(HomePageBanner $banner): void
    {
        $this->clearCache();
    }

    private function clearCache(): void
    {
        Cache::forget('homepage:banners:active');
    }
}
