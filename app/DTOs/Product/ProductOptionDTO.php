<?php

namespace App\DTOs\Product;

class ProductOptionDTO
{
    public function __construct(
        public string $provider,
        public string $name,
        public ?string $external_id = null,
        public ?string $description = null,
        public bool $is_default = false,
        public int $sort_order = 0,
        public ?array $metadata = null,
        /** @var PricingCategoryDTO[] */
        public array $pricing_categories = []
    ) {}

    public static function fromArray(array $data): self
    {
        $categories = [];
        if (!empty($data['pricing_categories'])) {
            foreach ($data['pricing_categories'] as $cat) {
                $categories[] = PricingCategoryDTO::fromArray($cat);
            }
        }

        return new self(
            provider: $data['provider'],
            name: $data['name'],
            external_id: $data['external_id'] ?? null,
            description: $data['description'] ?? null,
            is_default: $data['is_default'] ?? false,
            sort_order: $data['sort_order'] ?? 0,
            metadata: $data['metadata'] ?? null,
            pricing_categories: $categories
        );
    }

    public function toArray(): array
    {
        $array = get_object_vars($this);
        $array['pricing_categories'] = array_map(fn($c) => $c->toArray(), $this->pricing_categories);
        return $array;
    }
}
