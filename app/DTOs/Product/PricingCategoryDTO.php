<?php

namespace App\DTOs\Product;

class PricingCategoryDTO
{
    public function __construct(
        public string $category_code,
        public string $category_label,
        public ?string $external_id = null,
        public ?int $age_from = null,
        public ?int $age_to = null,
        public int $occupancy = 1,
        public int $group_size = 0,
        public ?int $min_qty = null,
        public ?int $max_qty = null,
        public bool $is_default = false,
        public bool $is_age_qualified = true,
        public int $sort_order = 0
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            category_code: $data['category_code'],
            category_label: $data['category_label'],
            external_id: $data['external_id'] ?? null,
            age_from: $data['age_from'] ?? null,
            age_to: $data['age_to'] ?? null,
            occupancy: $data['occupancy'] ?? 1,
            group_size: $data['group_size'] ?? 0,
            min_qty: $data['min_qty'] ?? null,
            max_qty: $data['max_qty'] ?? null,
            is_default: $data['is_default'] ?? false,
            is_age_qualified: $data['is_age_qualified'] ?? true,
            sort_order: $data['sort_order'] ?? 0
        );
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
