<?php

namespace App\Services;

use App\Models\Currency;
use Illuminate\Support\Facades\Cache;

class CurrencyService
{
    /**
     * Fallback exchange rates relative to EUR (EUR = 1.0).
     * Used whenever the database table has not yet synced or is missing a specific currency.
     */
    public const DEFAULT_RATES = [
        'EUR' => 1.000000,
        'USD' => 1.080000,
        'GBP' => 0.850000,
        'IDR' => 17500.000000,
        'VND' => 27500.000000,
        'THB' => 39.000000,
        'PHP' => 62.000000,
        'MYR' => 5.100000,
        'JPY' => 165.000000,
        'SGD' => 1.450000,
        'AUD' => 1.650000,
        'CAD' => 1.480000,
        'CHF' => 0.950000,
        'CNY' => 7.800000,
        'HKD' => 8.400000,
        'INR' => 90.000000,
        'KRW' => 1480.000000,
        'TWD' => 35.000000,
        'AED' => 3.970000,
        'SAR' => 4.050000,
        'NZD' => 1.780000,
        'TRY' => 37.000000,
        'BRL' => 6.000000,
        'MXN' => 21.000000,
    ];

    /**
     * Get all active exchange rates from cache or database, merged with default rates.
     *
     * @return array
     */
    public function getExchangeRates()
    {
        try {
            $dbRates = Cache::remember('currency_exchange_rates', now()->addMinutes(20), function () {
                return Currency::where('is_active', true)->pluck('exchange_rate', 'code')->toArray();
            });
        } catch (\Throwable) {
            $dbRates = [];
        }

        return array_merge(self::DEFAULT_RATES, is_array($dbRates) ? $dbRates : ($dbRates?->toArray() ?? []));
    }

    /**
     * Get all active currencies for the API endpoint.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveCurrencies()
    {
        return Cache::remember('active_currencies', now()->addMinutes(20), function () {
            return Currency::where('is_active', true)->select('code', 'symbol', 'exchange_rate')->get();
        });
    }

    /**
     * Convert an amount from one currency to another.
     *
     * @param float|int|null $amount
     * @param string $fromCurrency
     * @param string $toCurrency
     * @return float|null
     */
    public function convert($amount, $fromCurrency = 'EUR', $toCurrency = 'EUR')
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        $amount = (float) $amount;

        $fromCurrency = strtoupper(trim((string) $fromCurrency));
        $toCurrency = strtoupper(trim((string) $toCurrency));

        if ($fromCurrency === $toCurrency || $amount == 0) {
            return round($amount, 2);
        }

        $rates = $this->getExchangeRates();

        $fromRate = $rates[$fromCurrency] ?? self::DEFAULT_RATES[$fromCurrency] ?? null;
        $toRate = $rates[$toCurrency] ?? self::DEFAULT_RATES[$toCurrency] ?? null;

        if (!$fromRate || !$toRate) {
            return round($amount, 2);
        }

        // Formula: (Amount / FromRate) * ToRate
        // Since rates are based on EUR (EUR = 1.0)
        $amountInBase = $amount / $fromRate;
        $convertedAmount = $amountInBase * $toRate;

        return round($convertedAmount, 2);
    }

    /**
     * Infer local merchant currency from a country or city name.
     */
    public function inferCurrencyFromLocation(?string $country = null, ?string $city = null): string
    {
        $text = strtolower(trim(($country ?? '') . ' ' . ($city ?? '')));

        if ($text === '') {
            return 'EUR';
        }

        if (str_contains($text, 'indonesia') || str_contains($text, 'bali') || str_contains($text, 'jakarta')
            || str_contains($text, 'yogyakarta') || str_contains($text, 'jogja') || str_contains($text, 'bandung')
            || str_contains($text, 'surabaya') || str_contains($text, 'lombok') || str_contains($text, 'kintamani')
            || str_contains($text, 'batur') || str_contains($text, 'sanur') || str_contains($text, 'ubud')
            || str_contains($text, 'seminyak') || str_contains($text, 'canggu') || str_contains($text, 'nusa')
            || str_contains($text, 'denpasar') || str_contains($text, 'medan') || str_contains($text, 'semarang')
            || str_contains($text, 'batam') || str_contains($text, 'bogor') || str_contains($text, 'malang')
            || str_contains($text, 'flores') || str_contains($text, 'komodo') || $text === 'id' || str_ends_with($text, ' id') || str_starts_with($text, 'id ')) {
            return 'IDR';
        }

        if (str_contains($text, 'vietnam') || str_contains($text, 'hanoi') || str_contains($text, 'nha trang')
            || str_contains($text, 'da nang') || str_contains($text, 'ho chi minh') || str_contains($text, 'saigon')
            || str_contains($text, 'phu quoc')) {
            return 'VND';
        }

        if (str_contains($text, 'thailand') || str_contains($text, 'bangkok') || str_contains($text, 'pattaya')
            || str_contains($text, 'phuket') || str_contains($text, 'chiang mai') || str_contains($text, 'krabi')
            || str_contains($text, 'samui')) {
            return 'THB';
        }

        if (str_contains($text, 'philippines') || str_contains($text, 'manila') || str_contains($text, 'boracay')
            || str_contains($text, 'cebu') || str_contains($text, 'palawan')) {
            return 'PHP';
        }

        if (str_contains($text, 'malaysia') || str_contains($text, 'kuala lumpur') || str_contains($text, 'sabah')
            || str_contains($text, 'kota kinabalu') || str_contains($text, 'penang') || str_contains($text, 'langkawi')) {
            return 'MYR';
        }

        if (str_contains($text, 'japan') || str_contains($text, 'tokyo') || str_contains($text, 'osaka')
            || str_contains($text, 'kyoto') || str_contains($text, 'sapporo')) {
            return 'JPY';
        }

        if (str_contains($text, 'singapore')) {
            return 'SGD';
        }

        if (str_contains($text, 'korea') || str_contains($text, 'seoul') || str_contains($text, 'jeju') || str_contains($text, 'busan')) {
            return 'KRW';
        }

        if (str_contains($text, 'taiwan') || str_contains($text, 'taipei')) {
            return 'TWD';
        }

        if (str_contains($text, 'china') || str_contains($text, 'shanghai') || str_contains($text, 'beijing')) {
            return 'CNY';
        }

        if (str_contains($text, 'hong kong')) {
            return 'HKD';
        }

        if (str_contains($text, 'emirates') || str_contains($text, 'dubai') || str_contains($text, 'abu dhabi') || str_contains($text, 'uae')) {
            return 'AED';
        }

        if (str_contains($text, 'saudi') || str_contains($text, 'riyadh') || str_contains($text, 'jeddah')) {
            return 'SAR';
        }

        if (str_contains($text, 'united states') || str_contains($text, 'usa') || str_contains($text, 'new york')
            || str_contains($text, 'las vegas') || str_contains($text, 'orlando') || str_contains($text, 'los angeles')
            || str_contains($text, 'miami') || str_contains($text, 'chicago')) {
            return 'USD';
        }

        if (str_contains($text, 'united kingdom') || str_contains($text, 'london') || str_contains($text, 'edinburgh') || str_contains($text, 'scotland')) {
            return 'GBP';
        }

        if (str_contains($text, 'australia') || str_contains($text, 'sydney') || str_contains($text, 'melbourne') || str_contains($text, 'brisbane')) {
            return 'AUD';
        }

        return 'EUR';
    }

    /**
     * Convert an amount to EUR safely.
     * If the amount is unscaled domestic merchant currency (e.g. 78400 IDR, 225000 VND, 1200 THB),
     * it detects and converts it accurately to EUR.
     */
    public function convertToEur(?float $amount, ?string $currency = 'EUR', ?string $country = null, ?string $city = null): ?float
    {
        if ($amount === null || $amount <= 0) {
            return $amount;
        }

        $currency = strtoupper(trim((string) ($currency ?: 'EUR')));

        // If explicit non-EUR currency given, convert directly
        if ($currency !== 'EUR') {
            return $this->convert($amount, $currency, 'EUR');
        }

        // Location inference
        $inferred = $this->inferCurrencyFromLocation($country, $city);

        if ($inferred === 'IDR' && $amount > 500) {
            return $this->convert($amount, 'IDR', 'EUR');
        }

        if ($inferred === 'VND' && $amount > 500) {
            return $this->convert($amount, 'VND', 'EUR');
        }

        if ($inferred === 'THB' && $amount > 150) {
            return $this->convert($amount, 'THB', 'EUR');
        }

        if ($inferred === 'PHP' && $amount > 150) {
            return $this->convert($amount, 'PHP', 'EUR');
        }

        if ($inferred === 'MYR' && $amount > 100) {
            return $this->convert($amount, 'MYR', 'EUR');
        }

        if ($inferred === 'JPY' && $amount > 300) {
            return $this->convert($amount, 'JPY', 'EUR');
        }

        if ($inferred === 'KRW' && $amount > 1000) {
            return $this->convert($amount, 'KRW', 'EUR');
        }

        if ($inferred === 'TWD' && $amount > 300) {
            return $this->convert($amount, 'TWD', 'EUR');
        }

        // Generic unscaled heuristic if location was unknown or EUR
        if ($amount > 100000) {
            // Extremely likely IDR or VND
            return $this->convert($amount, 'IDR', 'EUR');
        }

        if ($amount > 10000) {
            // Likely IDR / JPY / KRW
            return $this->convert($amount, 'IDR', 'EUR');
        }

        return round($amount, 2);
    }
}
