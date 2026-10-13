<?php

namespace App\Traits;

use App\Models\Wishlist;
use App\Services\CurrencyService;

trait AppliesProductRequestState
{
    protected function applyProductRequestState(array $payload): array
    {
        $wishlistedIds = $this->requestWishlistedProductIds();
        $currency = $this->requestCurrency();

        $this->applyProductRequestStateToNode($payload, $wishlistedIds, $currency);

        return $payload;
    }

    protected function appendWishlistStatus(array &$payload): void
    {
        $payload = $this->applyProductRequestState($payload);
    }

    protected function requestCurrency(): string
    {
        return strtoupper((string) (app()->bound('user.currency') ? app('user.currency') : request()->query('currency', 'EUR')));
    }

    protected function requestWishlistedProductIds(): array
    {
        $request = request();

        if ($request->attributes->has('wishlist_ids')) {
            return $request->attributes->get('wishlist_ids');
        }

        $userId = auth('sanctum')->id();
        $guestId = $request->header('X-Guest-Wishlist');

        if (!$userId && !$guestId) {
            $request->attributes->set('wishlist_ids', []);
            return [];
        }

        $query = Wishlist::query();

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('guest_id', $guestId)->whereNull('user_id');
        }

        $wishlistedIds = $query->pluck('product_id')->flip()->toArray();
        $request->attributes->set('wishlist_ids', $wishlistedIds);

        return $wishlistedIds;
    }

    private function applyProductRequestStateToNode(array &$node, array $wishlistedIds, string $requestedCurrency): void
    {
        $this->applyWishlistState($node, $wishlistedIds);
        $this->applyCurrencyState($node, $requestedCurrency);

        foreach ($node as &$value) {
            if (is_array($value)) {
                $this->applyProductRequestStateToNode($value, $wishlistedIds, $requestedCurrency);
            } elseif ($value instanceof \Illuminate\Support\Collection) {
                $value = $value->map(function ($item) use ($wishlistedIds, $requestedCurrency) {
                    if (is_array($item)) {
                        $this->applyProductRequestStateToNode($item, $wishlistedIds, $requestedCurrency);
                    }

                    return $item;
                });
            }
        }

        unset($value);
    }

    private function applyWishlistState(array &$node, array $wishlistedIds): void
    {
        if (!array_key_exists('is_wishlisted', $node) && !$this->isProductPayloadNode($node)) {
            return;
        }

        $productId = $node['product_id'] ?? $node['id'] ?? null;
        $node['is_wishlisted'] = $productId !== null && isset($wishlistedIds[(int) $productId]);
    }

    private function applyCurrencyState(array &$node, string $requestedCurrency): void
    {
        $baseCurrency = $this->nodeBaseCurrency($node);

        if (!$baseCurrency) {
            return;
        }

        foreach (['from_price', 'original_price', 'price', 'fromPrice', 'originalPrice', 'listingPrice', 'originalListingPrice', 'finalPrice', 'minimumPayablePrice'] as $key) {
            if (array_key_exists($key, $node)) {
                $node[$key] = $this->convertRequestPrice($node[$key], $baseCurrency, $requestedCurrency);
            }
        }

        if (array_key_exists('currencyCode', $node)) {
            $node['currencyCode'] = $requestedCurrency;
        }

        if (array_key_exists('currency', $node)) {
            $node['currency'] = $requestedCurrency;
        }

        unset($node['default_currency']);
    }

    private function nodeBaseCurrency(array $node): ?string
    {
        $currency = $node['default_currency']
            ?? $node['currencyCode']
            ?? (array_key_exists('from_price', $node) || array_key_exists('price', $node) ? ($node['currency'] ?? null) : null);

        $currency = strtoupper(trim((string) $currency));

        return $currency !== '' ? $currency : null;
    }

    private function isProductPayloadNode(array $node): bool
    {
        if (!array_key_exists('id', $node)) {
            return false;
        }

        return array_key_exists('product_id', $node)
            || array_key_exists('default_currency', $node)
            || array_key_exists('from_price', $node)
            || array_key_exists('price', $node)
            || (array_key_exists('title', $node) && array_key_exists('slug', $node));
    }

    private function convertRequestPrice(mixed $amount, string $fromCurrency, string $toCurrency): ?float
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return app(CurrencyService::class)->convert((float) $amount, $fromCurrency, $toCurrency);
    }
}
