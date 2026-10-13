<?php

namespace App\DTOs;

/**
 * One availability slot = one date + time for one option.
 *
 * Bokun:     availabilities[] with date, startTime, availabilityCount, pricesByCategory
 * GlobalTix: availability[].data[] with date, nettPrice, recommendedSellingPrice, ticket_type_id
 * Supplier:  user-entered date/time slots
 *
 * Category-specific prices are now in AvailabilityCategoryPriceDTO[] instead of flat columns.
 */
final class AvailabilitySlotDTO
{
    public function __construct(
        public readonly string  $provider,
        public readonly ?string $externalId,          // Bokun availability.id / GlobalTix session.id
        public readonly ?string $optionExternalId,     // Links to ProductOptionDTO.externalId
        public readonly string  $availableDate,
        public readonly ?string $startTime,
        public readonly ?string $endTime,
        public readonly ?string $timeZone,

        // Capacity
        public readonly int     $totalCapacity,
        public readonly int     $bookedCount,
        public readonly bool    $isUnlimited,
        public readonly bool    $isSoldOut,

        // Display price (cheapest category for this slot)
        public readonly ?float  $displayPrice,
        public readonly ?string $currency,

        // Category-specific prices for this slot
        /** @var AvailabilityCategoryPriceDTO[] */
        public readonly array   $categoryPrices = [],

        // Optional params with defaults
        public readonly int     $minParticipants = 1,
        public readonly ?int    $maxParticipants = null,

        // Flags
        public readonly bool    $isActive = true,
        public readonly bool    $isClosed = false,
        public readonly ?string $seriesId = null,
        public readonly ?string $seriesName = null,

        // Raw data for debugging
        public readonly array   $rawData = [],
    ) {}

    /**
     * Generate idempotency key for upsert.
     */
    public function idempotencyKey(int $productId): string
    {
        return md5(implode('|', [
            $productId,
            $this->provider,
            $this->externalId ?? '',
            $this->optionExternalId ?? '',
            $this->availableDate,
            $this->startTime ?? '00:00:00',
        ]));
    }
}
