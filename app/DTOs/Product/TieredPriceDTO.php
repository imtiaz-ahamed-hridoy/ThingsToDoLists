<?php

namespace App\DTOs\Product;

class TieredPriceDTO
{
    public function __construct(
        public int $min_pax,
        public int $max_pax,
        public float $amount,
        public ?float $nett_amount = null,
        public string $currency = 'EUR',
        public bool $is_converted = false
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            min_pax: $data['min_pax'] ?? 1,
            max_pax: $data['max_pax'] ?? 999,
            amount: $data['amount'],
            nett_amount: $data['nett_amount'] ?? null,
            currency: $data['currency'] ?? 'EUR',
            is_converted: $data['is_converted'] ?? false
        );
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
