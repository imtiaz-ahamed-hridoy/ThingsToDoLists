<?php

namespace App\Observers;

use App\Models\Currency;
use Illuminate\Support\Facades\Cache;

class CurrencyObserver
{
    public function saved(Currency $currency): void
    {
        $this->clearCache();
    }

    public function deleted(Currency $currency): void
    {
        $this->clearCache();
    }

    private function clearCache(): void
    {
        Cache::forget('active_currencies');
        Cache::forget('currency_exchange_rates');
    }
}
