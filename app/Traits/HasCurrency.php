<?php

namespace App\Traits;

trait HasCurrency
{
    /**
     * Resolve the requested currency.
     * Uses the X-Currency header processed by CurrencyMiddleware.
     * Default fallback is 'EUR'.
     */
    protected function resolveCurrency(): string
    {
        if (app()->bound('user.currency')) {
            return app('user.currency');
        }
        return 'EUR';
    }
}
