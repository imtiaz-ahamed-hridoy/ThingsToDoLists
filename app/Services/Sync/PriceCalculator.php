<?php

namespace App\Services\Sync;

use App\Services\GlobalTix\GlobalTixPriceSyncService;

/**
 * Resolves prices from raw provider data into clean numeric values.
 *
 * As a RESELLER, our priority is:
 *   1. recommendedMerchantSellingPrice (what we sell to the customer)
 *   2. nettMerchantPrice (what we pay)
 *   3. originalMerchantPrice (list/strike-through price)
 *
 * For Bokun: uses tieredPrices[].amount (already final price)
 */
final class PriceCalculator
{
    /**
     * Resolve the SELLING price for a GlobalTix ticket type.
     * Uses the converted/requested transaction currency selling price first.
     */
    public static function resolveGlobalTixSellingPrice(array $ticket, ?string $targetCurrency = 'EUR', ?string $country = null, ?string $city = null): ?float
    {
        $selling = self::firstNumeric([
            $ticket['recommendedSellingPrice'] ?? null,
            $ticket['sellingPrice'] ?? null,
            $ticket['price'] ?? null,
            $ticket['minimumSellingPrice'] ?? null,
            $ticket['nettPrice'] ?? null,
        ]);

        if ($selling === null || $selling <= 0) {
            $selling = self::firstNumeric([
                $ticket['recommendedMerchantSellingPrice'] ?? null,
                $ticket['minimumMerchantSellingPrice'] ?? null,
                $ticket['nettMerchantPrice'] ?? null,
            ]);
        }

        if ($selling !== null && $selling > 0) {
            $currency = strtoupper((string) ($ticket['currency'] ?? $targetCurrency ?? 'EUR'));
            if ($currency === 'IDR' || $selling > 150) {
                $cs = app(\App\Services\CurrencyService::class);
                $converted = $cs->convertToEur($selling, $currency, $country, $city);
                return ($converted !== null && $converted > 0) ? $converted : $selling;
            }
            return $selling;
        }

        return null;
    }

    /**
     * Resolve the NETT (cost) price for a GlobalTix ticket type.
     */
    public static function resolveGlobalTixNettPrice(array $ticket, ?string $targetCurrency = 'EUR', ?string $country = null, ?string $city = null): ?float
    {
        $nett = self::firstNumeric([
            $ticket['nettPrice'] ?? null,
            $ticket['directContractPrice'] ?? null,
        ]);

        if ($nett === null || $nett <= 0) {
            $nett = self::firstNumeric([
                $ticket['nettMerchantPrice'] ?? null,
                $ticket['directMerchantContractPrice'] ?? null,
            ]);
        }

        if ($nett !== null && $nett > 0) {
            $currency = strtoupper((string) ($ticket['currency'] ?? $targetCurrency ?? 'EUR'));
            if ($currency === 'IDR' || $nett > 150) {
                $cs = app(\App\Services\CurrencyService::class);
                $converted = $cs->convertToEur($nett, $currency, $country, $city);
                return ($converted !== null && $converted > 0) ? $converted : $nett;
            }
            return $nett;
        }

        return null;
    }

    /**
     * Resolve the ORIGINAL (list/strike-through) price for a GlobalTix ticket.
     */
    public static function resolveGlobalTixOriginalPrice(array $ticket, ?string $targetCurrency = 'EUR', ?string $country = null, ?string $city = null): ?float
    {
        $orig = self::firstNumeric([
            $ticket['originalPrice'] ?? null,
            $ticket['recommendedSellingPrice'] ?? null,
            $ticket['sellingPrice'] ?? null,
        ]);

        if ($orig === null || $orig <= 0) {
            $orig = self::firstNumeric([
                $ticket['originalMerchantPrice'] ?? null,
                $ticket['recommendedMerchantSellingPrice'] ?? null,
            ]);
        }

        if ($orig !== null && $orig > 0) {
            $currency = strtoupper((string) ($ticket['currency'] ?? $targetCurrency ?? 'EUR'));
            if ($currency === 'IDR' || $orig > 150) {
                $cs = app(\App\Services\CurrencyService::class);
                $converted = $cs->convertToEur($orig, $currency, $country, $city);
                return ($converted !== null && $converted > 0) ? $converted : $orig;
            }
            return $orig;
        }

        return null;
    }

    /**
     * Resolve the recommended retail price.
     */
    public static function resolveGlobalTixRecommendedPrice(array $ticket, ?string $targetCurrency = 'EUR', ?string $country = null, ?string $city = null): ?float
    {
        $rec = self::firstNumeric([
            $ticket['recommendedSellingPrice'] ?? null,
            $ticket['sellingPrice'] ?? null,
            $ticket['recommendedMerchantSellingPrice'] ?? null,
        ]);

        if ($rec !== null && $rec > 0) {
            $currency = strtoupper((string) ($ticket['currency'] ?? $targetCurrency ?? 'EUR'));
            if ($currency === 'IDR' || $rec > 150) {
                $cs = app(\App\Services\CurrencyService::class);
                $converted = $cs->convertToEur($rec, $currency, $country, $city);
                return ($converted !== null && $converted > 0) ? $converted : $rec;
            }
            return $rec;
        }

        return null;
    }

    /**
     * Resolve price from a Bokun passenger entry.
     * Bokun uses tieredPrices with min/max passenger counts.
     */
    public static function resolveBokunPassengerPrice(array $passenger, ?float $fallback = null): ?float
    {
        // Direct price first
        $direct = self::firstNumeric([
            data_get($passenger, 'price.amount'),
            $passenger['amount'] ?? null,
            $passenger['nettPrice'] ?? null,
            $passenger['recommendedSellingPrice'] ?? null,
        ]);

        if ($direct !== null) {
            return $direct;
        }

        // Resolve from tiered pricing (pick the tier for 1 person)
        return self::resolveTieredPrice(
            $passenger['tieredPrices'] ?? $passenger['tiered_prices'] ?? [],
            $fallback
        );
    }

    /**
     * Pick the best price from a tiered pricing array for 1 person.
     */
    public static function resolveTieredPrice(array $tiers, ?float $fallback = null): ?float
    {
        if (empty($tiers)) {
            return $fallback;
        }

        $normalized = [];
        foreach ($tiers as $tier) {
            if (!is_array($tier)) {
                continue;
            }

            $amount = self::firstNumeric([
                data_get($tier, 'price.amount'),
                $tier['amount'] ?? null,
                $tier['recommendedSellingPrice'] ?? null,
            ]);

            if ($amount === null) {
                continue;
            }

            $normalized[] = [
                'amount' => $amount,
                'min'    => (int) ($tier['minPassengersRequired'] ?? $tier['minPurchaseQty'] ?? PHP_INT_MAX),
                'max'    => (int) ($tier['maxPassengersRequired'] ?? $tier['maxPurchaseQty'] ?? PHP_INT_MAX),
            ];
        }

        if (empty($normalized)) {
            return $fallback;
        }

        // Sort by min ascending
        usort($normalized, fn($a, $b) => [$a['min'], $a['max']] <=> [$b['min'], $b['max']]);

        // Prefer tier that includes 1 person
        foreach ($normalized as $tier) {
            if ($tier['min'] <= 1 && $tier['max'] >= 1) {
                return $tier['amount'];
            }
        }

        // Fallback to first tier
        return $normalized[0]['amount'] ?? $fallback;
    }

    /**
     * Resolve the display from_price and original_price from a collection or array of categories/tickets/prices.
     *
     * Rules:
     * 1. Check if ADULT tickets with selling_price > 0 exist -> take lowest adult selling_price and matching original_price.
     * 2. If no adult, check if CHILD tickets with selling_price > 0 exist -> take lowest child selling_price and matching original_price.
     * 3. If neither, take lowest selling_price > 0 among all categories.
     * 4. Fallback to candidate fromPrice/originalPrice.
     *
     * @param iterable $categories
     * @param float|null $fallbackFromPrice
     * @param float|null $fallbackOriginalPrice
     * @return array{from_price: ?float, original_price: ?float}
     */
    public static function resolveProductDisplayPrices(iterable $categories, ?float $fallbackFromPrice = null, ?float $fallbackOriginalPrice = null, ?string $country = null, ?string $city = null): array
    {
        return app(GlobalTixPriceSyncService::class)->resolveFromPrice(
            $categories,
            $fallbackFromPrice,
            $fallbackOriginalPrice,
            $country,
            $city
        );
    }

    /**
     * Return the first non-null numeric value from a list.
     */
    public static function firstNumeric(array $candidates): ?float
    {
        foreach ($candidates as $val) {
            if (is_numeric($val)) {
                return (float) $val;
            }
        }
        return null;
    }
}
