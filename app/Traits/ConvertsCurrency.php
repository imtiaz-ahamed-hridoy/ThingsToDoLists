<?php

namespace App\Traits;

use App\Services\CurrencyService;

trait ConvertsCurrency
{
    /**
     * Convert and format a price based on the user's requested currency.
     * The requested currency is set by CurrencyMiddleware in the service container.
     *
     * Only use this for providers whose prices are stored in a BASE currency (e.g. USD)
     * and need local exchange-rate conversion — e.g. Manual / Supplier products.
     *
     * ⚠️ Do NOT call this for Bokun prices. Bokun already returns prices in the
     * user-requested currency — calling formatPrice() on those values causes
     * a double-conversion and produces wrong amounts.
     *
     *
     * @param float|int|null $amount
     * @param string $originalCurrency
     * @return float|null
     */
    protected function formatPrice($amount, $originalCurrency = 'EUR')
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        $requestedCurrency = app()->bound('user.currency') ? app('user.currency') : $originalCurrency;

        if ($requestedCurrency === $originalCurrency) {
            return (float) $amount;
        }

        $currencyService = app(CurrencyService::class);
        return $currencyService->convert($amount, $originalCurrency, $requestedCurrency);
    }

    /**
     * Pass a Bokun price through unchanged (Bokun already returns prices in
     * the user-requested currency — no local conversion needed).
     *
     * Always rounds to 2 decimal places for display consistency.
     *
     * @param float|int|null $amount
     * @return float|null
     */
    protected function rawPrice($amount): ?float
    {
        if ($amount === null || $amount === '') {
            return null;
        }
        return round((float) $amount, 2);
    }
}
