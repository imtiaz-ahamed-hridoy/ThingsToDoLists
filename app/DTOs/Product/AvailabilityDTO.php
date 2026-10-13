<?php

namespace App\DTOs\Product;

class AvailabilityDTO
{
    public function __construct(
        public string $provider,
        public string $available_date,
        public ?string $external_id = null,
        public ?string $start_time = null,
        public ?string $end_time = null,
        public ?string $start_at = null,
        public ?string $time_zone = null,
        public int $capacity_total = 0,
        public int $capacity_booked = 0,
        public int $capacity_reserved = 0,
        public bool $unlimited_availability = false,
        public bool $sold_out = false,
        public int $min_participants = 1,
        public ?int $max_participants = null,
        public ?float $display_price = null,
        public ?string $currency = null,
        public bool $is_active = true,
        public bool $is_closed = false,
        public ?array $raw_data = null,
        /** @var AvailabilityCategoryPriceDTO[] */
        public array $category_prices = []
    ) {}

    public static function fromArray(array $data): self
    {
        $prices = [];
        if (!empty($data['category_prices'])) {
            foreach ($data['category_prices'] as $price) {
                $prices[] = AvailabilityCategoryPriceDTO::fromArray($price);
            }
        }

        return new self(
            provider: $data['provider'],
            available_date: $data['available_date'],
            external_id: $data['external_id'] ?? null,
            start_time: $data['start_time'] ?? null,
            end_time: $data['end_time'] ?? null,
            start_at: $data['start_at'] ?? null,
            time_zone: $data['time_zone'] ?? null,
            capacity_total: $data['capacity_total'] ?? 0,
            capacity_booked: $data['capacity_booked'] ?? 0,
            capacity_reserved: $data['capacity_reserved'] ?? 0,
            unlimited_availability: $data['unlimited_availability'] ?? false,
            sold_out: $data['sold_out'] ?? false,
            min_participants: $data['min_participants'] ?? 1,
            max_participants: $data['max_participants'] ?? null,
            display_price: $data['display_price'] ?? null,
            currency: $data['currency'] ?? null,
            is_active: $data['is_active'] ?? true,
            is_closed: $data['is_closed'] ?? false,
            raw_data: $data['raw_data'] ?? null,
            category_prices: $prices
        );
    }

    public function toArray(): array
    {
        $array = get_object_vars($this);
        $array['category_prices'] = array_map(fn($p) => $p->toArray(), $this->category_prices);
        return $array;
    }
}
