<?php

namespace App\Http\Controllers\API\AvailabilityCheck;

use App\Http\Controllers\Controller;
use App\Models\AvailabilityCategoryPrice;
use App\Models\AvailabilityCheck;
use App\Models\Product;
use App\Services\GlobalTix\GlobalTixPriceSyncService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\Provider\ProviderFactory;

class AvailabilityCheckController extends Controller
{
    use ApiResponse, \App\Traits\ConvertsCurrency;

    public function checkAvailability(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'activity_api_id' => 'sometimes|string',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'sometimes|date_format:Y-m-d|after_or_equal:start_date',
            'currency' => 'sometimes|string|max:10',
            'force_refresh' => 'sometimes|boolean',
        ]);

        $product = Product::with(['options.pricingCategories'])->find((int) $data['product_id']);
        if (!$product) {
            return $this->error('Product not found', 404);
        }

        $provider = strtolower((string) ($product->provider ?: $product->platform ?: 'bokun'));
        $startDate = $data['start_date'];
        $endDate = $data['end_date'] ?? Carbon::parse($startDate)->addDays(6)->format('Y-m-d');

        // Priority: X-Currency header (via CurrencyMiddleware) → ?currency= param → product default → EUR
        // For Bokun: Bokun returns prices already in this currency, so we MUST send the correct one.
        $currency = strtoupper((string) (
            app()->bound('user.currency')
                ? app('user.currency')
                : ($data['currency'] ?? $product->default_currency ?? 'EUR')
        ));
        $forceRefresh = (bool) ($data['force_refresh'] ?? false);
        $localSlots = $provider === 'supplier'
            ? $this->loadSupplierAvailabilityFromDatabase($product, $startDate, $endDate)
            : $this->loadLocalAvailabilityFromDatabase($product, $startDate, $endDate);

        if ($provider === 'supplier' || (!$forceRefresh && $localSlots->isNotEmpty())) {
            return $this->success('Availability loaded from local database successfully.', [
                'product_id' => $product->id,
                'provider'   => $provider,
                'currency'   => $currency,
                'dates'      => $this->buildAvailabilityResponse($localSlots, $provider),
            ]);
        }

        $activityApiId = $data['activity_api_id'] ?? $product->product_api_id;
        if (empty($activityApiId)) {
            throw ValidationException::withMessages([
                'activity_api_id' => 'activity_api_id is required (either in request or product record).',
            ]);
        }

        $providerService = ProviderFactory::make($provider);
        $result = $providerService->checkAvailability((string) $activityApiId, $startDate, $endDate, $currency);

        $providerRows = $result['availabilities'] ?? [];
        $normalizedSlots = is_array($providerRows) && $providerRows !== []
            ? $this->normalizeProviderAvailability($provider, $providerRows, $product)
            : [];
        $savedSlots = $this->persistNormalizedAvailability($product, $provider, $normalizedSlots, $startDate, $endDate);

        if ($provider === 'globaltix' && !empty($normalizedSlots)) {
            app(GlobalTixPriceSyncService::class)->syncFromAvailabilitySlots($product, $normalizedSlots);
        }

        return $this->success($savedSlots->isEmpty() ? 'No availability found for the selected date range.' : 'Availability fetched and synced successfully.', [
            'product_id' => $product->id,
            'provider'   => $provider,
            'currency'   => $currency,
            'dates'      => $this->buildAvailabilityResponse($savedSlots, $provider),
        ]);
    }

    /**
     * @param array<int, mixed> $providerRows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeProviderAvailability(string $provider, array $providerRows, Product $product): array
    {
        return match ($provider) {
            'globaltix' => $this->normalizeGlobalTixAvailability($providerRows, $product),
            default => $this->normalizeBokunAvailability($providerRows, $product),
        };
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeBokunAvailability(array $rows, Product $product): array
    {
        $slots = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $date = $this->normalizeDate($row['date'] ?? null);
            if ($date === null) {
                continue;
            }

            $startTime = $this->normalizeTime($row['start_time'] ?? $row['startTime'] ?? null, '00:00:00');
            $endTime = $this->normalizeTime($row['end_time'] ?? $row['endTime'] ?? null);
            $availabilityCount = (int) ($row['availability_count'] ?? $row['availabilityCount'] ?? 0);
            $unlimited = (bool) ($row['unlimited_availability'] ?? $row['unlimitedAvailability'] ?? false);
            $soldOut = (bool) ($row['sold_out'] ?? $row['soldOut'] ?? false);

            $rates = $row['prices_by_rate'] ?? $row['pricesByRate'] ?? [];
            if (!is_array($rates) || empty($rates)) {
                $rates = [null];
            }

            foreach ($rates as $rate) {
                $optionExternalId = null;
                $categories = [];

                if (is_array($rate)) {
                    $optionExternalId = isset($rate['rateId'])
                        ? (string) $rate['rateId']
                        : (isset($rate['rate_id'])
                            ? (string) $rate['rate_id']
                            : (isset($rate['activityRateId']) ? (string) $rate['activityRateId'] : null));
                    $categories = $this->extractBokunRateCategories($rate, $availabilityCount, $product);
                }

                if (empty($categories)) {
                    $fallbackPrice = is_numeric($product->from_price) ? (float) $product->from_price : 0.0;
                    $categories[] = [
                        'category_external_id' => 'default',
                        'code' => 'ADULT',
                        'label' => 'Adult',
                        'selling_price' => $fallbackPrice,
                        'nett_price' => null,
                        'original_price' => null,
                        'recommended_price' => null,
                        'merchant_price' => null,
                        'tax_amount' => null,
                        'currency' => $product->default_currency ?? 'EUR',
                        'available_qty' => $availabilityCount,
                        'min_qty' => null,
                        'max_qty' => null,
                        'metadata' => [],
                    ];
                }

                $displayPrice = $this->resolveDisplayPrice($categories);

                $currency = (string) (collect($categories)->pluck('currency')->filter()->first() ?? ($row['currency'] ?? $product->default_currency ?? 'EUR'));
                $slotKey = implode('|', [$date, $startTime ?? '00:00:00', $optionExternalId ?? 'default']);

                if (!isset($slots[$slotKey])) {
                    $slots[$slotKey] = [
                        'external_id' => isset($row['availability_id']) ? (string) $row['availability_id'] : (isset($row['id']) ? (string) $row['id'] : null),
                        'option_external_id' => $optionExternalId,
                        'available_date' => $date,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'time_zone' => $row['timezone'] ?? $row['timeZone'] ?? $product->time_zone,
                        'capacity_total' => $availabilityCount,
                        'capacity_booked' => 0,
                        'capacity_reserved' => 0,
                        'unlimited_availability' => $unlimited,
                        'sold_out' => $soldOut,
                        'min_participants' => (int) ($row['min_participants'] ?? $row['minParticipants'] ?? 1),
                        'max_participants' => isset($row['max_participants']) ? (int) $row['max_participants'] : (isset($row['maxParticipants']) ? (int) $row['maxParticipants'] : null),
                        'display_price' => $displayPrice,
                        'currency' => $currency,
                        'series_id' => $row['series_id'] ?? $row['seriesId'] ?? null,
                        'series_name' => $row['series_name'] ?? $row['seriesName'] ?? null,
                        'is_active' => ! $soldOut,
                        'is_closed' => false,
                        'raw_data' => $row,
                        'categories' => [],
                    ];
                }

                foreach ($categories as $category) {
                    $existing = collect($slots[$slotKey]['categories'])
                        ->first(fn (array $saved) => ($saved['category_external_id'] ?? null) === ($category['category_external_id'] ?? null)
                            && ($saved['code'] ?? null) === ($category['code'] ?? null)
                            && ($saved['min_qty'] ?? null) === ($category['min_qty'] ?? null)
                            && ($saved['max_qty'] ?? null) === ($category['max_qty'] ?? null));

                    if ($existing === null) {
                        $slots[$slotKey]['categories'][] = $category;
                    }
                }
            }
        }

        return array_values($slots);
    }

    /**
     * @param array<string, mixed> $rate
     * @return array<int, array<string, mixed>>
     */
    private function extractBokunRateCategories(array $rate, int $availableQty, Product $product): array
    {
        $categories = [];
        $units = $rate['pricePerCategoryUnit'] ?? $rate['pricesByCategory'] ?? [];
        if (!is_array($units)) {
            return $categories;
        }

        foreach ($units as $unit) {
            if (!is_array($unit)) {
                continue;
            }

            $amountNode = $unit['amount'] ?? [];
            $amount = is_array($amountNode)
                ? ($amountNode['amount'] ?? $amountNode['value'] ?? null)
                : $amountNode;

            if (!is_numeric($amount)) {
                continue;
            }

            $currency = is_array($amountNode)
                ? ($amountNode['currency'] ?? null)
                : null;

            $categoryExternalId = $unit['pricingCategoryId']
                ?? $unit['id']
                ?? data_get($unit, 'pricingCategory.id')
                ?? $unit['ticketTypeId']
                ?? $unit['category_id']
                ?? null;
            $pricingCategory = $categoryExternalId !== null
                ? $product->options
                    ->flatMap(fn ($option) => $option->pricingCategories)
                    ->first(fn ($category) => (string) $category->external_id === (string) $categoryExternalId)
                : null;
            $label = $unit['title']
                ?? data_get($unit, 'pricingCategory.title')
                ?? data_get($unit, 'category.title')
                ?? $pricingCategory?->category_label
                ?? 'Category';
            $code = strtoupper((string) ($unit['ticketCategory']
                ?? data_get($unit, 'pricingCategory.ticketCategory')
                ?? data_get($unit, 'category.code')
                ?? $pricingCategory?->category_code
                ?? Str::slug((string) $label, '_')));

            $categories[] = [
                'category_external_id' => $categoryExternalId !== null ? (string) $categoryExternalId : null,
                'code' => $code,
                'label' => (string) $label,
                'selling_price' => (float) $amount,
                'nett_price' => is_numeric($unit['nettPrice'] ?? null) ? (float) $unit['nettPrice'] : null,
                'original_price' => is_numeric($unit['originalPrice'] ?? null) ? (float) $unit['originalPrice'] : null,
                'recommended_price' => is_numeric($unit['recommendedSellingPrice'] ?? null) ? (float) $unit['recommendedSellingPrice'] : null,
                'merchant_price' => is_numeric($unit['recommendedMerchantSellingPrice'] ?? null) ? (float) $unit['recommendedMerchantSellingPrice'] : null,
                'tax_amount' => is_numeric($unit['tax'] ?? null) ? (float) $unit['tax'] : null,
                'currency' => (string) ($currency ?? $product->default_currency ?? 'EUR'),
                'available_qty' => $availableQty,
                'min_qty' => isset($unit['minPurchaseQty']) ? (int) $unit['minPurchaseQty'] : (isset($unit['minParticipantsRequired']) ? (int) $unit['minParticipantsRequired'] : null),
                'max_qty' => isset($unit['maxPurchaseQty']) ? (int) $unit['maxPurchaseQty'] : (isset($unit['maxParticipantsRequired']) ? (int) $unit['maxParticipantsRequired'] : null),
                'metadata' => $unit,
            ];
        }

        return $categories;
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeGlobalTixAvailability(array $rows, Product $product): array
    {
        $slots = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $date = $this->normalizeDate($row['date'] ?? $row['time'] ?? null);
            if ($date === null) {
                continue;
            }

            $startTime = $this->normalizeTime($row['start_time'] ?? $row['time'] ?? null, '00:00:00');
            $endTime = $this->normalizeTime($row['end_time'] ?? null);
            $optionExternalId = isset($row['option_id']) ? (string) $row['option_id'] : (isset($row['optionID']) ? (string) $row['optionID'] : null);
            $availableQty = (int) ($row['available'] ?? 0);
            $capacityTotal = (int) ($row['total'] ?? $availableQty);
            $soldOut = !((bool) ($row['isAvailable'] ?? true)) || $availableQty <= 0;

            $selling = $row['selling_price']
                ?? $row['recommendedSellingPrice']
                ?? data_get($row, 'price.amount')
                ?? 0;
            $nett = $row['nett_price'] ?? $row['nettPrice'] ?? null;
            $currency = (string) (data_get($row, 'price.currency') ?? $row['currency'] ?? $product->default_currency ?? 'EUR');

            $label = (string) ($row['ticket_type_name'] ?? $row['ticket_name'] ?? 'Ticket');
            $categoryCode = $this->inferCategoryCode($label);
            $categoryExternalId = isset($row['ticket_type_id'])
                ? (string) $row['ticket_type_id']
                : (isset($row['ticket_id']) ? (string) $row['ticket_id'] : null);

            $slotKey = implode('|', [$date, $startTime ?? '00:00:00', $optionExternalId ?? 'default']);
            if (!isset($slots[$slotKey])) {
                $slots[$slotKey] = [
                    'external_id' => isset($row['id']) ? (string) $row['id'] : null,
                    'option_external_id' => $optionExternalId,
                    'available_date' => $date,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'time_zone' => $product->time_zone,
                    'capacity_total' => $capacityTotal,
                    'capacity_booked' => max(0, $capacityTotal - $availableQty),
                    'capacity_reserved' => 0,
                    'unlimited_availability' => (bool) ($row['unlimited'] ?? false),
                    'sold_out' => $soldOut,
                    'min_participants' => 1,
                    'max_participants' => null,
                    'display_price' => is_numeric($selling) ? (float) $selling : null,
                    'currency' => $currency,
                    'series_id' => $row['seriesId'] ?? null,
                    'series_name' => $row['seriesName'] ?? null,
                    'is_active' => !(bool) ($row['isInactive'] ?? false),
                    'is_closed' => $soldOut,
                    'raw_data' => $row,
                    'categories' => [],
                ];
            }

            $slots[$slotKey]['categories'][] = [
                'category_external_id' => $categoryExternalId,
                'code' => $categoryCode,
                'label' => $label,
                'selling_price' => is_numeric($selling) ? (float) $selling : 0.0,
                'nett_price' => is_numeric($nett) ? (float) $nett : null,
                'original_price' => is_numeric($row['originalPrice'] ?? null) ? (float) $row['originalPrice'] : null,
                'recommended_price' => is_numeric($row['recommendedSellingPrice'] ?? null) ? (float) $row['recommendedSellingPrice'] : null,
                'merchant_price' => is_numeric($row['recommendedMerchantSellingPrice'] ?? null) ? (float) $row['recommendedMerchantSellingPrice'] : null,
                'tax_amount' => null,
                'currency' => $currency,
                'available_qty' => $availableQty,
                'min_qty' => isset($row['minPurchaseQty']) ? (int) $row['minPurchaseQty'] : null,
                'max_qty' => isset($row['maxPurchaseQty']) ? (int) $row['maxPurchaseQty'] : null,
                'metadata' => $row,
            ];
        }

        return array_values($slots);
    }

    /**
     * @param array<int, array<string, mixed>> $slots
     * @return \Illuminate\Support\Collection<int, AvailabilityCheck>
     */
    private function persistNormalizedAvailability(
        Product $product,
        string $provider,
        array $slots,
        string $startDate,
        string $endDate
    ): Collection
    {
        $optionRows = $product->options;
        $optionMap = $optionRows
            ->filter(fn ($option) => !empty($option->external_id))
            ->keyBy(fn ($option) => (string) $option->external_id);

        $categoryByOptionExternal = [];
        $categoryByExternal = [];

        foreach ($optionRows as $option) {
            foreach ($option->pricingCategories as $category) {
                if (!empty($category->external_id)) {
                    $categoryByOptionExternal[(string) $option->external_id][(string) $category->external_id] = $category;
                    $categoryByExternal[(string) $category->external_id] = $category;
                }
            }
        }

        $saved = collect();

        DB::transaction(function () use ($slots, $provider, $product, $optionMap, $categoryByOptionExternal, $categoryByExternal, $startDate, $endDate, &$saved) {
            $upsertRows = [];
            $persistedKeys = [];
            $categoryPriceBatches = [];
            $now = now();

            foreach ($slots as $slot) {
                $optionExternal = isset($slot['option_external_id']) ? (string) $slot['option_external_id'] : null;
                $productOptionId = $optionExternal && isset($optionMap[$optionExternal])
                    ? $optionMap[$optionExternal]->id
                    : null;

                $startTime = $slot['start_time'] ?? '00:00:00';
                $idempotencyKey = md5(implode('|', [
                    $product->id,
                    $provider,
                    $optionExternal ?? 'default',
                    $slot['available_date'],
                    $startTime,
                    $slot['external_id'] ?? '',
                ]));
                $persistedKeys[] = $idempotencyKey;

                $upsertRows[] = [
                    'idempotency_key' => $idempotencyKey,
                    'product_id' => $product->id,
                    'product_option_id' => $productOptionId,
                    'provider' => $provider,
                    'external_id' => $slot['external_id'] ?? null,
                    'available_date' => $slot['available_date'],
                    'start_time' => $startTime,
                    'end_time' => $slot['end_time'] ?? null,
                    'start_at' => $slot['available_date'] . ' ' . $startTime,
                    'time_zone' => $slot['time_zone'] ?? $product->time_zone,
                    'capacity_total' => (int) ($slot['capacity_total'] ?? 0),
                    'capacity_booked' => (int) ($slot['capacity_booked'] ?? 0),
                    'capacity_reserved' => (int) ($slot['capacity_reserved'] ?? 0),
                    'unlimited_availability' => (bool) ($slot['unlimited_availability'] ?? false),
                    'sold_out' => (bool) ($slot['sold_out'] ?? false),
                    'min_participants' => (int) ($slot['min_participants'] ?? 1),
                    'max_participants' => isset($slot['max_participants']) ? (int) $slot['max_participants'] : null,
                    'display_price' => isset($slot['display_price']) && is_numeric($slot['display_price']) ? (float) $slot['display_price'] : null,
                    'currency' => $slot['currency'] ?? $product->default_currency,
                    'is_active' => (bool) ($slot['is_active'] ?? true),
                    'is_closed' => (bool) ($slot['is_closed'] ?? false),
                    'series_id' => $slot['series_id'] ?? null,
                    'series_name' => $slot['series_name'] ?? null,
                    'raw_data' => isset($slot['raw_data']) ? json_encode($slot['raw_data']) : null,
                ];

                // Collect fresh availability IDs for category prices after upsert
                $categoryPriceBatches[$idempotencyKey] = [
                    'option_external' => $optionExternal,
                    'categories' => $slot['categories'] ?? [],
                    'currency' => $slot['currency'] ?? $product->default_currency ?? 'EUR',
                ];
            }

            // Batch upsert all availability rows at once
            if (!empty($upsertRows)) {
                AvailabilityCheck::upsert($upsertRows, ['idempotency_key'], [
                    'product_id', 'product_option_id', 'provider',
                    'external_id', 'available_date', 'start_time', 'end_time',
                    'start_at', 'time_zone',
                    'capacity_total', 'capacity_booked', 'capacity_reserved',
                    'unlimited_availability', 'sold_out',
                    'min_participants', 'max_participants',
                    'display_price', 'currency',
                    'is_active', 'is_closed',
                    'series_id', 'series_name', 'raw_data',
                ]);
            }

            // Re-fetch the saved slot IDs for category price batch insert (only need id + key)
            $savedSlots = AvailabilityCheck::whereIn('idempotency_key', $persistedKeys)
                ->where('product_id', $product->id)
                ->select(['id', 'idempotency_key'])
                ->get()
                ->keyBy('idempotency_key');

            // Build category price batch inserts grouped by availability
            $acpBatch = [];
            foreach ($categoryPriceBatches as $idempotencyKey => $batchInfo) {
                $availability = $savedSlots->get($idempotencyKey);
                if (!$availability) {
                    continue;
                }

                // Delete existing category prices for this slot
                AvailabilityCategoryPrice::where('availability_id', $availability->id)->delete();

                $optionExternal = $batchInfo['option_external'];
                $defaultCurrency = $batchInfo['currency'];

                foreach ($batchInfo['categories'] as $category) {
                    if (!is_array($category)) {
                        continue;
                    }

                    $categoryExternal = isset($category['category_external_id']) ? (string) $category['category_external_id'] : null;
                    $pricingCategory = null;

                    if ($optionExternal && $categoryExternal && isset($categoryByOptionExternal[$optionExternal][$categoryExternal])) {
                        $pricingCategory = $categoryByOptionExternal[$optionExternal][$categoryExternal];
                    } elseif ($categoryExternal && isset($categoryByExternal[$categoryExternal])) {
                        $pricingCategory = $categoryByExternal[$categoryExternal];
                    }

                    $acpBatch[] = [
                        'availability_id' => $availability->id,
                        'pricing_category_id' => $pricingCategory?->id,
                        'category_external_id' => $categoryExternal,
                        'category_code' => strtoupper((string) ($category['code'] ?? 'GENERAL')),
                        'category_label' => (string) ($category['label'] ?? $category['code'] ?? 'General'),
                        'selling_price' => isset($category['selling_price']) && is_numeric($category['selling_price']) ? (float) $category['selling_price'] : 0,
                        'nett_price' => isset($category['nett_price']) && is_numeric($category['nett_price']) ? (float) $category['nett_price'] : null,
                        'original_price' => isset($category['original_price']) && is_numeric($category['original_price']) ? (float) $category['original_price'] : null,
                        'recommended_price' => isset($category['recommended_price']) && is_numeric($category['recommended_price']) ? (float) $category['recommended_price'] : null,
                        'merchant_price' => isset($category['merchant_price']) && is_numeric($category['merchant_price']) ? (float) $category['merchant_price'] : null,
                        'tax_amount' => isset($category['tax_amount']) && is_numeric($category['tax_amount']) ? (float) $category['tax_amount'] : null,
                        'currency' => (string) ($category['currency'] ?? $defaultCurrency),
                        'available_qty' => isset($category['available_qty']) ? (int) $category['available_qty'] : null,
                        'min_qty' => isset($category['min_qty']) ? (int) $category['min_qty'] : null,
                        'max_qty' => isset($category['max_qty']) ? (int) $category['max_qty'] : null,
                        'metadata' => isset($category['metadata']) && is_array($category['metadata']) ? json_encode($category['metadata']) : null,
                    ];
                }

                $saved->push($availability->fresh('categoryPrices'));
            }

            // Batch insert all category prices
            if (!empty($acpBatch)) {
                AvailabilityCategoryPrice::insert($acpBatch);
            }

            // Delete stale rows (rows in date range not covered by persisted keys)
            $staleQuery = AvailabilityCheck::query()
                ->where('product_id', $product->id)
                ->where('provider', $provider)
                ->whereDate('available_date', '>=', $startDate)
                ->whereDate('available_date', '<=', $endDate);

            if ($persistedKeys !== []) {
                $staleQuery->whereNotIn('idempotency_key', $persistedKeys);
            }

            $staleQuery->delete();
        });

        return $saved;
    }

    /**
     * @return \Illuminate\Support\Collection<int, AvailabilityCheck>
     */
    private function loadLocalAvailabilityFromDatabase(Product $product, string $startDate, string $endDate): Collection
    {
        return AvailabilityCheck::with(['categoryPrices', 'productOption.pricingCategories'])
            ->where('product_id', $product->id)
            ->whereDate('available_date', '>=', $startDate)
            ->whereDate('available_date', '<=', $endDate)
            ->orderBy('available_date')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();
    }

    /**
     * Supplier availability is already normalized in local tables, so this
     * branch never calls external providers.
     *
     * @return \Illuminate\Support\Collection<int, AvailabilityCheck>
     */
    private function loadSupplierAvailabilityFromDatabase(Product $product, string $startDate, string $endDate): Collection
    {
        $slots = $this->loadLocalAvailabilityFromDatabase($product, $startDate, $endDate)
            ->where('provider', 'supplier')
            ->values();

        return $slots->map(function (AvailabilityCheck $slot) use ($product) {
            $option = $slot->productOption
                ?? $product->options->firstWhere('id', $slot->product_option_id)
                ?? $product->options->firstWhere('external_id', 'default')
                ?? $product->options->first();

            if ($option === null) {
                return $slot;
            }

            $baseCategories = $option->pricingCategories
                ->sortBy('sort_order')
                ->values();

            if ($baseCategories->isEmpty()) {
                return $slot;
            }

            $existingRows = $slot->categoryPrices->values();
            $baseExternalIds = $baseCategories
                ->pluck('external_id')
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->map(fn ($value) => (string) $value)
                ->values();
            $existingExternalIds = $existingRows
                ->pluck('category_external_id')
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->map(fn ($value) => (string) $value)
                ->values();

            $shouldReplaceGenericRows = $baseCategories->count() > 1 && (
                $existingRows->isEmpty()
                || $existingRows->count() === 1
                || $baseExternalIds->diff($existingExternalIds)->isNotEmpty()
            );

            if (! $shouldReplaceGenericRows && $existingRows->isNotEmpty()) {
                return $slot;
            }

            $remainingCapacity = $slot->unlimited_availability
                ? null
                : max(0, $slot->capacity_total - $slot->capacity_booked - $slot->capacity_reserved);

            $fallbackRows = $baseCategories->map(function ($category) use ($slot, $remainingCapacity, $product) {
                return new AvailabilityCategoryPrice([
                    'availability_id' => $slot->id,
                    'pricing_category_id' => $category->id,
                    'category_external_id' => $category->external_id,
                    'category_code' => $category->category_code,
                    'category_label' => $category->category_label,
                    'selling_price' => $category->selling_price ?? $slot->display_price ?? 0,
                    'nett_price' => $category->nett_price,
                    'original_price' => $category->original_price,
                    'recommended_price' => $category->recommended_price,
                    'merchant_price' => $category->merchant_price,
                    'tax_amount' => null,
                    'currency' => $category->currency ?? $slot->currency ?? $product->default_currency ?? 'EUR',
                    'available_qty' => $remainingCapacity,
                    'min_qty' => $category->min_purchase_qty,
                    'max_qty' => $category->max_purchase_qty,
                    'metadata' => [
                        'source' => 'supplier_option_pricing_category_fallback',
                    ],
                ]);
            });

            $slot->setRelation('categoryPrices', $fallbackRows);
            $slot->display_price = $this->resolveDisplayPrice(
                $fallbackRows
                    ->map(fn (AvailabilityCategoryPrice $row) => ['selling_price' => $row->selling_price])
                    ->all()
            );

            return $slot;
        });
    }

    /**
     * Build the final availability response array from persisted slots.
     *
     * @param  \Illuminate\Support\Collection<int, AvailabilityCheck>  $slots
     * @param  string  $provider  'bokun' | 'globaltix' | 'supplier' | 'manual'
     * @return array<int, array<string, mixed>>
     */
    private function buildAvailabilityResponse(Collection $slots, string $provider = 'bokun'): array
    {
        $requestedCurrency = app()->bound('user.currency') ? app('user.currency') : null;

        // Both Bokun and GlobalTix return prices directly from their official APIs in the
        // product's native currency — no local exchange-rate conversion should be applied.
        // Bokun: prices already in user's requested currency (Bokun converts server-side).
        // GlobalTix: prices always in EUR as returned by the official API.
        // Manual/supplier: formatPrice() applies local Currency table rates.
        $useRawPrice = in_array($provider, ['bokun', 'globaltix']);

        return $slots
            ->sortBy(fn (AvailabilityCheck $slot) => (string) $slot->available_date . ' ' . (string) $slot->start_time)
            ->groupBy(fn (AvailabilityCheck $slot) => $slot->available_date?->format('Y-m-d'))
            ->map(function (Collection $dateSlots, string $date) use ($requestedCurrency, $useRawPrice) {
                return [
                    'date'       => $date,
                    'time_slots' => $dateSlots->map(function (AvailabilityCheck $slot) use ($requestedCurrency, $useRawPrice) {
                        $available = $slot->unlimited_availability
                            ? null
                            : max(0, $slot->capacity_total - $slot->capacity_booked - $slot->capacity_reserved);

                        $originalCurrency = $slot->currency ?? 'EUR';
                        // rawPrice providers: slot currency IS the authoritative currency.
                        // Others: may convert to user's requested currency.
                        $targetCurrency = $useRawPrice
                            ? $originalCurrency
                            : ($requestedCurrency ?? $originalCurrency);

                        return [
                            'start_time'    => $slot->start_time,
                            'end_time'      => $slot->end_time,
                            'available'     => $available,
                            'sold_out'      => (bool) $slot->sold_out,
                            // rawPrice providers: no conversion. Others: apply local rate.
                            'display_price' => $useRawPrice
                                ? $this->rawPrice($slot->display_price)
                                : $this->formatPrice($slot->display_price, $originalCurrency),
                            'currency'      => $targetCurrency,
                            'categories'    => $slot->categoryPrices->map(function (AvailabilityCategoryPrice $category) use ($available, $targetCurrency, $originalCurrency, $useRawPrice) {
                                $catCurrency = $category->currency ?? $originalCurrency;
                                return [
                                    'code'                 => $category->category_code,
                                    'label'                => $category->category_label,
                                    'category_external_id' => $category->category_external_id,
                                    // rawPrice providers: no conversion. Others: apply local rate.
                                    'selling_price'        => $useRawPrice
                                        ? $this->rawPrice($category->selling_price)
                                        : (float) $this->formatPrice($category->selling_price, $catCurrency),
                                    'nett_price'           => $category->nett_price !== null
                                        ? ($useRawPrice
                                            ? $this->rawPrice($category->nett_price)
                                            : (float) $this->formatPrice($category->nett_price, $catCurrency))
                                        : null,
                                    'original_price'       => $category->original_price !== null
                                        ? ($useRawPrice
                                            ? $this->rawPrice($category->original_price)
                                            : (float) $this->formatPrice($category->original_price, $catCurrency))
                                        : null,
                                    'recommended_price'    => $category->recommended_price !== null
                                        ? ($useRawPrice
                                            ? $this->rawPrice($category->recommended_price)
                                            : (float) $this->formatPrice($category->recommended_price, $catCurrency))
                                        : null,
                                    'merchant_price'       => $category->merchant_price !== null
                                        ? ($useRawPrice
                                            ? $this->rawPrice($category->merchant_price)
                                            : (float) $this->formatPrice($category->merchant_price, $catCurrency))
                                        : null,
                                    'available'            => $category->available_qty ?? $available,
                                    'min_qty'              => $category->min_qty,
                                    'max_qty'              => $category->max_qty,
                                    'currency'             => $targetCurrency,
                                ];
                            })->values()->all(),
                        ];
                    })->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return Carbon::createFromTimestampMs((int) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeTime(mixed $value, ?string $default = null): ?string
    {
        if ($value === null || $value === '') {
            return $default;
        }

        try {
            return Carbon::parse((string) $value)->format('H:i:s');
        } catch (\Throwable) {
            return $default;
        }
    }

    private function inferCategoryCode(string $label): string
    {
        $normalized = strtoupper($label);

        if (str_contains($normalized, 'ADULT')) return 'ADULT';
        if (str_contains($normalized, 'CHILD') || str_contains($normalized, 'KID')) return 'CHILD';
        if (str_contains($normalized, 'INFANT') || str_contains($normalized, 'BABY')) return 'INFANT';
        if (str_contains($normalized, 'YOUTH') || str_contains($normalized, 'TEEN')) return 'YOUTH';
        if (str_contains($normalized, 'SENIOR')) return 'SENIOR';
        if (str_contains($normalized, 'FAMILY')) return 'FAMILY';
        if (str_contains($normalized, 'GROUP')) return 'GROUP';

        $slug = strtoupper(Str::slug($label, '_'));
        return $slug !== '' ? $slug : 'GENERAL';
    }

    /**
     * Prefer the ADULT lowest price (falling back to lowest paid category) for calendar/card display.
     */
    private function resolveDisplayPrice(array $categories): ?float
    {
        $resolved = app(\App\Services\GlobalTix\GlobalTixPriceSyncService::class)->resolveFromPrice($categories);
        if ($resolved['from_price'] !== null && $resolved['from_price'] > 0) {
            return (float) $resolved['from_price'];
        }

        $prices = collect($categories)
            ->pluck('selling_price')
            ->filter(fn ($price) => is_numeric($price))
            ->map(fn ($price) => (float) $price);

        return $prices
            ->filter(fn (float $price) => $price > 0)
            ->sort()
            ->first()
            ?? $prices->sort()->first();
    }
}
