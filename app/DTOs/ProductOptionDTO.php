<?php

namespace App\DTOs;

/**
 * One product option = Bokun rate / GlobalTix option / Supplier variant.
 */
final class ProductOptionDTO
{
    public function __construct(
        public readonly string $provider,
        public readonly string $externalId,
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly bool $isDefault = false,
        public readonly int $sortOrder = 0,

        /** @var PricingCategoryDTO[] */
        public readonly array $categories = [],

        // Option-level commerce fields
        public readonly ?string $currency = null,
        public readonly ?string $ticketFormat = null,
        public readonly ?string $ticketValidity = null,
        public readonly ?string $definedDuration = null,
        public readonly bool $isDynamicPricing = false,
        public readonly bool $isCapacity = false,
        public readonly bool $isCancellable = true,
        public readonly ?array $timeSlot = null,
        public readonly ?array $visitDate = null,
        public readonly ?array $advanceBooking = null,
        public readonly ?array $questions = null,
        public readonly ?array $inclusions = null,
        public readonly ?array $exclusions = null,
        public readonly ?string $cancellationPolicy = null,
        public readonly ?array $cancellationNotes = null,
        public readonly ?string $publishStart = null,
        public readonly ?string $publishEnd = null,
        public readonly ?string $redeemStart = null,
        public readonly ?string $redeemEnd = null,

        // Provider-specific metadata
        public readonly array $metadata = [],
    ) {}
}
