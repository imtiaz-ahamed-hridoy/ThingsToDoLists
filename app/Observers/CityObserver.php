<?php

namespace App\Observers;

use App\Models\City;
use Illuminate\Support\Facades\Cache;

class CityObserver
{
    public function saved(City $city): void
    {
        $this->clearCache($city);
    }

    public function deleted(City $city): void
    {
        $this->clearCache($city);
    }

    private function clearCache(City $city): void
    {
        Cache::forget('location:cities:all');
        Cache::forget('location:countries:all');
        
        if ($city->slug) {
            Cache::forget('destination:highlights:slug:' . md5($city->slug));
        }
        if ($city->country_slug) {
            Cache::forget('destination:highlights:slug:' . md5($city->country_slug));
        }
    }
}
