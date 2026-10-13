<?php

namespace App\DTOs;

/**
 * One tiered price = one volume bracket for a pricing category.
 */
final class TieredPriceDTO
{
    public function __construct(
        public readonly int $minPax,
        public readonly int $maxPax,
        public readonly float $amount,
        public readonly ?float $nettAmount,
        public readonly string $currency,
        public readonly ?float $taxAmount = null,
        public readonly bool $isConverted = false,
        public readonly ?float $conversionRate = null,
        public readonly int $sortOrder = 0,
    ) {}

    public function matchesPaxCount(int $paxCount): bool
    {
        return $paxCount >= $this->minPax && $paxCount <= $this->maxPax;
    }

    public function toDatabaseRow(int $pricingCategoryId): array
    {
        return [
            'pricing_category_id' => $pricingCategoryId,
            'min_pax' => $this->minPax,
            'max_pax' => $this->maxPax,
            'amount' => $this->amount,
            'nett_amount' => $this->nettAmount,
            'currency' => $this->currency,
            'tax_amount' => $this->taxAmount,
            'sort_order' => $this->sortOrder,
        ];
    }
}
