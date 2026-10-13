<?php

namespace App\DTOs\Product;

class PricingDTO
{
    public function __construct(
        public string $provider,
        public ?string $valid_from = null,
        public ?string $valid_to = null,
        public float $selling_price = 0,
        public ?float $nett_price = null,
        public ?float $original_price = null,
        public ?float $recommended_price = null,
        public ?float $merchant_price = null,
        public string $currency = 'EUR',
        public bool $is_price_converted = false,
        public bool $is_default = false,
        public int $sort_order = 0,
        /** @var TieredPriceDTO[] */
        public array $tiered_prices = []
    ) {}

    public static function fromArray(array $data): self
    {
        $tiers = [];
        if (!empty($data['tiered_prices'])) {
            foreach ($data['tiered_prices'] as $tier) {
                $tiers[] = TieredPriceDTO::fromArray($tier);
            }
        }

        return new self(
            provider: $data['provider'],
            valid_from: $data['valid_from'] ?? null,
            valid_to: $data['valid_to'] ?? null,
            selling_price: $data['selling_price'] ?? 0,
            nett_price: $data['nett_price'] ?? null,
            original_price: $data['original_price'] ?? null,
            recommended_price: $data['recommended_price'] ?? null,
            merchant_price: $data['merchant_price'] ?? null,
            currency: $data['currency'] ?? 'EUR',
            is_price_converted: $data['is_price_converted'] ?? false,
            is_default: $data['is_default'] ?? false,
            sort_order: $data['sort_order'] ?? 0,
            tiered_prices: $tiers
        );
    }

    public function toArray(): array
    {
        $array = get_object_vars($this);
        $array['tiered_prices'] = array_map(fn($t) => $t->toArray(), $this->tiered_prices);
        return $array;
    }
}
