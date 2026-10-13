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
use App\Services\GlobalTix\GlobalTixProductService;
use Illuminate\Support\Str;

/**
 * Converts raw GlobalTix API response into a SyncedProductDTO.
 *
 * GlobalTix pricing structure:
 *   activityOptions[].data[] → options
 *   activityOptions[].ticketType[] → categories per option
 *   Each ticket has recommendedMerchantSellingPrice (RESELLER price!)
 *   Availability rows have per-ticket nettPrice + recommendedSellingPrice
 *
 * As a RESELLER, we use recommendedMerchantSellingPrice as the selling price.
 */
final class GlobalTixNormalizer
{
    public function normalize(array $rawApiData): SyncedProductDTO
    {
        $activity       = $rawApiData['data']['activity']['data'] ?? [];
        $ticketOptions  = $rawApiData['data']['activityOptions']['data'] ?? [];
        $availabilities = $rawApiData['data']['availability']['data'] ?? [];
        $currency = $activity['currency'] ?? $rawApiData['data']['currency'] ?? 'EUR'; // Use native currency instead of forcing EUR
        $country = $activity['country'] ?? $activity['countryCode'] ?? null;
        $city = $activity['city'] ?? null;
        
        // ─── Step 1: Build Options + Categories from activityOptions + ticketTypes
        [$options, $ticketLookup, $allPrices] = $this->buildOptionsAndCategories($ticketOptions, $currency, $country, $city);

        // ─── Parse cancellation notes + booking questions from options
        $bookingQuestions  = [];
        $cancellationNotes = [];
        foreach (($ticketOptions ?: []) as $option) {
            if (!is_array($option)) continue;
            foreach (($option['questions'] ?? []) as $q) {
                if (!is_array($q)) continue;
                $bookingQuestions[] = [
                    'questionCode' => $q['id'] ?? null,
                    'label'        => $q['label'] ?? $q['name'] ?? null,
                    'required'     => (bool) ($q['required'] ?? false),
                    'dataType'     => $q['type'] ?? 'STRING',
                    'options'      => $q['options'] ?? [],
                ];
            }
            foreach (($option['cancellationNotes'] ?? []) as $note) {
                if (is_string($note) && trim($note) !== '') {
                    $cancellationNotes[] = trim($note);
                }
            }
        }

        // ─── Media ───────────────────────────────────────────────────────
        [$photos, $thumbnail] = $this->parseMedia($activity);

        // ─── Content arrays ──────────────────────────────────────────────
        $inclusions   = $this->extractValueArray($activity['inclusions'] ?? []);
        $exclusions   = $this->extractValueArray($activity['exclusions'] ?? []);
        $highlights   = $this->extractValueArray($activity['highlights'] ?? []);
        $thingsToNote = $this->extractValueArray($activity['thingsToNote'] ?? []);
        $howToUse     = $this->extractValueArray($activity['howToUseList'] ?? $activity['howToUse'] ?? []);
        $keywords     = !empty($activity['keywords'])
            ? array_values(array_filter(array_map('trim', explode(',', (string) $activity['keywords']))))
            : [];

        // ─── Duration ────────────────────────────────────────────────────
        $duration = $activity['definedDuration'] ?? null;
        if (!$duration) {
            foreach (($ticketOptions ?: []) as $option) {
                if (!empty($option['definedDuration'])) {
                    $duration = $option['definedDuration'];
                    break;
                }
            }
        }

        // ─── Display pricing ─────────────────────────────────────────────
        $rawRootFromPrice = array_key_exists('fromPrice', $activity) ? PriceCalculator::firstNumeric([$activity['fromPrice']]) : null;
        $rawRootOriginalPrice = array_key_exists('originalPrice', $activity) ? PriceCalculator::firstNumeric([$activity['originalPrice']]) : null;

        $resolvedPrices = PriceCalculator::resolveProductDisplayPrices($ticketLookup, $rawRootFromPrice, $rawRootOriginalPrice, $country, $city);
        $activityFromPrice = $resolvedPrices['from_price'];
        $activityOriginalPrice = $resolvedPrices['original_price'];

        // ─── Cancellation ────────────────────────────────────────────────
        $isCancellable = collect($ticketOptions ?: [])
            ->contains(fn($o) => is_array($o) && (bool) ($o['isCancellable'] ?? false))
            || (bool) ($activity['isCancellable'] ?? false);

        // ─── Step 2: Build Price Rows ────────────────────────────────────
        $priceRows = $this->buildPriceRows($ticketLookup, $currency);

        // ─── Step 3: Build Availability Slots ────────────────────────────
        $availSlots = $this->buildAvailabilitySlots($availabilities, $activity, $ticketLookup);

        // ─── Pickup detection ────────────────────────────────────────────
        $inclusionsStr = strtolower(implode(' ', $inclusions));
        $isPickup = str_contains($inclusionsStr, 'transfer')
            || str_contains($inclusionsStr, 'pick up')
            || str_contains($inclusionsStr, 'pickup');

        // ─── Min age ─────────────────────────────────────────────────────
        $minAge = null;
        foreach ($ticketLookup as $t) {
            $candidate = $t['ageFrom'] ?? null;
            if ($candidate !== null && ($minAge === null || $candidate < $minAge)) {
                $minAge = (int) $candidate;
            }
        }

        return new SyncedProductDTO(
            provider: 'globaltix',
            productApiId: (string) ($activity['id'] ?? ''),
            vendor: VendorDTO::fromGlobalTixData($activity),
            title: $activity['name'] ?? 'Untitled Product',
            description: strip_tags($activity['description'] ?? ''),
            thumbnail: $thumbnail,
            barcodeType: 'Standard',
            categoryName: is_array($activity['category'] ?? null)
                ? ($activity['category'][0] ?? 'Activity')
                : ($activity['category'] ?? 'Activity'),
            duration: $duration ? (string) $duration : null,
            durationType: 'MINUTES',
            durationMinutes: $duration ? (int) $duration : null,
            durationHours: null,
            durationDays: null,
            durationWeeks: null,
            durationText: $duration ? "{$duration} minutes" : null,
            activityType: 'ACTIVITY',
            scheduleType: null,
            bookingType: 'SHARED',
            difficulty: null,
            meetingType: 'POINT',
            cutoffType: 'FIXED',
            cutoffReferenceHour: 0,
            cutoffReferenceMinute: 0,
            bookingCutoffMinutes: null,
            bookingCutoffHours: null,
            bookingCutoffDays: null,
            bookingCutoffWeeks: null,
            capacityType: 'FIXED',
            passCapacity: null,
            passesAvailable: null,
            originalPrice: $activityOriginalPrice,
            fromPrice: $activityFromPrice,
            defaultCurrency: $currency,
            isInstantConfirmation: (bool) ($activity['isInstantConfirmation'] ?? false),
            isCancellable: $isCancellable,
            isPrivate: false,
            isPickupAvailable: $isPickup,
            isPassportRequired: (bool) ($activity['passportRequired'] ?? false),
            allowCustomizedBookings: false,
            published: true,
            inclusions: $inclusions,
            exclusions: $exclusions,
            highlights: $highlights,
            knowBeforeYouGo: $thingsToNote,
            itinerary: [],
            activityCategories: is_array($activity['category'] ?? null)
                ? $activity['category']
                : [$activity['category'] ?? 'Activity'],
            activityAttributes: [],
            startTimes: [],
            guidanceTypes: [],
            bookableExtras: [],
            startPoints: [],
            keywords: $keywords,
            photos: $photos,
            videos: [],
            location: LocationDTO::fromGlobalTixData($activity),
            cancellationPolicy: new CancellationPolicyDTO(
                title: $isCancellable ? 'Cancellable' : 'Non-refundable',
                policyType: $isCancellable ? 'STANDARD' : 'NON_REFUNDABLE',
                description: $isCancellable ? 'Cancellable' : 'Non-refundable',
                metadata: ['cancellation_notes' => array_values(array_unique($cancellationNotes))],
            ),
            options: $options,
            priceRows: $priceRows,
            availabilitySlots: $availSlots,
            timeZone: $activity['timeZone'] ?? $activity['timezone'] ?? null,
            timezoneOffset: null,
            minAge: $minAge,
            dressCode: null,
            defaultRateId: null,
            pickupService: $isPickup ? 'AVAILABLE' : 'NOT_AVAILABLE',
            dropoffService: null,
            whatToExpect: is_string($activity['whatToExpect'] ?? null) ? strip_tags($activity['whatToExpect']) : null,
            termsAndConditions: $activity['termsAndConditions'] ?? null,
            operatingHours: $this->normalizeTextPayload($activity['operatingHours'] ?? null),
            thingsToNote: $thingsToNote,
            howToUse: $howToUse,
            cancellationNotes: array_values(array_unique($cancellationNotes)),
            addressLine: $activity['addressLine'] ?? null,
            postalCode: $activity['postalCode'] ?? null,
            merchantName: $activity['merchant']['name'] ?? null,
            hasAvailabilityData: array_key_exists('availability', $rawApiData['data'] ?? []),
            rawResponse: $rawApiData,
        );
    }

    // ─── OPTIONS + CATEGORIES ───────────────────────────────────────────

    /**
     * Build ProductOptionDTO[] from GlobalTix activityOptions + ticketTypes.
     *
     * @return array{0: ProductOptionDTO[], 1: array, 2: float[]}
     *         [options, ticketLookup keyed by ticket_id, allPrices]
     */
    private function buildOptionsAndCategories(array $ticketOptions, string $currency, ?string $country = null, ?string $city = null): array
    {
        $options      = [];
        $ticketLookup = [];
        $allPrices    = [];
        $optionSort   = 0;

        foreach (($ticketOptions ?: []) as $option) {
            if (!is_array($option)) continue;

            $optionId   = $option['id'] ?? null;
            $optionName = $option['name'] ?? 'Option ' . ($optionSort + 1);
            $categories = [];
            $seenTicketIds = [];
            $catSort    = 0;

            foreach (($option['ticketType'] ?? []) as $ticket) {
                if (!is_array($ticket) || empty($ticket['id'])) continue;
                if (isset($seenTicketIds[(string) $ticket['id']])) continue;
                $seenTicketIds[(string) $ticket['id']] = true;

                $ticketName     = $ticket['name'] ?? $ticket['nameTranslated'] ?? 'Standard';
                $ticketCategory = $this->inferTicketCategory($ticketName, $ticket['nameTranslated'] ?? null);
                $sellingPrice   = PriceCalculator::resolveGlobalTixSellingPrice($ticket, 'EUR', $country, $city);

                // Store in lookup for price row and availability building
                $ticketLookup[(string) $ticket['id']] = [
                    'ticket'                  => $ticket,
                    'optionId'                => $optionId,
                    'optionName'              => $optionName,
                    'name'                    => $ticket['name'] ?? $ticketName,
                    'nameTranslated'          => $ticket['nameTranslated'] ?? null,
                    'categoryCode'            => $ticketCategory,
                    'category_code'           => $ticketCategory,
                    'categoryLabel'           => $ticketName,
                    'sellingPrice'            => $sellingPrice,
                    'selling_price'           => $sellingPrice,
                    'recommendedSellingPrice' => $ticket['recommendedSellingPrice'] ?? null,
                    'originalPrice'           => $ticket['originalPrice'] ?? null,
                    'original_price'          => PriceCalculator::resolveGlobalTixOriginalPrice($ticket, 'EUR', $country, $city),
                    'ageFrom'                 => $ticket['ageFrom'] ?? $ticket['minAge'] ?? null,
                    'ageTo'                   => $ticket['ageTo'] ?? $ticket['maxAge'] ?? null,
                    'currency'                => $ticket['currency'] ?? $option['currency'] ?? $currency,
                ];

                if ($sellingPrice !== null && $sellingPrice > 0) {
                    $allPrices[] = $sellingPrice;
                }

                $categories[] = new PricingCategoryDTO(
                    externalId: (string) $ticket['id'],
                    categoryCode: $ticketCategory,
                    categoryLabel: $ticketName,
                    ageFrom: $ticket['ageFrom'] ?? $ticket['minAge'] ?? null,
                    ageTo: $ticket['ageTo'] ?? $ticket['maxAge'] ?? null,
                    occupancy: 1,
                    groupSize: 0,
                    minPurchaseQty: $ticket['minPurchaseQty'] ?? $ticket['minQty'] ?? null,
                    maxPurchaseQty: $ticket['maxPurchaseQty'] ?? $ticket['maxQty'] ?? null,
                    sellingPrice: $sellingPrice,
                    nettPrice: PriceCalculator::resolveGlobalTixNettPrice($ticket, 'EUR', $country, $city),
                    originalPrice: PriceCalculator::resolveGlobalTixOriginalPrice($ticket, 'EUR', $country, $city),
                    recommendedPrice: PriceCalculator::resolveGlobalTixRecommendedPrice($ticket, 'EUR', $country, $city),
                    merchantPrice: $sellingPrice,
                    currency: $ticket['currency'] ?? $option['currency'] ?? $currency,
                    isDefault: $ticketCategory === 'ADULT',
                    sortOrder: $catSort++,
                );
            }

            if (!empty($optionId)) {
                $options[] = new ProductOptionDTO(
                    provider: 'globaltix',
                    externalId: (string) $optionId,
                    name: $optionName,
                    isDefault: $optionSort === 0,
                    sortOrder: $optionSort++,
                    categories: $categories,
                    currency: $option['currency'] ?? $currency,
                    ticketFormat: $option['ticketFormat'] ?? null,
                    ticketValidity: $option['ticketValidity'] ?? null,
                    definedDuration: isset($option['definedDuration']) ? (string) $option['definedDuration'] : null,
                    isDynamicPricing: (bool) ($option['isDynamicPricing'] ?? false),
                    isCapacity: (bool) ($option['isCapacity'] ?? false),
                    isCancellable: (bool) ($option['isCancellable'] ?? true),
                    timeSlot: is_array($option['timeSlot'] ?? null) ? $option['timeSlot'] : null,
                    visitDate: is_array($option['visitDate'] ?? null) ? $option['visitDate'] : null,
                    advanceBooking: is_array($option['advanceBooking'] ?? null) ? $option['advanceBooking'] : null,
                    questions: is_array($option['questions'] ?? null) ? $option['questions'] : null,
                    inclusions: is_array($option['inclusions'] ?? null) ? $option['inclusions'] : null,
                    exclusions: is_array($option['exclusions'] ?? null) ? $option['exclusions'] : null,
                    cancellationPolicy: is_string($option['cancellationPolicy'] ?? null) ? $option['cancellationPolicy'] : null,
                    cancellationNotes: is_array($option['cancellationNotes'] ?? null) ? $option['cancellationNotes'] : null,
                    publishStart: $option['publishStart'] ?? null,
                    publishEnd: $option['publishEnd'] ?? null,
                    redeemStart: $option['redeemStart'] ?? null,
                    redeemEnd: $option['redeemEnd'] ?? null,
                    metadata: [
                        'questions'          => $option['questions'] ?? [],
                        'cancellationNotes'  => $option['cancellationNotes'] ?? [],
                    ],
                );
            }
        }

        // Fallback: create a default option if none found
        if (empty($options)) {
            $options[] = new ProductOptionDTO(
                provider: 'globaltix',
                externalId: 'default',
                name: 'Standard',
                isDefault: true,
                sortOrder: 0,
            );
        }

        return [$options, $ticketLookup, $allPrices];
    }

    // ─── PRICE ROWS ─────────────────────────────────────────────────────

    /**
     * Build PriceRowDTO[] from parsed ticket types.
     *
     * RESELLER PRICING: We use recommendedMerchantSellingPrice as selling_price.
     * GlobalTix has flat pricing, so we create a single tier per price for consistency.
     *
     * @return PriceRowDTO[]
     */
    private function buildPriceRows(array $ticketLookup, string $currency): array
    {
        if (empty($ticketLookup)) return [];

        $rows = [];
        $sortOrder = 0;

        foreach ($ticketLookup as $ticketId => $info) {
            $ticket       = $info['ticket'];
            $sellingPrice = $info['sellingPrice'];
            if ($sellingPrice === null) continue;

            // Create a single tier for GlobalTix (flat price = 1-999 pax)
            $tieredPrices = [new TieredPriceDTO(
                minPax: 1,
                maxPax: 999,
                amount: $sellingPrice,
                nettAmount: PriceCalculator::resolveGlobalTixNettPrice($ticket),
                currency: $info['currency'],
                sortOrder: 0,
            )];

            $rows[] = new PriceRowDTO(
                provider: 'globaltix',
                optionExternalId: $info['optionId'] ? (string) $info['optionId'] : 'default',
                categoryExternalId: (string) $ticketId,
                categoryCode: $info['categoryCode'],
                categoryLabel: $info['categoryLabel'],
                validFrom: now()->toDateString(),
                validTo: now()->addMonths(3)->toDateString(),
                sellingPrice: $sellingPrice,
                nettPrice: PriceCalculator::resolveGlobalTixNettPrice($ticket),
                originalPrice: PriceCalculator::resolveGlobalTixOriginalPrice($ticket),
                recommendedPrice: PriceCalculator::resolveGlobalTixRecommendedPrice($ticket),
                merchantPrice: PriceCalculator::firstNumeric([
                    $ticket['nettMerchantPrice'] ?? null,
                    $ticket['originalMerchantPrice'] ?? null,
                ]),
                currency: $info['currency'],
                tieredPrices: $tieredPrices,
                isDefault: false,
                sortOrder: $sortOrder++,
            );
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

    // ─── AVAILABILITY ───────────────────────────────────────────────────

    /**
     * Build AvailabilitySlotDTO[] from GlobalTix availability data.
     *
     * GlobalTix sends one availability row per ticket_type per date+time.
     * We GROUP these into one slot per date+time and attach category prices.
     *
     * @return AvailabilitySlotDTO[]
     */
    private function buildAvailabilitySlots(array $availabilities, array $activity, array $ticketLookup): array
    {
        $tz = $activity['timeZone'] ?? $activity['timezone'] ?? null;

        // Group availability by date+time+option to merge category prices
        $grouped = [];

        foreach ($availabilities as $avail) {
            if (!is_array($avail)) continue;

            $date = $this->normalizeDate($avail['date'] ?? $avail['available_date'] ?? null);
            if (!$date) continue;

            $isSoldOut   = (bool) ($avail['soldOut'] ?? $avail['sold_out'] ?? false);
            $isUnlimited = (bool) ($avail['unlimitedAvailability'] ?? $avail['unlimited'] ?? false);
            $available   = (int) ($avail['available'] ?? $avail['availabilityCount'] ?? 0);

            if ($isSoldOut || (!$isUnlimited && $available <= 0)) {
                continue;
            }

            $startTime = $this->normalizeTime($avail['startTime'] ?? $avail['time'] ?? null) ?? '00:00:00';
            $ticketId  = isset($avail['ticket_type_id']) ? (string) $avail['ticket_type_id'] : null;
            $optionId  = isset($avail['option_id']) ? (string) $avail['option_id'] : null;

            // Determine option from ticket lookup
            if (!$optionId && $ticketId && isset($ticketLookup[$ticketId])) {
                $optionId = $ticketLookup[$ticketId]['optionId'] ? (string) $ticketLookup[$ticketId]['optionId'] : 'default';
            }

            $groupKey = "{$date}|{$startTime}|{$optionId}|{$ticketId}";

            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'date'           => $date,
                    'startTime'      => $startTime,
                    'optionId'       => $optionId ?? 'default',
                    'externalId'     => isset($avail['id']) ? (string) $avail['id'] : null,
                    'totalCapacity'  => $available,
                    'isUnlimited'    => $isUnlimited,
                    'seriesId'       => isset($avail['seriesId']) ? (string) $avail['seriesId'] : null,
                    'seriesName'     => $avail['seriesName'] ?? null,
                    'categoryPrices' => [],
                    'rawData'        => $avail,
                ];
            } else {
                // Keep the highest capacity
                $grouped[$groupKey]['totalCapacity'] = max($grouped[$groupKey]['totalCapacity'], $available);
            }

            // Add category price for this ticket type
            if ($ticketId) {
                $info = $ticketLookup[$ticketId] ?? null;
                $nettPrice    = PriceCalculator::firstNumeric([
                    $avail['nettPrice'] ?? null,
                    $avail['nettMerchantPrice'] ?? null,
                ]);
                $sellingPrice = PriceCalculator::firstNumeric([
                    $avail['recommendedSellingPrice'] ?? null,
                    $avail['recommendedMerchantSellingPrice'] ?? null,
                ]);

                if ($sellingPrice !== null || $nettPrice !== null) {
                    $grouped[$groupKey]['categoryPrices'][] = new AvailabilityCategoryPriceDTO(
                        categoryExternalId: $ticketId,
                        categoryCode: $info['categoryCode'] ?? strtoupper($avail['ticket_name'] ?? 'STANDARD'),
                        categoryLabel: $info['categoryLabel'] ?? $avail['ticket_name'] ?? 'Standard',
                        sellingPrice: $sellingPrice ?? $nettPrice ?? 0,
                        nettPrice: $nettPrice,
                        currency: $avail['currency'] ?? $activity['currency'] ?? 'EUR',
                    );
                }
            }
        }

        // Convert grouped data into AvailabilitySlotDTOs
        $slots = [];
        foreach ($grouped as $group) {
            // Calculate display price (lowest category)
            $displayPrice = null;
            foreach ($group['categoryPrices'] as $cp) {
                if ($cp->sellingPrice > 0 && ($displayPrice === null || $cp->sellingPrice < $displayPrice)) {
                    $displayPrice = $cp->sellingPrice;
                }
            }

            $slots[] = new AvailabilitySlotDTO(
                provider: 'globaltix',
                externalId: $group['externalId'],
                optionExternalId: $group['optionId'],
                availableDate: $group['date'],
                startTime: $group['startTime'],
                endTime: null,
                timeZone: $tz,
                totalCapacity: $group['totalCapacity'],
                bookedCount: 0,
                isUnlimited: $group['isUnlimited'],
                isSoldOut: false,
                displayPrice: $displayPrice,
                currency: $activity['currency'] ?? 'EUR',
                categoryPrices: $group['categoryPrices'],
                seriesId: $group['seriesId'],
                seriesName: $group['seriesName'],
                rawData: $group['rawData'],
            );
        }

        return $slots;
    }

    // ─── HELPERS ────────────────────────────────────────────────────────

    private function parseMedia(array $activity): array
    {
        $productService = app(GlobalTixProductService::class);

        $photos = [];
        foreach (($activity['media'] ?? []) as $image) {
            if (!is_array($image)) continue;
            $imageKey = $image['path'] ?? $image['name'] ?? null;
            if (!$imageKey) continue;

            $url = $productService->getImage($imageKey);
            $photos[] = [
                'original'      => $url,
                'large'         => $url,
                'preview'       => $url,
                'external_id'   => $image['id'] ?? null,
                'mime_type'     => $image['type'] ?? null,
                'provider_path' => $image['path'] ?? null,
            ];
        }

        $thumbnail = !empty($activity['image'])
            ? $productService->getImage($activity['image'])
            : ($photos[0]['original'] ?? null);

        return [$photos, $thumbnail];
    }

    private function inferTicketCategory(string $name, ?string $nameTranslated = null): string
    {
        $lower = strtolower($name);
        $lowerTrans = $nameTranslated ? strtolower($nameTranslated) : '';

        return match (true) {
            str_contains($lower, 'child') || str_contains($lower, 'kid') || str_contains($lowerTrans, 'child') || str_contains($lowerTrans, 'kid') => 'CHILD',
            str_contains($lower, 'infant') || str_contains($lower, 'baby') || str_contains($lowerTrans, 'infant') || str_contains($lowerTrans, 'baby') => 'INFANT',
            str_contains($lower, 'senior') || str_contains($lower, 'elder') || str_contains($lowerTrans, 'senior') || str_contains($lowerTrans, 'elder') => 'SENIOR',
            str_contains($lower, 'youth') || str_contains($lower, 'teen') || str_contains($lowerTrans, 'youth') || str_contains($lowerTrans, 'teen') => 'YOUTH',
            str_contains($lower, 'adult') || str_contains($lowerTrans, 'adult') => 'ADULT',
            str_contains($lower, 'family') || str_contains($lowerTrans, 'family') => 'FAMILY',
            str_contains($lower, 'student') || str_contains($lowerTrans, 'student') => 'CONCESSION',
            default => Str::upper(Str::slug($name, '_')),
        };
    }

    private function extractValueArray(array|string|null $raw): array
    {
        if (empty($raw)) return [];
        if (is_string($raw)) return [strip_tags($raw)];

        $result = [];
        foreach ($raw as $item) {
            if (is_array($item)) {
                $value = $item['value'] ?? $item['label'] ?? $item['name'] ?? null;
                if ($value !== null) $result[] = strip_tags((string) $value);
            } elseif (is_string($item)) {
                $result[] = strip_tags($item);
            }
        }
        return array_values(array_filter($result));
    }

    /**
     * Normalize provider text fields that may arrive as nested arrays.
     */
    private function normalizeTextPayload(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        if (is_string($raw)) {
            $clean = trim(strip_tags($raw));
            return $clean !== '' ? $clean : null;
        }

        if (is_scalar($raw)) {
            $clean = trim((string) $raw);
            return $clean !== '' ? $clean : null;
        }

        if (!is_array($raw)) {
            return null;
        }

        $parts = [];
        array_walk_recursive($raw, function ($value) use (&$parts): void {
            if (!is_scalar($value)) {
                return;
            }

            $clean = trim(strip_tags((string) $value));
            if ($clean !== '') {
                $parts[] = $clean;
            }
        });

        if ($parts !== []) {
            return implode('; ', array_values(array_unique($parts)));
        }

        $json = json_encode($raw);
        return $json === false ? null : $json;
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
