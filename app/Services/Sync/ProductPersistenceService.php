<?php

namespace App\Services\Sync;

use App\DTOs\AvailabilityCategoryPriceDTO;
use App\DTOs\AvailabilitySlotDTO;
use App\DTOs\PriceRowDTO;
use App\DTOs\PricingCategoryDTO;
use App\DTOs\ProductOptionDTO;
use App\DTOs\SyncedProductDTO;
use App\DTOs\TieredPriceDTO;
use App\Models\AvailabilityCategoryPrice;
use App\Models\AvailabilityCheck;
use App\Models\CancellationPolicy;
use App\Models\Categories;
use App\Models\Keyword;
use App\Models\Location;
use App\Models\OptionPricingCategory;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductOption;
use App\Models\ProductVideo;
use App\Models\TieredPrice;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Persists a SyncedProductDTO to all normalized tables.
 *
 * This is the ONLY class that writes to the database during product sync.
 * All provider-specific logic lives in the Normalizers — this class is provider-agnostic.
 *
 * Tables written:
 *   vendors, products, product_options, option_pricing_categories,
 *   locations, cancellation_policies, keywords,
 *   product_images, product_videos,
 *   prices, tiered_prices,
 *   availability, availability_category_prices
 */
final class ProductPersistenceService
{
    /**
     * Persist a complete SyncedProductDTO to the database.
     *
     * @return Product The persisted product model
     */
    public function persist(SyncedProductDTO $dto, bool $forceUpdate = false): Product
    {
        return DB::transaction(function () use ($dto, $forceUpdate) {
            $existingProduct = Product::withTrashed()
                ->where('product_api_id', $dto->productApiId)
                ->where(function ($q) use ($dto) {
                    $q->where('platform', $dto->provider)
                        ->orWhere('provider', $dto->provider);
                })
                ->first();

            // 1. Vendor
            $vendor = $this->persistVendor($dto);

            // 2. Category — only use existing categories (admins create via admin panel)
            $category = $this->persistCategory($dto->categoryName ?? 'Activity');

            // 3. Product (core + TIER 1 display pricing)
            $product = $this->persistProduct($dto, $vendor, $category, $forceUpdate, $existingProduct);

            // 4. Product Options + Pricing Categories
            $optionMap = $this->persistOptions($product, $dto);

            // 5. Location
            $this->persistLocation($product, $dto);

            // 6. Cancellation policy
            $this->persistCancellationPolicy($product, $dto);

            // 7. Keywords
            $this->persistKeywords($product, $dto);

            // 8. Media (photos + videos)
            $this->persistMedia($product, $dto);

            // 9. TIER 2 — Prices + Tiered Prices
            $this->persistPrices($product, $dto, $optionMap);

            // 10. TIER 3 — Availability + Category Prices
            // Always attempt to persist availability slots. If the DTO has none,
            // persistAvailability() is a no-op. Products are never blocked by this.
            $this->persistAvailability($product, $dto, $optionMap);

            // 12. Update display prices
            $this->updateDisplayPrices($product, $dto);

            // 13. Save raw response
            $this->saveRawResponse($product, $dto);

            return $product;
        }, 3);
    }

    // ─── 1. VENDOR ──────────────────────────────────────────────────────

    private function persistVendor(SyncedProductDTO $dto): Vendor
    {
        $vDto = $dto->vendor;
        $lookup = $vDto->externalId
            ? ['provider' => $vDto->provider, 'external_id' => $vDto->externalId]
            : ['provider' => $vDto->provider, 'name' => $vDto->name];

        $vendor = Vendor::firstOrCreate($lookup, [
            'name'        => $vDto->name,
            'currency'    => $vDto->currency,
            'rating'      => $vDto->rating,
            'reviews'     => $vDto->reviews,
            'link'        => $vDto->link,
            'logo'        => $vDto->logo,
        ]);

        $vendor->fill([
            'name'        => $vDto->name,
            'provider'    => $vDto->provider,
            'external_id' => $vDto->externalId,
            'currency'    => $vDto->currency,
            'rating'      => $vDto->rating,
            'reviews'     => $vDto->reviews,
            'link'        => $vDto->link,
            'logo'        => $vDto->logo,
        ])->save();

        return $vendor;
    }

    // ─── 2. CATEGORY ────────────────────────────────────────────────────

    private function persistCategory(string $name): ?Categories
    {
        // Only match existing categories created by admins via /admin/category.
        // NEVER auto-create categories during import.
        $category = Categories::where('name', $name)->first();

        if ($category) {
            return $category;
        }

        Log::debug('ProductPersistenceService: No matching category found for product', [
            'category_name' => $name,
        ]);

        return null;
    }

    // ─── 3. PRODUCT ─────────────────────────────────────────────────────

    private function persistProduct(
        SyncedProductDTO $dto,
        Vendor $vendor,
        ?Categories $category,
        bool $forceUpdate,
        ?Product $existing = null
    ): Product
    {
        $slug = $existing?->slug ?: $this->makeUniqueSlug($dto->title);

        // Status: keep existing product's status on update, default to 'active' for new products.
        // Availability (or lack thereof) never downgrades the product status during sync.
        $status = ($existing !== null && !$forceUpdate)
            ? ($existing->status ?: 'active')
            : 'active';

        $payload = [
            'platform'                => $dto->provider,
            'provider'                => $dto->provider,
            'vendor_id'               => $vendor->id,
            'barcode_type'            => $dto->barcodeType ?? 'Standard',
            'category_id'             => $category?->id,
            'title'                   => $dto->title,
            'description'             => $dto->description,
            'thumbnail'               => $dto->thumbnail,
            'slug'                    => $slug,

            // Duration
            'duration'                => (string) ($dto->duration ?? ''),
            'durationType'            => $dto->durationType ?? 'HOURS',
            'durationMinutes'         => $dto->durationMinutes,
            'durationHours'           => $dto->durationHours,
            'durationDays'            => $dto->durationDays,
            'durationWeeks'           => $dto->durationWeeks,
            'durationText'            => $dto->durationText,

            // Classification
            'difficulty'              => $dto->difficulty,
            'activityType'            => $dto->activityType,
            'scheduleType'            => $dto->scheduleType,
            'bookingType'             => $dto->bookingType,
            'meetingType'             => $dto->meetingType,

            // Cutoff
            'cutoffType'              => $dto->cutoffType,
            'cutoffReferenceHour'     => $dto->cutoffReferenceHour,
            'cutoffReferenceMinute'   => $dto->cutoffReferenceMinute,
            'bookingCutoffMinutes'    => $dto->bookingCutoffMinutes,
            'bookingCutoffHours'      => $dto->bookingCutoffHours,
            'bookingCutoffDays'       => $dto->bookingCutoffDays,
            'bookingCutoffWeeks'      => $dto->bookingCutoffWeeks,

            // Capacity
            'capacityType'            => $dto->capacityType,
            'passCapacity'            => $dto->passCapacity,
            'passesAvailable'         => $dto->passesAvailable,

            // TIER 1 — Display pricing
            'original_price'          => $dto->calculatedDisplayPrices()['original_price'],
            'from_price'              => $dto->calculatedDisplayPrices()['from_price'],
            'default_currency'        => $dto->defaultCurrency,

            // Flags
            'is_instant_confirmation' => $dto->isInstantConfirmation,
            'is_cancellable'          => $dto->isCancellable,
            'privateActivity'         => $dto->isPrivate,
            'private_tour'            => $dto->isPrivate ? 'YES' : '',
            'pickup_service'          => $dto->isPickupAvailable ? 'YES' : ($dto->pickupService ?? ''),
            'passportRequired'        => $dto->isPassportRequired,
            'allowCustomizedBookings' => $dto->allowCustomizedBookings,
            'published'               => $dto->published,

            // Reviews are handled directly through the Review model via Eloquent events.
            // External syncs should NOT overwrite them.

            // Content (JSON)
            'included'                => $dto->inclusions,
            'excluded'                => $dto->exclusions,
            'inclusions'              => $dto->inclusions,
            'exclusions'              => $dto->exclusions,
            'highlights'              => $dto->highlights,
            'know_before_you_go'      => $dto->knowBeforeYouGo,
            'itinerary'               => $dto->itinerary,
            'activityCategories'      => $dto->activityCategories,
            'activityAttributes'      => $dto->activityAttributes,
            'startTimes'              => $dto->startTimes,
            'guidanceTypes'           => $dto->guidanceTypes,
            'bookableExtras'          => $dto->bookableExtras,
            'startPoints'             => $dto->startPoints,
            'booking_questions_data'  => $this->extractBookingQuestionsFromRaw($dto),
            'passenger_fields_data'   => $this->extractPassengerFieldsFromRaw($dto),
            'contact_fields_data'     => $this->extractContactFieldsFromRaw($dto),

            // Provider-specific
            'time_zone'               => $dto->timeZone,
            'timezone_offset'         => $dto->timezoneOffset,
            'minAge'                  => $dto->minAge,
            'dressCode'               => $dto->dressCode,
            'defaultRateId'           => $dto->defaultRateId,
            'pickupService'           => $dto->pickupService,
            'dropoffService'          => $dto->dropoffService,
            'merchant_name'           => $dto->merchantName,

            // Status
            'status'                  => $status,
            'last_synced_at'          => now(),
        ];

        if ($existing) {
            // If the existing product was soft-deleted, restore it first
            if ($existing->trashed()) {
                $existing->restore();
            }
            $existing->update($payload);
            return $existing;
        }

        return Product::create(array_merge($payload, [
            'product_api_id' => $dto->productApiId,
        ]));
    }

    // ─── 4. PRODUCT OPTIONS + PRICING CATEGORIES ────────────────────────

    /**
     * Persist options and their categories. Returns a map for FK resolution.
     *
     * @return array{optionIdMap: array<string, int>, categoryIdMap: array<string, int>}
     */
    private function persistOptions(Product $product, SyncedProductDTO $dto): array
    {
        $optionIdMap   = []; // externalId => product_options.id
        $categoryIdMap = []; // externalId => option_pricing_categories.id
        $keptOptionIds = [];
        $categoryBasePriceMap = [];
        $categoryFallbackMap = [];

        foreach ($dto->priceRows as $priceRow) {
            /** @var PriceRowDTO $priceRow */
            if ($priceRow->categoryExternalId === null || $priceRow->categoryExternalId === '') {
                continue;
            }

            $payload = [
                'selling_price' => $priceRow->sellingPrice,
                'nett_price' => $priceRow->nettPrice,
                'original_price' => $priceRow->originalPrice,
                'recommended_price' => $priceRow->recommendedPrice,
                'merchant_price' => $priceRow->merchantPrice,
                'currency' => $priceRow->currency,
            ];

            $compositeKey = ($priceRow->optionExternalId ?? '') . '|' . $priceRow->categoryExternalId;
            if (!isset($categoryBasePriceMap[$compositeKey])) {
                $categoryBasePriceMap[$compositeKey] = $payload;
            }
            if (!isset($categoryFallbackMap[$priceRow->categoryExternalId])) {
                $categoryFallbackMap[$priceRow->categoryExternalId] = $payload;
            }
        }

        $seenOptionExternalIds = [];
        foreach ($dto->options as $optionDto) {
            /** @var ProductOptionDTO $optionDto */
            $optExtId = (string) ($optionDto->externalId ?? '');
            if ($optExtId !== '' && isset($seenOptionExternalIds[$optExtId])) {
                continue;
            }
            if ($optExtId !== '') {
                $seenOptionExternalIds[$optExtId] = true;
            }

            $option = null;
            if ($optExtId !== '') {
                $option = ProductOption::where('product_id', $product->id)
                    ->where('external_id', $optExtId)
                    ->first();
            }

            $optionPayload = [
                'product_id'  => $product->id,
                'provider'    => $optionDto->provider,
                'external_id' => $optionDto->externalId,
                'name'        => $optionDto->name,
                'description' => $optionDto->description,
                'currency'    => $optionDto->currency,
                'ticket_format' => $optionDto->ticketFormat,
                'ticket_validity' => $optionDto->ticketValidity,
                'defined_duration' => $optionDto->definedDuration,
                'is_dynamic_pricing' => $optionDto->isDynamicPricing,
                'is_capacity' => $optionDto->isCapacity,
                'is_cancellable' => $optionDto->isCancellable,
                'time_slot' => $optionDto->timeSlot,
                'visit_date' => $optionDto->visitDate,
                'advance_booking' => $optionDto->advanceBooking,
                'questions' => $optionDto->questions,
                'inclusions' => $optionDto->inclusions,
                'exclusions' => $optionDto->exclusions,
                'cancellation_policy' => $optionDto->cancellationPolicy,
                'cancellation_notes' => $optionDto->cancellationNotes,
                'publish_start' => $optionDto->publishStart,
                'publish_end' => $optionDto->publishEnd,
                'redeem_start' => $optionDto->redeemStart,
                'redeem_end' => $optionDto->redeemEnd,
                'is_default'  => $optionDto->isDefault,
                'sort_order'  => $optionDto->sortOrder,
                'metadata'    => $optionDto->metadata ?: null,
            ];

            if ($option) {
                $option->fill($optionPayload)->save();
            } else {
                $option = ProductOption::create($optionPayload);
            }

            $keptOptionIds[] = $option->id;
            $optionIdMap[$optionDto->externalId] = $option->id;
            $keptCategoryIds = [];
            $seenCatExternalIds = [];

            // Persist pricing categories for this option
            foreach ($optionDto->categories as $catDto) {
                /** @var PricingCategoryDTO $catDto */
                $catExtId = (string) ($catDto->externalId ?? '');
                if ($catExtId !== '' && isset($seenCatExternalIds[$catExtId])) {
                    continue;
                }
                if ($catExtId !== '') {
                    $seenCatExternalIds[$catExtId] = true;
                }

                $categoryIdempotencyKey = md5(implode('|', [
                    $product->id,
                    $optionDto->provider,
                    $optionDto->externalId,
                    $catDto->externalId,
                    strtoupper((string) $catDto->categoryCode),
                    strtolower(trim((string) $catDto->categoryLabel)),
                    $catDto->ageFrom,
                    $catDto->ageTo,
                    $catDto->sortOrder,
                ]));

                $basePrices = $categoryBasePriceMap[$optionDto->externalId . '|' . $catDto->externalId]
                    ?? $categoryFallbackMap[$catDto->externalId]
                    ?? [];

                $cat = null;
                if ($catExtId !== '') {
                    $cat = OptionPricingCategory::where('product_option_id', $option->id)
                        ->where('external_id', $catExtId)
                        ->first();
                }

                if (!$cat) {
                    $cat = OptionPricingCategory::where('idempotency_key', $categoryIdempotencyKey)->first();
                }

                $catPayload = [
                    'idempotency_key'   => $categoryIdempotencyKey,
                    'product_option_id' => $option->id,
                    'provider'          => $optionDto->provider,
                    'external_id'       => $catDto->externalId ?: null,
                    'category_code'     => $catDto->categoryCode,
                    'category_label'    => $catDto->categoryLabel,
                    'age_from'          => $catDto->ageFrom,
                    'age_to'            => $catDto->ageTo,
                    'occupancy'         => $catDto->occupancy,
                    'group_size'        => $catDto->groupSize,
                    'selling_price'     => $catDto->sellingPrice ?? ($basePrices['selling_price'] ?? null),
                    'nett_price'        => $catDto->nettPrice ?? ($basePrices['nett_price'] ?? null),
                    'original_price'    => $catDto->originalPrice ?? ($basePrices['original_price'] ?? null),
                    'recommended_price' => $catDto->recommendedPrice ?? ($basePrices['recommended_price'] ?? null),
                    'merchant_price'    => $catDto->merchantPrice ?? ($basePrices['merchant_price'] ?? null),
                    'currency'          => $catDto->currency ?: ($basePrices['currency'] ?? $dto->defaultCurrency),
                    'min_purchase_qty'  => $catDto->minPurchaseQty,
                    'max_purchase_qty'  => $catDto->maxPurchaseQty,
                    'is_default'        => $catDto->isDefault,
                    'is_age_qualified'  => $catDto->isAgeQualified,
                    'sort_order'        => $catDto->sortOrder,
                    'metadata'          => [
                        'option_external_id' => $optionDto->externalId,
                        'provider'           => $optionDto->provider,
                    ],
                ];

                if ($cat) {
                    $cat->fill($catPayload)->save();
                } else {
                    $cat = OptionPricingCategory::create($catPayload);
                }

                $keptCategoryIds[] = $cat->id;
                if ($catDto->externalId !== '') {
                    $categoryIdMap[$catDto->externalId] = $cat->id;
                }
            }

            OptionPricingCategory::where('product_option_id', $option->id)
                ->when(
                    !empty($keptCategoryIds),
                    fn($q) => $q->whereNotIn('id', $keptCategoryIds),
                    fn($q) => $q
                )
                ->delete();
        }

        ProductOption::where('product_id', $product->id)
            ->when(
                !empty($keptOptionIds),
                fn($q) => $q->whereNotIn('id', $keptOptionIds),
                fn($q) => $q
            )
            ->delete();

        return ['optionIdMap' => $optionIdMap, 'categoryIdMap' => $categoryIdMap];
    }

    // ─── 5. LOCATION ────────────────────────────────────────────────────

    private function persistLocation(Product $product, SyncedProductDTO $dto): void
    {
        if ($dto->location === null) {
            return;
        }

        $loc = $dto->location;

        Location::updateOrCreate(
            ['product_id' => $product->id],
            [
                'city'         => $loc->city,
                'countryCode'  => $loc->countryCode,
                'country'      => $loc->country,
                'address'      => $loc->address,
                'postal_code'  => $loc->postalCode,
                'city_id'      => $loc->cityId,
                'latitude'     => $loc->latitude,
                'longitude'    => $loc->longitude,
                'coordinates'  => [
                    'latitude'  => $loc->latitude,
                    'longitude' => $loc->longitude,
                ],
            ]
        );
    }

    // ─── 6. CANCELLATION POLICY ─────────────────────────────────────────

    private function persistCancellationPolicy(Product $product, SyncedProductDTO $dto): void
    {
        if ($dto->cancellationPolicy === null) {
            return;
        }

        $cp = $dto->cancellationPolicy;
        $policyType = strtoupper((string) ($cp->policyType ?? ''));
        $isCancellable = !in_array($policyType, ['NON_REFUNDABLE', 'NON-REFUNDABLE'], true);
        $tax = $cp->tax;
        $taxValue = is_scalar($tax)
            ? (string) $tax
            : (is_array($tax) ? json_encode($tax) : null);

        CancellationPolicy::updateOrCreate(
            ['product_id' => $product->id, 'provider' => $dto->provider],
            [
                'provider'   => $dto->provider,
                'title'      => $cp->title,
                'policy'     => $cp->description ?? $cp->title,
                'policyType' => $cp->policyType,
                'tax'        => $taxValue,
                'is_cancellable' => $isCancellable,
                'cancellation_notes' => $cp->metadata['cancellation_notes'] ?? [],
                'penalty_rules' => $cp->penaltyRules,
                'meta'       => $cp->metadata ?: null,
            ]
        );
    }

    // ─── 7. KEYWORDS ────────────────────────────────────────────────────

    private function persistKeywords(Product $product, SyncedProductDTO $dto): void
    {
        if (empty($dto->keywords)) {
            return;
        }

        Keyword::where('product_id', $product->id)->delete();

        $batch = [];
        $now = now();

        foreach ($dto->keywords as $name) {
            $clean = trim((string) $name);
            if ($clean !== '') {
                $batch[] = [
                    'product_id' => $product->id,
                    'name'       => $clean,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($batch)) {
            Keyword::insert($batch);
        }
    }

    // ─── 8. MEDIA ───────────────────────────────────────────────────────

    private function persistMedia(Product $product, SyncedProductDTO $dto): void
    {
        // ─── Images ──────────────────────────────────────────────────────
        if (!empty($dto->photos)) {
            ProductImage::where('product_id', $product->id)->delete();

            foreach ($dto->photos as $index => $photo) {
                if (!is_array($photo)) continue;

                ProductImage::create([
                    'product_id'    => $product->id,
                    'provider'      => $dto->provider,
                    'original_url'  => $photo['original'] ?? null,
                    'large_url'     => $photo['large'] ?? $photo['original'] ?? null,
                    'preview_url'   => $photo['preview'] ?? $photo['large'] ?? $photo['original'] ?? null,
                    'alt_text'      => $photo['alt_text'] ?? null,
                    'external_id'   => isset($photo['external_id']) ? (string) $photo['external_id'] : null,
                    'provider_path' => $photo['provider_path'] ?? null,
                    'mime_type'     => $photo['mime_type'] ?? null,
                    'extension'     => $photo['extension'] ?? null,
                    'file_size'     => $photo['file_size'] ?? null,
                    'is_primary'    => $index === 0,
                    'is_active'     => true,
                    'sort_order'    => $index,
                    'metadata'      => $photo['meta'] ?? null,
                ]);
            }
        }

        // ─── Videos ──────────────────────────────────────────────────────
        if (!empty($dto->videos)) {
            ProductVideo::where('product_id', $product->id)->delete();

            foreach ($dto->videos as $index => $video) {
                if (!is_array($video)) continue;

                ProductVideo::create([
                    'product_id'    => $product->id,
                    'provider'      => $dto->provider,
                    'source_url'    => $video['sourceUrl'] ?? $video['source_url'] ?? null,
                    'thumbnail_url' => $video['thumbnailUrl'] ?? $video['thumbnail_url'] ?? null,
                    'preview_url'   => $video['previewUrl'] ?? $video['preview_url'] ?? null,
                    'embed_html'    => $video['html'] ?? $video['embed_html'] ?? null,
                    'title'         => $video['title'] ?? null,
                    'description'   => $video['description'] ?? null,
                    'external_id'   => isset($video['id']) ? (string) $video['id'] : null,
                    'provider_name' => $video['providerName'] ?? $video['provider_name'] ?? null,
                    'duration'      => $video['duration'] ?? null,
                    'is_primary'    => $index === 0,
                    'is_active'     => true,
                    'sort_order'    => $index,
                    'metadata'      => $video,
                ]);
            }
        }

        // Backward-compatible JSON for existing API consumers
        $product->forceFill([
            'image_gallery' => $dto->photos,
            'video_gallery' => $dto->videos,
        ])->save();
    }

    // ─── 9. TIER 2 — PRICES + TIERED PRICES ────────────────────────────

    private function persistPrices(Product $product, SyncedProductDTO $dto, array $optionMap): void
    {
        $optionIdMap   = $optionMap['optionIdMap'];
        $categoryIdMap = $optionMap['categoryIdMap'];
        $currentKeys   = [];
        $categoryTierMap = [];
        $upsertRows = [];
        $seenPriceKeys = [];

        foreach ($dto->priceRows as $index => $priceRow) {
            /** @var PriceRowDTO $priceRow */
            $key = $priceRow->idempotencyKey($product->id);
            if (isset($seenPriceKeys[$key])) {
                continue;
            }
            $seenPriceKeys[$key] = true;
            $currentKeys[] = $key;

            // Resolve FK IDs from the maps
            $productOptionId   = $priceRow->optionExternalId !== null
                ? ($optionIdMap[$priceRow->optionExternalId] ?? null)
                : null;
            $pricingCategoryId = $priceRow->categoryExternalId !== null
                ? ($categoryIdMap[$priceRow->categoryExternalId] ?? null)
                : null;

            if ($pricingCategoryId !== null) {
                $existingTiers = $categoryTierMap[$pricingCategoryId] ?? null;
                if ($existingTiers === null || (!empty($priceRow->tieredPrices) && empty($existingTiers))) {
                    $categoryTierMap[$pricingCategoryId] = $priceRow->tieredPrices;
                }
            }

            $upsertRows[] = [
                'idempotency_key' => $key,
                'product_id' => $product->id,
                'product_option_id' => $productOptionId,
                'pricing_category_id' => $pricingCategoryId,
                'provider' => $priceRow->provider !== '' ? $priceRow->provider : $dto->provider,
                'source_type' => 'date_range_override',
                'price_kind' => $priceRow->categoryCode ? strtolower($priceRow->categoryCode) : 'category',
                'external_id' => $priceRow->categoryExternalId,
                'rate_id' => $priceRow->optionExternalId,
                'ticket_type_id' => $priceRow->categoryExternalId,
                'ticket_type_name' => $priceRow->categoryLabel,
                'category_code' => $priceRow->categoryCode,
                'category_name' => $priceRow->categoryLabel,
                'valid_from' => $priceRow->validFrom,
                'valid_to' => $priceRow->validTo,
                'selling_price' => $priceRow->sellingPrice,
                'nett_price' => $priceRow->nettPrice,
                'original_price' => $priceRow->originalPrice,
                'recommended_price' => $priceRow->recommendedPrice,
                'merchant_price' => $priceRow->merchantPrice,
                'currency' => $priceRow->currency !== '' ? $priceRow->currency : $dto->defaultCurrency,
                'tax_amount' => $priceRow->taxAmount,
                'tax_percentage' => $priceRow->taxPercentage,
                'discount_percentage' => $priceRow->discountPercentage,
                'discounted_price' => $priceRow->discountedPrice,
                'is_default' => $priceRow->isDefault,
                'sort_order' => $index,
                'metadata' => json_encode([
                    'option_external_id' => $priceRow->optionExternalId,
                    'category_external_id' => $priceRow->categoryExternalId,
                    'is_converted' => $priceRow->isConverted,
                ]),
            ];
        }

        // Bulk upsert all price rows at once
        if (!empty($upsertRows)) {
            Price::upsert($upsertRows, ['idempotency_key'], [
                'product_id', 'product_option_id', 'pricing_category_id',
                'provider', 'source_type', 'price_kind',
                'external_id', 'rate_id', 'ticket_type_id', 'ticket_type_name',
                'category_code', 'category_name',
                'valid_from', 'valid_to',
                'selling_price', 'nett_price', 'original_price', 'recommended_price', 'merchant_price',
                'currency', 'tax_amount', 'tax_percentage',
                'discount_percentage', 'discounted_price',
                'is_default', 'sort_order', 'metadata',
            ]);
        }

        // Delete stale rows
        Price::where('product_id', $product->id)
            ->when(!empty($currentKeys), fn($q) => $q->whereNotIn('idempotency_key', $currentKeys))
            ->delete();

        foreach ($categoryTierMap as $pricingCategoryId => $tiers) {
            $this->persistTieredPrices((int) $pricingCategoryId, $tiers);
        }
    }

    /**
     * Persist TieredPriceDTO[] for a single pricing category.
     */
    private function persistTieredPrices(int $pricingCategoryId, array $tieredPrices): void
    {
        TieredPrice::where('pricing_category_id', $pricingCategoryId)->delete();

        $batch = [];
        $now = now();

        foreach ($tieredPrices as $index => $tierDto) {
            /** @var TieredPriceDTO $tierDto */
            $batch[] = array_merge($tierDto->toDatabaseRow($pricingCategoryId), [
                'sort_order' => $index,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (!empty($batch)) {
            TieredPrice::insert($batch);
        }
    }

    // ─── 10. TIER 3 — AVAILABILITY + CATEGORY PRICES ───────────────────

    private function persistAvailability(Product $product, SyncedProductDTO $dto, array $optionMap): void
    {
        // If the provider payload did not include availability data at all, do not touch existing availability.
        // Wiping records just because availability was missing from the fetch would be destructive.
        if (!$dto->hasAvailabilityData) {
            return;
        }

        $optionIdMap   = $optionMap['optionIdMap'];
        $categoryIdMap = $optionMap['categoryIdMap'];
        $currentKeys   = [];
        $seenSlotKeys  = [];

        foreach ($dto->availabilitySlots as $slot) {
            /** @var AvailabilitySlotDTO $slot */
            $key = $slot->idempotencyKey($product->id);
            if (isset($seenSlotKeys[$key])) {
                continue;
            }
            $seenSlotKeys[$key] = true;
            $currentKeys[] = $key;

            $productOptionId = $optionIdMap[$slot->optionExternalId] ?? null;
            $startAt = $slot->availableDate . ' ' . ($slot->startTime ?? '00:00:00');

            $availability = AvailabilityCheck::updateOrCreate(
                ['idempotency_key' => $key],
                [
                    'product_id'             => $product->id,
                    'product_option_id'      => $productOptionId,
                    'provider'               => $slot->provider,
                    'external_id'            => $slot->externalId,
                    'available_date'         => $slot->availableDate,
                    'start_time'             => $slot->startTime ?? '00:00:00',
                    'end_time'               => $slot->endTime,
                    'start_at'               => $startAt,
                    'time_zone'              => $slot->timeZone,
                    'capacity_total'         => $slot->totalCapacity,
                    'capacity_booked'        => $slot->bookedCount,
                    'capacity_reserved'      => 0,
                    'unlimited_availability' => $slot->isUnlimited,
                    'sold_out'               => $slot->isSoldOut,
                    'min_participants'       => $slot->minParticipants,
                    'max_participants'       => $slot->maxParticipants,
                    'display_price'          => $slot->displayPrice,
                    'currency'               => $slot->currency,
                    'is_active'              => $slot->isActive,
                    'is_closed'              => $slot->isClosed,
                    'series_id'              => $slot->seriesId,
                    'series_name'            => $slot->seriesName,
                    'raw_data'               => $this->compactAvailabilityRawData($slot->rawData ?: null),
                ]
            );

            // Persist category prices for this availability slot
            $this->persistAvailabilityCategoryPrices($availability, $slot->categoryPrices, $categoryIdMap);
        }

        // Delete stale availability rows that were not in this fresh sync batch.
        // Safe: $currentKeys is non-empty here because we returned early above if slots were empty.
        AvailabilityCheck::where('product_id', $product->id)
            ->whereNotIn('idempotency_key', $currentKeys)
            ->delete();
    }

    /**
     * Persist AvailabilityCategoryPriceDTO[] for a single availability slot.
     */
    private function persistAvailabilityCategoryPrices(
        AvailabilityCheck $availability,
        array $categoryPrices,
        array $categoryIdMap
    ): void {
        // Delete old and re-insert (idempotent)
        AvailabilityCategoryPrice::where('availability_id', $availability->id)->delete();

        foreach ($categoryPrices as $cpDto) {
            /** @var AvailabilityCategoryPriceDTO $cpDto */
            $pricingCategoryId = $cpDto->categoryExternalId !== ''
                ? ($categoryIdMap[$cpDto->categoryExternalId] ?? null)
                : null;

            AvailabilityCategoryPrice::create(
                $cpDto->toDatabaseRow($availability->id, $pricingCategoryId)
            );
        }
    }

    // ─── 11. DISPLAY PRICES ─────────────────────────────────────────────

    /**
     * Update product-level display prices from the synced data.
     */
    private function updateDisplayPrices(Product $product, SyncedProductDTO $dto): void
    {
        $prices = $dto->calculatedDisplayPrices();

        $product->forceFill([
            'default_currency' => $dto->defaultCurrency,
            'original_price'   => $prices['original_price'],
            'from_price'       => $prices['from_price'],
        ])->save();
    }

    // ─── 12. RAW RESPONSE ───────────────────────────────────────────────

    private function saveRawResponse(Product $product, SyncedProductDTO $dto): void
    {
        if (empty($dto->rawResponse)) {
            return;
        }

        try {
            $payload = $this->compactRawResponseForStorage($dto->rawResponse, $dto);
            $payload['_meta'] = array_merge($payload['_meta'] ?? [], [
                'provider'       => $dto->provider,
                'product_api_id' => $dto->productApiId,
                'synced_at'      => now()->toISOString(),
            ]);

            $product->forceFill([
                'raw_response'          => $payload,
                'raw_response_saved_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            Log::error('Failed to save raw response snapshot', [
                'product_api_id' => $dto->productApiId,
                'provider' => $dto->provider,
                'message' => substr($e->getMessage(), 0, 1000),
            ]);
        }
    }

    // ─── HELPERS ────────────────────────────────────────────────────────

    /**
     * Keep raw provider payloads useful for audits without allowing massive
     * availability arrays to exhaust PHP memory during Laravel JSON casting.
     */
    private function compactRawResponseForStorage(array $payload, SyncedProductDTO $dto): array
    {
        $omitted = [];

        if ($dto->provider === 'bokun') {
            $this->compactArrayAtPath($payload, ['data', 'availabilities'], 'data.availabilities', $omitted);
            $this->compactArrayAtPath($payload, ['data', 'availability'], 'data.availability', $omitted);
        }

        if ($dto->provider === 'globaltix') {
            $this->compactArrayAtPath($payload, ['data', 'availability', 'data'], 'data.availability.data', $omitted);
            $this->compactArrayAtPath($payload, ['availability', 'data'], 'availability.data', $omitted);
        }

        if ($omitted !== []) {
            $payload['_storage'] = array_merge($payload['_storage'] ?? [], [
                'raw_compacted' => true,
                'reason' => 'Large availability payload is stored in normalized availability tables.',
                'omitted_paths' => $omitted,
            ]);
        }

        return $payload;
    }

    private function compactArrayAtPath(array &$payload, array $path, string $label, array &$omitted, int $sampleSize = 3): void
    {
        $ref =& $payload;

        foreach ($path as $segment) {
            if (!is_array($ref) || !array_key_exists($segment, $ref)) {
                return;
            }

            $ref =& $ref[$segment];
        }

        if (!is_array($ref) || $ref === []) {
            return;
        }

        $count = count($ref);
        $sample = array_slice($ref, 0, $sampleSize);

        $ref = [
            '_compacted' => true,
            'count' => $count,
            'sample' => $sample,
        ];

        $omitted[] = [
            'path' => $label,
            'count' => $count,
            'sample_size' => count($sample),
        ];
    }

    private function compactAvailabilityRawData(?array $rawData): ?array
    {
        if ($rawData === null || $rawData === []) {
            return null;
        }

        $payload = $rawData;
        $omitted = [];

        foreach ([
            'pricesByRate',
            'pricesByCategory',
            'pricePerCategoryUnit',
            'passengers',
            'rates',
            'tickets',
            'prices',
        ] as $key) {
            if (!isset($payload[$key]) || !is_array($payload[$key]) || $payload[$key] === []) {
                continue;
            }

            $count = count($payload[$key]);
            if ($count <= 10) {
                continue;
            }

            $sample = array_slice($payload[$key], 0, 3);
            $payload[$key] = [
                '_compacted' => true,
                'count' => $count,
                'sample' => $sample,
            ];

            $omitted[] = [
                'path' => $key,
                'count' => $count,
                'sample_size' => count($sample),
            ];
        }

        if ($omitted !== []) {
            $payload['_storage'] = [
                'raw_compacted' => true,
                'reason' => 'Large nested pricing arrays are normalized into availability_category_prices.',
                'omitted_paths' => $omitted,
            ];
        }

        return $payload;
    }

    private function extractBookingQuestionsFromRaw(SyncedProductDTO $dto): array
    {
        $raw = $dto->rawResponse;

        if ($dto->provider === 'bokun') {
            $questions = data_get($raw, 'data.activity.bookingQuestions', []);
            return is_array($questions) ? array_values(array_filter($questions, 'is_array')) : [];
        }

        if ($dto->provider === 'globaltix') {
            $rows = [];
            $options = data_get($raw, 'data.activityOptions.data', []);
            if (!is_array($options)) {
                return [];
            }

            foreach ($options as $option) {
                if (!is_array($option)) {
                    continue;
                }

                foreach (($option['questions'] ?? []) as $question) {
                    if (!is_array($question)) {
                        continue;
                    }

                    $rows[] = [
                        'questionCode' => $question['id'] ?? null,
                        'label' => $question['label'] ?? $question['name'] ?? null,
                        'required' => (bool) ($question['required'] ?? false),
                        'dataType' => $question['type'] ?? 'STRING',
                        'options' => $question['options'] ?? [],
                    ];
                }
            }

            return $rows;
        }

        return [];
    }

    private function extractPassengerFieldsFromRaw(SyncedProductDTO $dto): array
    {
        if ($dto->provider !== 'bokun') {
            return [];
        }

        $fields = data_get($dto->rawResponse, 'data.activity.passengerFields', []);
        return is_array($fields) ? array_values(array_filter($fields, 'is_array')) : [];
    }

    private function extractContactFieldsFromRaw(SyncedProductDTO $dto): array
    {
        if ($dto->provider !== 'bokun') {
            return [];
        }

        $rows = [];
        $mainContactFields = data_get($dto->rawResponse, 'data.activity.mainContactFields', []);
        if (is_array($mainContactFields)) {
            foreach ($mainContactFields as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }
        }

        $requiredCustomerFields = data_get($dto->rawResponse, 'data.activity.requiredCustomerFields', []);
        if (is_array($requiredCustomerFields)) {
            foreach ($requiredCustomerFields as $fieldName) {
                if (!is_string($fieldName) || trim($fieldName) === '') {
                    continue;
                }
                $rows[] = [
                    'field' => $fieldName,
                    'required' => true,
                ];
            }
        }

        return $rows;
    }

    private function makeUniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'product-' . Str::lower(Str::random(8));
        }

        $slug = $base;
        $counter = 1;

        while (Product::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }
}
