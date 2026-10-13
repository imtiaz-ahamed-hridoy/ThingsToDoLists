<?php

namespace App\DTOs;

/**
 * One pricing category = one ticket type within an option.
 */
final class PricingCategoryDTO
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $categoryCode,
        public readonly string $categoryLabel,
        public readonly ?int $ageFrom = null,
        public readonly ?int $ageTo = null,
        public readonly int $occupancy = 1,
        public readonly int $groupSize = 0,
        public readonly ?int $minPurchaseQty = null,
        public readonly ?int $maxPurchaseQty = null,
        public readonly ?float $sellingPrice = null,
        public readonly ?float $nettPrice = null,
        public readonly ?float $originalPrice = null,
        public readonly ?float $recommendedPrice = null,
        public readonly ?float $merchantPrice = null,
        public readonly string $currency = 'EUR',
        public readonly bool $isDefault = false,
        public readonly bool $isAgeQualified = true,
        public readonly int $sortOrder = 0,
    ) {}
}
