<?php

namespace App\Services\Sync;

use App\DTOs\AvailabilityCategoryPriceDTO;
use App\DTOs\AvailabilitySlotDTO;
use App\DTOs\CancellationPolicyDTO;
use App\DTOs\LocationDTO;
use App\DTOs\PriceRowDTO;
use App\DTOs\PricingCategoryDTO;
use App\DTOs\ProductOptionDTO;
use App\DTOs\SyncedProductDTO;
use App\DTOs\TieredPriceDTO;
use App\DTOs\VendorDTO;

/**
 * Converts raw Bokun API response into a SyncedProductDTO.
 *
 * Bokun pricing structure:
 *   rates[] → options (each rate = one bookable option)
 *   rates[].passengers[] → categories (ADULT/CHILD/INFANT per rate)
 *   pricesByDateRange[].rates[].passengers[].tieredPrices[] → volume tiers
 *   availabilities[].pricesByCategory → per-slot category prices
 *
 * As a RESELLER, the tiered price amounts ARE the selling prices.
 */
final class BokunNormalizer
{
    public function normalize(array $rawApiData): SyncedProductDTO
    {
        $activity       = $rawApiData['data']['activity'] ?? [];
        $priceList      = $rawApiData['data']['priceList'] ?? [];
        $availabilities = $rawApiData['data']['availabilities'] ?? [];

        // Parse media
        [$photos, $videos, $thumbnail] = $this->parseMedia($activity);

        // Parse content arrays
        $inclusions = $this->parseHtmlList($activity['included'] ?? '');
        $exclusions = $this->parseHtmlList($activity['excluded'] ?? '');

        // Parse keywords
        $keywords = $this->parseKeywords($activity['keywords'] ?? null);

        // Currency
        $currency = data_get(
            $activity,
            'nextDefaultPriceMoney.currency',
            data_get(
                $priceList,
                'defaultCurrency',
                data_get($activity, 'vendor.currencyCode', 'EUR')
            )
        );

        $isConverted = (bool) ($priceList['isPriceConverted'] ?? false);

        // ─── Step 1: Build Options + Categories from rates & pricingCategories
        [$options, $categoryMap] = $this->buildOptionsAndCategories($activity);

        // ─── Step 2: Build Price Rows from pricesByDateRange
        $priceRows = $this->buildPriceRows($activity, $priceList, $currency, $isConverted);

        // ─── Step 3: Build Availability Slots with category prices
        $availSlots = $this->buildAvailabilitySlots($activity, $availabilities, $currency);

        // Display pricing
        $originalPrice = PriceCalculator::firstNumeric([
            $activity['originalDefaultPrice'] ?? null,
            data_get($activity, 'nextDefaultPriceMoney.amount'),
            $activity['nextDefaultPrice'] ?? null,
        ]);

        $fromPrice = PriceCalculator::firstNumeric([
            data_get($activity, 'nextDefaultPriceMoney.amount'),
            $activity['nextDefaultPrice'] ?? null,
        ]);

        // Cancellation
        $isCancellable = !in_array(
            strtolower((string) data_get($activity, 'cancellationPolicy.policyType')),
            ['non_refundable', 'non-refundable'],
            true
        );

        return new SyncedProductDTO(
            provider: 'bokun',
            productApiId: (string) ($activity['id'] ?? ''),
            vendor: VendorDTO::fromBokunData($activity),
            title: $activity['title'] ?? 'Untitled Activity',
            description: $activity['description'] ?? null,
            thumbnail: $thumbnail,
            barcodeType: $activity['barcodeType'] ?? 'Standard',
            categoryName: $this->extractCategoryName($activity['activityCategories'] ?? null),
            duration: (string) ($activity['duration'] ?? ''),
            durationType: $activity['durationType'] ?? 'HOURS',
            durationMinutes: $activity['durationMinutes'] ?? null,
            durationHours: $activity['durationHours'] ?? null,
            durationDays: $activity['durationDays'] ?? null,
            durationWeeks: $activity['durationWeeks'] ?? null,
            durationText: $activity['durationText'] ?? null,
            activityType: $activity['activityType'] ?? null,
            scheduleType: $activity['scheduleType'] ?? null,
            bookingType: $activity['bookingType'] ?? null,
            difficulty: $activity['difficultyLevel'] ?? null,
            meetingType: $activity['meetingType'] ?? null,
            cutoffType: $activity['cutoffType'] ?? null,
            cutoffReferenceHour: $activity['cutoffReferenceHour'] ?? null,
            cutoffReferenceMinute: $activity['cutoffReferenceMinute'] ?? null,
            bookingCutoffMinutes: $activity['bookingCutoffMinutes'] ?? null,
            bookingCutoffHours: $activity['bookingCutoffHours'] ?? null,
            bookingCutoffDays: $activity['bookingCutoffDays'] ?? null,
            bookingCutoffWeeks: $activity['bookingCutoffWeeks'] ?? null,
            capacityType: $activity['capacityType'] ?? null,
            passCapacity: $activity['passCapacity'] ?? null,
            passesAvailable: $activity['passesAvailable'] ?? null,
            originalPrice: $originalPrice,
            fromPrice: $fromPrice,
            defaultCurrency: $currency,
            isInstantConfirmation: true,
            isCancellable: $isCancellable,
            isPrivate: (bool) ($activity['privateActivity'] ?? false),
            isPickupAvailable: ($activity['pickupService'] ?? '') !== '' && $activity['pickupService'] !== 'NOT_AVAILABLE',
            isPassportRequired: (bool) ($activity['passportRequired'] ?? false),
            allowCustomizedBookings: (bool) ($activity['allowCustomizedBookings'] ?? false),
            published: true,
            inclusions: $inclusions,
            exclusions: $exclusions,
            highlights: $this->asArray($activity['highlights'] ?? []),
            knowBeforeYouGo: $this->asArray($activity['knowBeforeYouGoItems'] ?? []),
            itinerary: $this->asArray($activity['itinerary'] ?? $activity['agendaItems'] ?? []),
            activityCategories: $this->asArray($activity['activityCategories'] ?? []),
            activityAttributes: array_merge($this->asArray($activity['activityAttributes'] ?? []), [
                'instant_confirmation' => true,
                'original_price'       => $originalPrice,
            ]),
            startTimes: $this->asArray($activity['startTimes'] ?? []),
            guidanceTypes: $this->asArray($activity['guidanceTypes'] ?? []),
            bookableExtras: $this->asArray($activity['bookableExtras'] ?? []),
            startPoints: $this->asArray($activity['startPoints'] ?? []),
            keywords: $keywords,
            photos: $photos,
            videos: $videos,
            location: LocationDTO::fromBokunData($activity),
            cancellationPolicy: CancellationPolicyDTO::fromBokunData($activity),
            options: $options,
            priceRows: $priceRows,
            availabilitySlots: $availSlots,
            timeZone: $activity['timeZone'] ?? null,
            timezoneOffset: null,
            minAge: $activity['minAge'] ?? null,
            dressCode: $activity['dressCode'] ?? null,
            defaultRateId: $activity['defaultRateId'] ?? null,
            pickupService: $activity['pickupService'] ?? null,
            dropoffService: $activity['dropoffService'] ?? null,
            hasAvailabilityData: array_key_exists('availabilities', $rawApiData['data'] ?? []),
            rawResponse: $rawApiData,
        );
    }

    // ─── OPTIONS + CATEGORIES ───────────────────────────────────────────

    /**
     * Build ProductOptionDTO[] from Bokun rates + pricingCategories.
     *
     * @return array{0: ProductOptionDTO[], 1: array} [options, categoryMap keyed by pricingCategoryId]
     */
    private function buildOptionsAndCategories(array $activity): array
    {
        $options     = [];
        $categoryMap = [];
        $rates       = $activity['rates'] ?? [];
        $pricingCats = $activity['pricingCategories'] ?? [];
        $defaultRateId = $activity['defaultRateId'] ?? null;

        // Build a lookup of pricingCategories by ID
        $pricingCatLookup = [];
        foreach ($pricingCats as $pc) {
            if (is_array($pc) && !empty($pc['id'])) {
                $pricingCatLookup[$pc['id']] = $pc;
            }
        }

        if (empty($rates)) {
            // Fallback: create a single "Default" option with all pricingCategories
            $categories = [];
            $catSort = 0;
            foreach ($pricingCatLookup as $id => $pc) {
                $catCode = strtoupper($pc['ticketCategory'] ?? 'ADULT');
                $dto = new PricingCategoryDTO(
                    externalId: (string) $id,
                    categoryCode: $catCode,
                    categoryLabel: $pc['fullTitle'] ?? $pc['title'] ?? $catCode,
                    ageFrom: $pc['minAge'] ?? null,
                    ageTo: $pc['maxAge'] ?? null,
                    occupancy: (int) ($pc['occupancy'] ?? 1),
                    groupSize: (int) ($pc['groupSize'] ?? 0),
                    isDefault: (bool) ($pc['defaultCategory'] ?? ($catCode === 'ADULT')),
                    isAgeQualified: (bool) ($pc['ageQualified'] ?? true),
                    sortOrder: $catSort++,
                );
                $categories[] = $dto;
                $categoryMap[(string) $id] = $dto;
            }

            $options[] = new ProductOptionDTO(
                provider: 'bokun',
                externalId: $defaultRateId ? (string) $defaultRateId : 'default',
                name: 'Default',
                isDefault: true,
                sortOrder: 0,
                categories: $categories,
            );

            return [$options, $categoryMap];
        }

        $optionSort = 0;
        foreach ($rates as $rate) {
            if (!is_array($rate)) continue;

            $rateId   = $rate['rateId'] ?? $rate['id'] ?? null;
            $rateName = $rate['title'] ?? 'Option ' . ($optionSort + 1);
            $isDefault = ($rateId !== null && (string) $rateId === (string) $defaultRateId) || $optionSort === 0;

            // Build categories from pricingCategoryIds in this rate
            $categories = [];
            $catSort = 0;
            $pricingCategoryIds = $rate['pricingCategoryIds'] ?? [];

            if (empty($pricingCategoryIds)) {
                // Fallback: use all pricingCategories
                $pricingCategoryIds = array_keys($pricingCatLookup);
            }

            foreach ($pricingCategoryIds as $pcId) {
                $pc = $pricingCatLookup[$pcId] ?? null;
                if (!$pc) continue;

                $catCode = strtoupper($pc['ticketCategory'] ?? 'ADULT');
                $dto = new PricingCategoryDTO(
                    externalId: (string) $pcId,
                    categoryCode: $catCode,
                    categoryLabel: $pc['fullTitle'] ?? $pc['title'] ?? $catCode,
                    ageFrom: $pc['minAge'] ?? null,
                    ageTo: $pc['maxAge'] ?? null,
                    occupancy: (int) ($pc['occupancy'] ?? 1),
                    groupSize: (int) ($pc['groupSize'] ?? 0),
                    isDefault: (bool) ($pc['defaultCategory'] ?? ($catCode === 'ADULT')),
                    isAgeQualified: (bool) ($pc['ageQualified'] ?? true),
                    sortOrder: $catSort++,
                );
                $categories[] = $dto;
                $categoryMap[(string) $pcId] = $dto;
            }

            $options[] = new ProductOptionDTO(
                provider: 'bokun',
                externalId: $rateId ? (string) $rateId : 'rate-' . $optionSort,
                name: $rateName,
                isDefault: $isDefault,
                sortOrder: $optionSort++,
                categories: $categories,
            );
        }

        return [$options, $categoryMap];
    }

    // ─── PRICE ROWS ─────────────────────────────────────────────────────

    /**
     * Build PriceRowDTO[] from Bokun priceList.pricesByDateRange.
     *
     * Each price row = one passenger category for one rate within one date range.
     * Tiered prices are extracted as TieredPriceDTO[].
     *
     * @return PriceRowDTO[]
     */
    private function buildPriceRows(array $activity, array $priceList, string $currency, bool $isConverted): array
    {
        $rows = [];
        $dateRanges = $priceList['pricesByDateRange'] ?? [];

        if (!is_array($dateRanges) || empty($dateRanges)) {
            return $this->buildFallbackPriceRow($activity, $currency);
        }

        $sortOrder = 0;

        foreach ($dateRanges as $dateRange) {
            if (!is_array($dateRange)) continue;

            foreach (($dateRange['rates'] ?? []) as $rate) {
                if (!is_array($rate)) continue;

                $rateId   = $rate['rateId'] ?? $rate['id'] ?? null;
                $passengers = $rate['passengers'] ?? [];
                if (!is_array($passengers) || empty($passengers)) continue;

                foreach ($passengers as $passenger) {
                    if (!is_array($passenger)) continue;

                    $amount = PriceCalculator::resolveBokunPassengerPrice($passenger);
                    if ($amount === null) continue;

                    $catCode  = strtoupper($passenger['ticketCategory'] ?? 'ADULT');
                    $catLabel = $passenger['title'] ?? $catCode;
                    $pricingCategoryId = $passenger['pricingCategoryId'] ?? null;

                    // Build tiered prices
                    $tieredPrices = $this->buildTieredPrices(
                        $passenger['tieredPrices'] ?? [],
                        $currency
                    );

                    $rows[] = new PriceRowDTO(
                        provider: 'bokun',
                        optionExternalId: $rateId ? (string) $rateId : null,
                        categoryExternalId: $pricingCategoryId ? (string) $pricingCategoryId : null,
                        categoryCode: $catCode,
                        categoryLabel: $catLabel,
                        validFrom: $dateRange['from'] ?? null,
                        validTo: $dateRange['to'] ?? null,
                        sellingPrice: $amount,
                        nettPrice: PriceCalculator::firstNumeric([$passenger['nettPrice'] ?? null]),
                        originalPrice: PriceCalculator::firstNumeric([$activity['originalDefaultPrice'] ?? null]),
                        recommendedPrice: PriceCalculator::firstNumeric([$passenger['recommendedSellingPrice'] ?? null]),
                        merchantPrice: null,
                        currency: data_get($passenger, 'price.currency', $currency),
                        isConverted: $isConverted,
                        tieredPrices: $tieredPrices,
                        isDefault: false,
                        sortOrder: $sortOrder++,
                    );
                }
            }
        }

        // Mark cheapest as default
        if (!empty($rows)) {
            usort(
                $rows,
                fn(PriceRowDTO $a, PriceRowDTO $b) => ($a->comparablePrice() ?? PHP_FLOAT_MAX) <=> ($b->comparablePrice() ?? PHP_FLOAT_MAX)
            );
            $first = $rows[0];
            $rows[0] = new PriceRowDTO(
                provider: $first->provider,
                optionExternalId: $first->optionExternalId,
                categoryExternalId: $first->categoryExternalId,
                categoryCode: $first->categoryCode,
                categoryLabel: $first->categoryLabel,
                validFrom: $first->validFrom,
                validTo: $first->validTo,
                sellingPrice: $first->sellingPrice,
                nettPrice: $first->nettPrice,
                originalPrice: $first->originalPrice,
                recommendedPrice: $first->recommendedPrice,
                merchantPrice: $first->merchantPrice,
                currency: $first->currency,
                isConverted: $first->isConverted,
                tieredPrices: $first->tieredPrices,
                isDefault: true,
                sortOrder: 0,
                taxAmount: $first->taxAmount,
                taxPercentage: $first->taxPercentage,
                discountPercentage: $first->discountPercentage,
                discountedPrice: $first->discountedPrice,
            );
        }

        return $rows;
    }

    /**
     * Build TieredPriceDTO[] from Bokun tieredPrices array.
     *
     * @return TieredPriceDTO[]
     */
    private function buildTieredPrices(array $tiers, string $defaultCurrency): array
    {
        $result = [];
        $sort = 0;

        foreach ($tiers as $tier) {
            if (!is_array($tier)) continue;

            $amount = PriceCalculator::firstNumeric([
                data_get($tier, 'price.amount'),
                $tier['amount'] ?? null,
            ]);

            if ($amount === null) continue;

            $result[] = new TieredPriceDTO(
                minPax: (int) ($tier['minPassengersRequired'] ?? $tier['minPurchaseQty'] ?? 1),
                maxPax: (int) ($tier['maxPassengersRequired'] ?? $tier['maxPurchaseQty'] ?? 999),
                amount: $amount,
                nettAmount: PriceCalculator::firstNumeric([$tier['nettPrice'] ?? null]),
                currency: data_get($tier, 'price.currency', $tier['currency'] ?? $defaultCurrency),
                taxAmount: PriceCalculator::firstNumeric([$tier['ofWhichTax'] ?? null]),
                isConverted: (bool) ($tier['converted'] ?? false),
                sortOrder: $sort++,
            );
        }

        return $result;
    }

    /**
     * Fallback: create a single summary price row from product-level price.
     * @return PriceRowDTO[]
     */
    private function buildFallbackPriceRow(array $activity, string $currency): array
    {
        $amount = PriceCalculator::firstNumeric([
            data_get($activity, 'nextDefaultPriceMoney.amount'),
            $activity['nextDefaultPrice'] ?? null,
            $activity['originalDefaultPrice'] ?? null,
        ]);

        if ($amount === null) {
            return [];
        }

        return [new PriceRowDTO(
            provider: 'bokun',
            optionExternalId: $activity['defaultRateId'] ?? 'default',
            categoryExternalId: null,
            categoryCode: null,
            categoryLabel: 'Standard',
            validFrom: now()->toDateString(),
            validTo: now()->addMonths(3)->toDateString(),
            sellingPrice: $amount,
            nettPrice: null,
            originalPrice: PriceCalculator::firstNumeric([$activity['originalDefaultPrice'] ?? null]),
            recommendedPrice: null,
            merchantPrice: null,
            currency: $currency,
            isDefault: true,
            sortOrder: 0,
        )];
    }

    // ─── AVAILABILITY ───────────────────────────────────────────────────

    /**
     * Build AvailabilitySlotDTO[] from Bokun availabilities.
     *
     * Each slot has category prices extracted from pricesByCategory/passengers.
     *
     * @return AvailabilitySlotDTO[]
     */
    private function buildAvailabilitySlots(array $activity, array $availabilities, string $currency): array
    {
        $slots = [];
        $tz = $activity['timeZone'] ?? null;
        $defaultRateId = $activity['defaultRateId'] ?? null;
        $pricingCategoryLookup = [];

        foreach (($activity['pricingCategories'] ?? []) as $pc) {
            if (is_array($pc) && isset($pc['id'])) {
                $pricingCategoryLookup[(string) $pc['id']] = $pc;
            }
        }

        foreach ($availabilities as $avail) {
            if (!is_array($avail)) continue;

            $date = $this->normalizeDate($avail['date'] ?? null);
            if (!$date) continue;

            $isSoldOut   = (bool) ($avail['soldOut'] ?? false);
            $isUnlimited = (bool) ($avail['unlimitedAvailability'] ?? false);
            $isAvailable = (bool) ($avail['isAvailable'] ?? true);
            $available   = (int) ($avail['availabilityCount'] ?? $avail['available'] ?? 0);
            $total       = (int) ($avail['total'] ?? $avail['capacity_total'] ?? $available);
            $bookedCount = (int) ($avail['bookedParticipants'] ?? max(0, $total - $available));

            if ($isSoldOut || !$isAvailable || (!$isUnlimited && $available <= 0)) {
                continue;
            }

            $startTime = $this->normalizeTime($avail['startTime'] ?? null);

            $categoryPrices = $this->buildAvailabilityCategoryPrices(
                $avail,
                $pricingCategoryLookup,
                $currency,
                $defaultRateId ? (string) $defaultRateId : null
            );

            $lowestPrice = $this->resolveDisplayPriceFromAvailabilityCategories($categoryPrices);

            // Fallback display price
            if ($lowestPrice === null) {
                $lowestPrice = PriceCalculator::firstNumeric([
                    $avail['defaultPrice'] ?? null,
                    data_get($avail, 'price.amount'),
                ]);
            }

            $slots[] = new AvailabilitySlotDTO(
                provider: 'bokun',
                externalId: isset($avail['id']) ? (string) $avail['id'] : null,
                optionExternalId: $defaultRateId ? (string) $defaultRateId : 'default',
                availableDate: $date,
                startTime: $startTime ?? '00:00:00',
                endTime: null,
                timeZone: $tz,
                totalCapacity: $total > 0 ? $total : $available,
                bookedCount: $bookedCount,
                isUnlimited: $isUnlimited,
                isSoldOut: false,
                displayPrice: $lowestPrice,
                currency: data_get($avail, 'price.currency', $currency),
                categoryPrices: $categoryPrices,
                minParticipants: (int) ($avail['minParticipants'] ?? 1),
                rawData: $avail,
            );
        }

        return $slots;
    }

    /**
     * @return AvailabilityCategoryPriceDTO[]
     */
    private function buildAvailabilityCategoryPrices(
        array $availability,
        array $pricingCategoryLookup,
        string $fallbackCurrency,
        ?string $defaultRateId
    ): array {
        $categoryPrices = [];

        foreach (($availability['pricesByRate'] ?? []) as $ratePrice) {
            if (!is_array($ratePrice)) {
                continue;
            }

            $rateId = isset($ratePrice['activityRateId']) ? (string) $ratePrice['activityRateId'] : null;
            if ($defaultRateId !== null && $rateId !== null && $rateId !== $defaultRateId) {
                continue;
            }

            foreach (($ratePrice['pricePerCategoryUnit'] ?? []) as $unit) {
                if (!is_array($unit)) {
                    continue;
                }

                $categoryPrice = $this->makeAvailabilityCategoryPriceDTO($unit, $pricingCategoryLookup, $fallbackCurrency);
                if ($categoryPrice !== null) {
                    $categoryPrices[] = $categoryPrice;
                }
            }
        }

        foreach (
            [
                $availability['passengers'] ?? [],
                $availability['prices'] ?? [],
                $availability['pricesByCategory'] ?? [],
            ] as $entries
        ) {
            if (!is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $categoryPrice = $this->makeAvailabilityCategoryPriceDTO($entry, $pricingCategoryLookup, $fallbackCurrency);
                if ($categoryPrice !== null) {
                    $categoryPrices[] = $categoryPrice;
                }
            }
        }

        return $categoryPrices;
    }

    private function makeAvailabilityCategoryPriceDTO(array $entry, array $pricingCategoryLookup, string $fallbackCurrency): ?AvailabilityCategoryPriceDTO
    {
        $categoryId = $entry['pricingCategoryId'] ?? $entry['id'] ?? $entry['categoryId'] ?? null;
        $category = $categoryId !== null ? ($pricingCategoryLookup[(string) $categoryId] ?? []) : [];
        $categoryCode = strtoupper((string) (
            $entry['ticketCategory']
            ?? $entry['categoryCode']
            ?? $category['ticketCategory']
            ?? $category['title']
            ?? 'ADULT'
        ));
        $categoryLabel = (string) (
            $entry['title']
            ?? $entry['categoryLabel']
            ?? $category['fullTitle']
            ?? $category['title']
            ?? $categoryCode
        );

        $price = PriceCalculator::firstNumeric([
            data_get($entry, 'amount.amount'),
            data_get($entry, 'price.amount'),
            $entry['amount'] ?? null,
            $entry['nettPrice'] ?? null,
            $entry['recommendedSellingPrice'] ?? null,
        ]);

        if ($price === null) {
            $price = PriceCalculator::resolveBokunPassengerPrice($entry);
        }

        if ($price === null) {
            return null;
        }

        return new AvailabilityCategoryPriceDTO(
            categoryExternalId: $categoryId !== null ? (string) $categoryId : $categoryCode,
            categoryCode: $categoryCode,
            categoryLabel: $categoryLabel,
            sellingPrice: $price,
            nettPrice: PriceCalculator::firstNumeric([$entry['nettPrice'] ?? null]),
            taxAmount: PriceCalculator::firstNumeric([$entry['taxAmount'] ?? null, data_get($entry, 'tax.amount')]),
            availableQty: null,
            minQty: isset($entry['minParticipantsRequired']) ? (int) $entry['minParticipantsRequired'] : (isset($entry['minPurchaseQty']) ? (int) $entry['minPurchaseQty'] : null),
            maxQty: isset($entry['maxParticipantsRequired']) ? (int) $entry['maxParticipantsRequired'] : (isset($entry['maxPurchaseQty']) ? (int) $entry['maxPurchaseQty'] : null),
            metadata: $entry,
            currency: data_get($entry, 'amount.currency', data_get($entry, 'price.currency', $fallbackCurrency)),
        );
    }

    /**
     * Prefer the cheapest paid category for display; zero-price infant rows
     * are still saved for billing but should not make slots look free.
     *
     * @param AvailabilityCategoryPriceDTO[] $categoryPrices
     */
    private function resolveDisplayPriceFromAvailabilityCategories(array $categoryPrices): ?float
    {
        $prices = [];

        foreach ($categoryPrices as $categoryPrice) {
            if ($categoryPrice instanceof AvailabilityCategoryPriceDTO) {
                $prices[] = $categoryPrice->sellingPrice;
            }
        }

        if ($prices === []) {
            return null;
        }

        $paidPrices = array_values(array_filter($prices, fn(float $price) => $price > 0));
        sort($paidPrices);

        if ($paidPrices !== []) {
            return $paidPrices[0];
        }

        sort($prices);
        return $prices[0] ?? null;
    }

    // ─── HELPERS ────────────────────────────────────────────────────────

    private function parseMedia(array $activity): array
    {
        $photos = collect($activity['photos'] ?? [])->map(fn($p) => [
            'original'      => $p['originalUrl'] ?? null,
            'large'         => collect($p['derived'] ?? [])->firstWhere('name', 'large')['url'] ?? ($p['originalUrl'] ?? null),
            'preview'       => collect($p['derived'] ?? [])->firstWhere('name', 'preview')['url'] ?? ($p['originalUrl'] ?? null),
            'alt_text'      => $p['alternateText'] ?? null,
            'external_id'   => $p['id'] ?? null,
            'provider_path' => $p['fileName'] ?? null,
        ])->values()->toArray();

        $videos = collect($activity['videos'] ?? [])->map(fn($v) => [
            'id'           => $v['id'] ?? null,
            'title'        => $v['title'] ?? null,
            'sourceUrl'    => $v['sourceUrl'] ?? null,
            'thumbnailUrl' => $v['thumbnailUrl'] ?? null,
            'previewUrl'   => $v['previewUrl'] ?? null,
            'html'         => $v['html'] ?? null,
            'providerName' => $v['providerName'] ?? null,
            'duration'     => $v['duration'] ?? null,
        ])->values()->toArray();

        $thumbnail = data_get($activity, 'keyPhoto.originalUrl', $photos[0]['original'] ?? null);

        return [$photos, $videos, $thumbnail];
    }

    private function parseHtmlList(?string $html): array
    {
        if (!is_string($html) || trim($html) === '') return [];
        $raw = str_replace(['<br />', '<br/>', '<br>', '</li>'], '|', $html);
        return array_values(array_filter(array_map('trim', array_map('strip_tags', explode('|', $raw)))));
    }

    private function parseKeywords($value): array
    {
        if (is_string($value)) {
            return array_values(array_filter(array_map('trim', preg_split('/[,;]+/', $value))));
        }
        if (is_array($value)) {
            return array_values(array_filter(array_map(
                fn($e) =>
                is_string($e) ? trim($e) : (is_array($e) ? ($e['name'] ?? $e['label'] ?? '') : ''),
                $value
            )));
        }
        return [];
    }

    private function extractCategoryName($value): string
    {
        if (is_array($value)) return (string) ($value[0] ?? 'Activity');
        $v = trim((string) $value);
        return $v !== '' ? $v : 'Activity';
    }

    private function asArray($value): array
    {
        if (is_array($value)) return $value;
        if (is_string($value) && $value !== '') return [$value];
        return [];
    }

    private function normalizeDate($value): ?string
    {
        if (empty($value)) return null;
        if (is_numeric($value)) {
            $ts = (int) $value;
            if ($ts > 9999999999) $ts = (int) floor($ts / 1000);
            return date('Y-m-d', $ts);
        }
        try {
            return date('Y-m-d', strtotime((string) $value));
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeTime($value): ?string
    {
        if (empty($value)) return null;
        $ts = strtotime((string) $value);
        return $ts !== false ? date('H:i:s', $ts) : null;
    }
}
