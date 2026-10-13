<?php

namespace App\DTOs;

/**
 * One availability category price = exact price for one category on one date+time slot.
 */
final class AvailabilityCategoryPriceDTO
{
    public function __construct(
        public readonly string $categoryExternalId,
        public readonly string $categoryCode,
        public readonly string $categoryLabel,
        public readonly float $sellingPrice,
        public readonly ?float $nettPrice,
        public readonly ?float $originalPrice = null,
        public readonly ?float $recommendedPrice = null,
        public readonly ?float $merchantPrice = null,
        public readonly ?float $taxAmount = null,
        public readonly ?int $availableQty = null,
        public readonly ?int $minQty = null,
        public readonly ?int $maxQty = null,
        public readonly array $metadata = [],
        public readonly string $currency = 'EUR',
    ) {}

    public function toDatabaseRow(int $availabilityId, ?int $pricingCategoryId): array
    {
        return [
            'availability_id' => $availabilityId,
            'pricing_category_id' => $pricingCategoryId,
            'category_external_id' => $this->categoryExternalId,
            'category_code' => $this->categoryCode,
            'category_label' => $this->categoryLabel,
            'selling_price' => $this->sellingPrice,
            'nett_price' => $this->nettPrice,
            'original_price' => $this->originalPrice,
            'recommended_price' => $this->recommendedPrice,
            'merchant_price' => $this->merchantPrice,
            'tax_amount' => $this->taxAmount,
            'currency' => $this->currency,
            'available_qty' => $this->availableQty,
            'min_qty' => $this->minQty,
            'max_qty' => $this->maxQty,
            'metadata' => $this->metadata ?: null,
        ];
    }
}
