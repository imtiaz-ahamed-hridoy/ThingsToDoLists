<?php

namespace App\DTOs\Product;

class AvailabilityCategoryPriceDTO
{
    public function __construct(
        public string $category_code,
        public float $selling_price,
        public ?float $nett_price = null,
        public ?float $original_price = null,
        public string $currency = 'EUR',
        public ?string $category_label = null,
        public ?int $pricing_category_id = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            category_code: $data['category_code'],
            selling_price: $data['selling_price'],
            nett_price: $data['nett_price'] ?? null,
            original_price: $data['original_price'] ?? null,
            currency: $data['currency'] ?? 'EUR',
            category_label: $data['category_label'] ?? null,
            pricing_category_id: $data['pricing_category_id'] ?? null
        );
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
