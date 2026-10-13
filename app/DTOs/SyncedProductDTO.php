<?php

namespace App\DTOs;

/**
 * Master DTO carrying ALL normalized data for a product from any provider.
 * This is the single source of truth passed to the PersistenceService.
 *
 * One SyncedProductDTO = everything needed to write to all tables:
 *   products, product_options, option_pricing_categories,
 *   prices, tiered_prices, availability, availability_category_prices,
 *   vendors, locations, cancellation_policies, keywords, media
 */
final class SyncedProductDTO
{
    public function __construct(
        public readonly string  $provider,
        public readonly string  $productApiId,

        // Vendor
        public readonly VendorDTO $vendor,

        // Core product fields
        public readonly string  $title,
        public readonly ?string $description,
        public readonly ?string $thumbnail,
        public readonly ?string $barcodeType,
        public readonly ?string $categoryName,

        // Duration
        public readonly ?string $duration,
        public readonly ?string $durationType,
        public readonly ?int    $durationMinutes,
        public readonly ?int    $durationHours,
        public readonly ?int    $durationDays,
        public readonly ?int    $durationWeeks,
        public readonly ?string $durationText,

        // Classification
        public readonly ?string $activityType,
        public readonly ?string $scheduleType,
        public readonly ?string $bookingType,
        public readonly ?string $difficulty,
        public readonly ?string $meetingType,

        // Cutoff
        public readonly ?string $cutoffType,
        public readonly ?int    $cutoffReferenceHour,
        public readonly ?int    $cutoffReferenceMinute,
        public readonly ?int    $bookingCutoffMinutes,
        public readonly ?int    $bookingCutoffHours,
        public readonly ?int    $bookingCutoffDays,
        public readonly ?int    $bookingCutoffWeeks,

        // Capacity
        public readonly ?string $capacityType,
        public readonly ?int    $passCapacity,
        public readonly ?int    $passesAvailable,

        // Display pricing (TIER 1 — product cards)
        public readonly ?float  $originalPrice,
        public readonly ?float  $fromPrice,
        public readonly string  $defaultCurrency,

        // Flags
        public readonly bool    $isInstantConfirmation,
        public readonly bool    $isCancellable,
        public readonly bool    $isPrivate,
        public readonly bool    $isPickupAvailable,
        public readonly bool    $isPassportRequired,
        public readonly bool    $allowCustomizedBookings,
        public readonly bool    $published,

        // Content arrays
        public readonly array   $inclusions,
        public readonly array   $exclusions,
        public readonly array   $highlights,
        public readonly array   $knowBeforeYouGo,
        public readonly array   $itinerary,
        public readonly array   $activityCategories,
        public readonly array   $activityAttributes,
        public readonly array   $startTimes,
        public readonly array   $guidanceTypes,
        public readonly array   $bookableExtras,
        public readonly array   $startPoints,
        public readonly array   $keywords,

        // Media
        public readonly array   $photos,
        public readonly array   $videos,

        // Location
        public readonly ?LocationDTO $location,

        // Cancellation
        public readonly ?CancellationPolicyDTO $cancellationPolicy,

        // ─── NORMALIZED PRICING DATA ────────────────────────────────────

        /** @var ProductOptionDTO[] — Options (rates/variants) with their categories */
        public readonly array   $options,

        /** @var PriceRowDTO[] — Category billing prices per date-range */
        public readonly array   $priceRows,

        /** @var AvailabilitySlotDTO[] — Date-wise availability with category prices */
        public readonly array   $availabilitySlots,

        // Provider-specific extras
        public readonly ?string $timeZone,
        public readonly ?string $timezoneOffset,
        public readonly ?int    $minAge,
        public readonly ?string $dressCode,
        public readonly ?string $defaultRateId,
        public readonly ?string $pickupService,
        public readonly ?string $dropoffService,
        public readonly ?string $whatToExpect = null,
        public readonly ?string $termsAndConditions = null,
        public readonly ?string $operatingHours = null,
        public readonly array   $thingsToNote = [],
        public readonly array   $howToUse = [],
        public readonly array   $cancellationNotes = [],
        public readonly ?string $addressLine = null,
        public readonly ?string $postalCode = null,
        public readonly ?string $merchantName = null,
        public readonly bool    $hasAvailabilityData = true,

        // Raw response for debugging
        public readonly array   $rawResponse = [],
    ) {}

    /**
     * Calculate both display from_price and original_price using Adult-first/Child-second hierarchy.
     */
    public function calculatedDisplayPrices(): array
    {
        $allCategories = [];
        if (!empty($this->options)) {
            foreach ($this->options as $opt) {
                if (!empty($opt->pricingCategories)) {
                    foreach ($opt->pricingCategories as $cat) {
                        $allCategories[] = $cat;
                    }
                }
            }
        }

        if (!empty($allCategories)) {
            $resolved = \App\Services\Sync\PriceCalculator::resolveProductDisplayPrices(
                $allCategories,
                $this->fromPrice,
                $this->originalPrice
            );
            if ($resolved['from_price'] !== null && $resolved['from_price'] > 0) {
                return $resolved;
            }
        }

        if (!empty($this->priceRows)) {
            $resolved = \App\Services\Sync\PriceCalculator::resolveProductDisplayPrices(
                $this->priceRows,
                $this->fromPrice,
                $this->originalPrice
            );
            if ($resolved['from_price'] !== null && $resolved['from_price'] > 0) {
                return $resolved;
            }
        }

        $fromPrice = $this->fromPrice;
        $origPrice = $this->originalPrice;
        if ($fromPrice !== null && $fromPrice > 0) {
            if ($origPrice === null || $origPrice <= 0 || $origPrice > ($fromPrice * 5)) {
                $origPrice = $fromPrice;
            }
        }

        return [
            'from_price'     => $fromPrice,
            'original_price' => $origPrice ?? $fromPrice,
        ];
    }

    /**
     * Calculate the display "from_price" from the lowest category price.
     */
    public function calculatedFromPrice(): ?float
    {
        return $this->calculatedDisplayPrices()['from_price'];
    }

    /**
     * Check if this product has valid availability slots.
     */
    public function hasAvailability(): bool
    {
        return count($this->availabilitySlots) > 0;
    }
}
