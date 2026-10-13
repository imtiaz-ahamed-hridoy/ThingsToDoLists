<?php

namespace App\DTOs;

/**
 * One price row = one category for one rate/option within one date-range.
 *
 * This is the BILLING price — what the customer is charged at checkout.
 * As a RESELLER, we use recommendedMerchantSellingPrice as our selling price.
 *
 * Pricing hierarchy (reseller model):
 *   nett_price        = what WE pay the provider (our cost)
 *   selling_price     = what CUSTOMER pays (recommendedMerchantSellingPrice)
 *   original_price    = strike-through price (originalMerchantPrice)
 *   recommended_price = provider's recommended retail (recommendedMerchantSellingPrice)
 *   merchant_price    = merchant's wholesale (nettMerchantPrice)
 *
 * Bokun:     tieredPrices[].amount (already in cents for 1 pax)
 * GlobalTix: recommendedMerchantSellingPrice per ticket type
 * Supplier:  user-entered prices
 */
final class PriceRowDTO
{
    public function __construct(
        public readonly string  $provider,
        public readonly ?string $optionExternalId,     // Links to ProductOptionDTO.externalId
        public readonly ?string $categoryExternalId,   // Links to PricingCategoryDTO.externalId
        public readonly ?string $categoryCode,         // ADULT, CHILD, etc.
        public readonly ?string $categoryLabel,

        // Date range (Bokun pricesByDateRange)
        public readonly ?string $validFrom,
        public readonly ?string $validTo,

        // 💰 Five-tier pricing
        public readonly float   $sellingPrice,          // CUSTOMER pays
        public readonly ?float  $nettPrice,
        public readonly ?float  $originalPrice,
        public readonly ?float  $recommendedPrice,
        public readonly ?float  $merchantPrice,
        public readonly string  $currency,
        public readonly bool    $isConverted = false,

        // Tiered pricing (Bokun volume discounts)
        /** @var TieredPriceDTO[] */
        public readonly array   $tieredPrices = [],

        // Flags
        public readonly bool    $isDefault = false,
        public readonly int     $sortOrder = 0,

        // Tax
        public readonly ?float  $taxAmount = null,
        public readonly ?float  $taxPercentage = null,

        // Discount
        public readonly ?float  $discountPercentage = null,
        public readonly ?float  $discountedPrice = null,
    ) {}

    /**
     * Generate idempotency key for upsert.
     */
    public function idempotencyKey(int $productId): string
    {
        return md5(implode('|', [
            $productId,
            $this->provider,
            $this->optionExternalId ?? '',
            $this->categoryExternalId ?? '',
            $this->validFrom ?? '',
            $this->validTo ?? '',
            $this->sortOrder,
        ]));
    }

    /**
     * Get the comparable price for sorting (lowest = best for "From $X").
     */
    public function comparablePrice(): ?float
    {
        $price = $this->sellingPrice;
        return ($price > 0) ? $price : null;
    }
}
