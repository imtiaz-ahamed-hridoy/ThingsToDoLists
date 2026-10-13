<?php

namespace App\DTOs;

/**
 * Normalized vendor/merchant data from any provider.
 *
 * Bokun:     vendor.title, vendor.id, vendor.currencyCode
 * GlobalTix: merchant.name, merchant.id, currency
 * Supplier:  user-entered business name
 */
final class VendorDTO
{
    public function __construct(
        public readonly string  $provider,
        public readonly string  $name,
        public readonly ?string $externalId,
        public readonly string  $currency = 'EUR',
        public readonly float   $rating = 0,
        public readonly int     $reviews = 0,
        public readonly ?string $link = null,
        public readonly ?string $logo = null,
        public readonly array   $metadata = [],
    ) {}

    public static function fromBokunData(array $data): self
    {
        return new self(
            provider:   'bokun',
            name:       data_get($data, 'vendor.title', data_get($data, 'vendor.name', 'Bokun Vendor')),
            externalId: (string) data_get($data, 'vendor.id'),
            currency:   data_get($data, 'vendor.currencyCode', 'EUR'),
            rating:     0,
            reviews:    0,
        );
    }

    public static function fromGlobalTixData(array $data): self
    {
        $merchant = $data['merchant'] ?? $data['vendor'] ?? [];

        return new self(
            provider:   'globaltix',
            name:       $merchant['name'] ?? 'GlobalTix Merchant',
            externalId: isset($merchant['id']) ? (string) $merchant['id'] : null,
            currency:   $data['currency'] ?? 'EUR',
            rating:     (float) ($data['reviewRating'] ?? 0),
            reviews:    (int) ($data['reviewCount'] ?? 0),
        );
    }

    public static function fromSupplierData(array $data): self
    {
        return new self(
            provider:   'supplier',
            name:       $data['vendor_name'] ?? $data['business_name'] ?? 'Supplier',
            externalId: null,
            currency:   $data['currency'] ?? 'EUR',
        );
    }
}
