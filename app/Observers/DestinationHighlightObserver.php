<?php

namespace App\Observers;

use App\Models\DestinationHighlight;
use Illuminate\Support\Facades\Cache;

class DestinationHighlightObserver
{
    public function saved(DestinationHighlight $highlight): void
    {
        $this->clearCache($highlight);
    }

    public function deleted(DestinationHighlight $highlight): void
    {
        $this->clearCache($highlight);
    }

    private function clearCache(DestinationHighlight $highlight): void
    {
        Cache::forget('destination:highlight:' . $highlight->id);

        if ($highlight->destination_id) {
            $city = \App\Models\City::find($highlight->destination_id);
            if ($city) {
                if ($highlight->destination_type === 'city') {
                    Cache::forget('destination:highlights:slug:' . md5($city->slug ?? $city->city_name));
                } else {
                    Cache::forget('destination:highlights:slug:' . md5($city->country_slug ?? $city->country_name));
                }
            }
        }
    }
}
