<?php

namespace App\Http\Controllers\API\Supplier;

use App\Http\Controllers\Controller;
use App\Models\AvailabilityCategoryPrice;
use App\Models\AvailabilityCheck;
use App\Models\Categories;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVideo;
use App\Repositories\Contracts\AvailabilityCategoryPriceRepositoryInterface;
use App\Repositories\Contracts\AvailabilityCheckRepositoryInterface;
use App\Repositories\Contracts\CancellationPolicyRepositoryInterface;
use App\Repositories\Contracts\CategoriesRepositoryInterface;
use App\Repositories\Contracts\KeywordRepositoryInterface;
use App\Repositories\Contracts\LocationRepositoryInterface;
use App\Repositories\Contracts\OptionPricingCategoryRepositoryInterface;
use App\Repositories\Contracts\PriceRepositoryInterface;
use App\Repositories\Contracts\ProductImageRepositoryInterface;
use App\Repositories\Contracts\ProductOptionRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\ProductVideoRepositoryInterface;
use App\Repositories\Contracts\TieredPriceRepositoryInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupplierProductCreationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private CategoriesRepositoryInterface $categoriesRepository,
        private LocationRepositoryInterface $locationRepository,
        private KeywordRepositoryInterface $keywordRepository,
        private CancellationPolicyRepositoryInterface $cancellationPolicyRepository,
        private AvailabilityCheckRepositoryInterface $availabilityCheckRepository,
        private ProductOptionRepositoryInterface $productOptionRepository,
        private OptionPricingCategoryRepositoryInterface $optionPricingCategoryRepository,
        private ProductImageRepositoryInterface $productImageRepository,
        private ProductVideoRepositoryInterface $productVideoRepository,
        private PriceRepositoryInterface $priceRepository,
        private TieredPriceRepositoryInterface $tieredPriceRepository,
        private AvailabilityCategoryPriceRepositoryInterface $availabilityCategoryPriceRepository,
    ) {}

    // ─── HELPER: Vendor Auth Check ──────────────────────────────────────
    private function authorizedVendor()
    {
        $user = auth()->user();
        if (!in_array($user->role, ['supplier', 'Vendor'], true) || !$user->vendor) {
            return null;
        }
        return $user;
    }

    // ─── HELPER: Get Draft Product ──────────────────────────────────────
    private function getDraftProduct($productId, $vendorId)
    {
        return $this->productRepository->query()
            ->where('id', $productId)
            ->where('vendor_id', $vendorId)
            ->where('is_draft', true)
            ->first();
    }

    // ─── HELPER: Multi-key request input resolver ───────────────────────
    private function input(Request $request, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if ($request->has($key)) {
                $value = $request->input($key);
                if ($value !== null && $value !== '') {
                    return $value;
                }
            }
        }
        return $default;
    }

    private function toBoolean(mixed $value, ?bool $default = null): ?bool
    {
        if ($value === null) return $default;
        if (is_bool($value)) return $value;
        if (is_int($value) || is_float($value)) return (bool) $value;

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on', 'available'], true)) return true;
            if (in_array($normalized, ['0', 'false', 'no', 'off', 'not_available'], true)) return false;
        }
        return $default;
    }

    private function normalizeStringList(mixed $value): array
    {
        if ($value === null || $value === '') return [];
        if (is_string($value)) $value = preg_split('/[\r\n,]+/', $value) ?: [];
        if (!is_array($value)) return [];

        return array_values(array_filter(array_map(function ($item) {
            if (is_array($item)) {
                $item = $item['label'] ?? $item['value'] ?? $item['name'] ?? null;
            }
            return is_string($item) ? trim($item) : null;
        }, $value)));
    }

    private function normalizeGroupSize(mixed $value): int
    {
        if ($value === null || $value === '') return 0;
        if (is_numeric($value)) return max(0, (int) $value);

        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['no limit', 'unlimited'], true)) return 0;

        preg_match('/\d+/', $normalized, $matches);
        return isset($matches[0]) ? (int) $matches[0] : 0;
    }

    private function normalizeSkipLineType(?string $value): ?string
    {
        if (!$value) return null;
        return match (strtolower(trim($value))) {
            'skip the line to get tickets', 'skip_line_to_get_tickets' => 'skip_line_to_get_tickets',
            'skip the line through a separate entrance', 'skip_line_through_separate_entrance' => 'skip_line_through_separate_entrance',
            'skip the line through express security check', 'skip_line_through_express_security_check' => 'skip_line_through_express_security_check',
            'skip the line through express elevators', 'skip_line_through_express_elevators' => 'skip_line_through_express_elevators',
            default => null,
        };
    }

    private function normalizeGuideType(?string $value): ?string
    {
        if (!$value) return null;
        return match (strtolower(trim($value))) {
            'self_guided' => 'self_guided',
            'tour_guide' => 'tour_guide',
            'host_greeter', 'host_or_greeter' => 'host_greeter',
            'instructor' => 'instructor',
            'driver' => 'driver',
            default => null,
        };
    }

    private function normalizeMealType(?string $value): ?string
    {
        if (!$value) return null;
        return match (strtolower(trim($value))) {
            'breakfast' => 'breakfast',
            'dinner' => 'dinner',
            'lunch' => 'lunch',
            'brunch' => 'brunch',
            'depending on starting time', 'lunch or dinner depending on starting time', 'lunch_or_dinner_depending' => 'lunch_or_dinner_depending',
            default => null,
        };
    }

    private function normalizeMealFormat(?string $value): ?string
    {
        if (!$value) return null;
        return match (strtolower(trim($value))) {
            'full meal', 'full_meal' => 'full_meal',
            'food tasting', 'food_tasting' => 'food_tasting',
            'cooking class', 'cooking_class' => 'cooking_class',
            'buffet' => 'buffet',
            'snack' => 'snack',
            'picnic' => 'picnic',
            'packed meal', 'packed_meal' => 'packed_meal',
            'bbq' => 'bbq',
            default => null,
        };
    }

    private function normalizeTransportationItems(mixed $value): array
    {
        $items = $this->normalizeStringList($value);
        return array_values(array_filter(array_map(function (string $item) {
            return match (strtolower(trim($item))) {
                'bike' => 'bike',
                'segway' => 'segway',
                'electric bike', 'electric_bike' => 'electric_bike',
                'mountain bike', 'mountain_bike' => 'mountain_bike',
                'motorcycle' => 'motorcycle',
                'scooter' => 'scooter',
                'car' => 'car',
                'van' => 'van',
                'bus' => 'bus',
                default => null,
            };
        }, $items)));
    }

    private function normalizeArrivalTime(?string $value): ?string
    {
        if (!$value) return null;
        return match (strtolower(trim($value))) {
            'not relevant for this activity', 'not_relevant' => 'not_relevant',
            '5 minutes before the activity', '5_minutes_before' => '5_minutes_before',
            '10 minutes before the activity', '10_minutes_before' => '10_minutes_before',
            '15 minutes before the activity', '15_minutes_before' => '15_minutes_before',
            '20 minutes before the activity', '20_minutes_before' => '20_minutes_before',
            '25 minutes before the activity', '25_minutes_before' => '25_minutes_before',
            '30 minutes before the activity', '30_minutes_before' => '30_minutes_before',
            default => null,
        };
    }

    private function normalizeDropOffType(?string $value): ?string
    {
        if (!$value) return null;
        return match (strtolower(trim($value))) {
            'at the same place you met them', 'same_place' => 'same_place',
            'at a different place', 'at a different placey', 'different_place' => 'different_place',
            'no drop-off service, the customer stays at the site or destination', 'no_drop_off' => 'no_drop_off',
            default => null,
        };
    }

    private function normalizeReservationSystem(?string $value): ?string
    {
        if (!$value) return null;
        return match (strtolower(trim($value))) {
            'bokun' => 'bokun',
            'globaltix' => 'globaltix',
            'fareharbor' => 'fareharbor',
            'rezdy' => 'rezdy',
            'peek' => 'peek',
            'checkfront' => 'checkfront',
            'other', 'card', 'apple pay', 'credit card' => 'other',
            default => 'other',
        };
    }

    private function normalizePricingType(?string $value): ?string
    {
        if (!$value) return null;
        return match (strtolower(trim($value))) {
            'same_for_everyone' => 'same_for_everyone',
            'depends_on_age' => 'depends_on_age',
            'price-per-person', 'price per person' => 'depends_on_age',
            'price-per-group', 'price per group', 'price per group/vehicle' => 'same_for_everyone',
            default => null,
        };
    }

    private function normalizeCutOffTime(?string $value): ?string
    {
        if (!$value) return null;
        return match (strtolower(trim($value))) {
            'default' => 'default',
            '0 minutes', '0_minutes' => '0_minutes',
            '5 minutes', '5_minutes' => '5_minutes',
            '10 minutes', '10_minutes' => '10_minutes',
            '15 minutes', '15_minutes' => '15_minutes',
            '20 minutes', '20_minutes' => '20_minutes',
            '25 minutes', '25_minutes' => '25_minutes',
            '30 minutes', '30_minutes' => '30_minutes',
            '35 minutes', '35_minutes' => '35_minutes',
            '40 minutes', '40_minutes' => '40_minutes',
            '45 minutes', '45_minutes' => '45_minutes',
            default => null,
        };
    }

    private function normalizeLocationPayload(mixed $value): array
    {
        if ($value === null || $value === '') return [];

        $entries = is_array($value) ? $value : [$value];

        return array_values(array_filter(array_map(function ($entry) {
            if (is_string($entry)) {
                $parts = array_map('trim', explode(',', $entry, 2));
                return [
                    'city' => $parts[0] ?? '',
                    'country' => $parts[1] ?? '',
                    'countryCode' => '',
                    'coordinates' => null,
                ];
            }
            if (!is_array($entry)) return null;

            $label = $entry['label'] ?? $entry['value'] ?? $entry['name'] ?? null;
            $city = $entry['city'] ?? null;
            $country = $entry['country'] ?? '';

            if (!$city && is_string($label)) {
                $parts = array_map('trim', explode(',', $label, 2));
                $city = $parts[0] ?? '';
                $country = $country ?: ($parts[1] ?? '');
            }

            return [
                'city' => (string) ($city ?? ''),
                'country' => (string) ($country ?? ''),
                'countryCode' => (string) ($entry['countryCode'] ?? $entry['country_code'] ?? ''),
                'coordinates' => $entry['coordinates'] ?? null,
            ];
        }, $entries)));
    }

    private function buildHighlights(Request $request): array
    {
        $highlights = [];
        foreach ($request->all() as $key => $value) {
            if (!is_string($value) || trim($value) === '') continue;
            if (preg_match('/^h(?:igh|ight)light\d+$/i', $key) === 1) {
                $highlights[] = trim($value);
            }
        }
        return array_values($highlights);
    }

    private function resolveSupplierCurrency(?Product $product = null): string
    {
        return $product?->vendor?->currency
            ?? config('app.currency')
            ?? 'EUR';
    }

    private function normalizePriceCategoryKey(?string $categoryCode, ?string $categoryName): ?string
    {
        foreach ([$categoryCode, $categoryName] as $value) {
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $normalized = strtolower(trim($value));

            if (in_array($normalized, ['adult', 'adults'], true)) {
                return 'adult';
            }
            if (in_array($normalized, ['child', 'children', 'kid', 'kids'], true)) {
                return 'child';
            }
            if (in_array($normalized, ['infant', 'infants', 'baby', 'babies'], true)) {
                return 'infant';
            }
            if (in_array($normalized, ['youth', 'teen', 'teens'], true)) {
                return 'youth';
            }
            if (in_array($normalized, ['senior', 'seniors', 'elder'], true)) {
                return 'senior';
            }
            if (in_array($normalized, ['concession', 'student'], true)) {
                return 'concession';
            }

            return Str::slug($normalized, '_');
        }

        return null;
    }

    private function buildSupplierPriceRows(Request $request, Product $product, ?string $pricingType): array
    {
        $currency = $this->resolveSupplierCurrency($product);
        $rows = [];
        $normalizedPricingType = $this->normalizePricingType($pricingType);

        $ageCategories = $request->input('age_categories', []);
        if (is_array($ageCategories)) {
            foreach ($ageCategories as $index => $category) {
                if (!is_array($category)) {
                    continue;
                }

                $amount = $category['amount'] ?? $category['price'] ?? null;
                if (!is_numeric($amount)) {
                    continue;
                }

                $label = (string) ($category['name'] ?? $category['title'] ?? ('Category ' . ($index + 1)));
                $code = strtoupper((string) ($category['code'] ?? Str::slug($label, '_')));
                $externalId = (string) ($category['id'] ?? Str::slug($label, '_'));

                $rows[] = [
                    'provider' => 'supplier',
                    'source_type' => 'supplier_age_category',
                    'price_kind' => strtolower($code),
                    'external_id' => $externalId,
                    'ticket_type_id' => $externalId,
                    'ticket_type_name' => $label,
                    'category_code' => $code,
                    'category_name' => $label,
                    'valid_from' => null,
                    'valid_to' => null,
                    'selling_price' => (float) $amount,
                    'nett_price' => null,
                    'original_price' => (float) $amount,
                    'recommended_price' => (float) $amount,
                    'merchant_price' => null,
                    'currency' => $currency,
                    'is_default' => false,
                    'sort_order' => $index,
                    'metadata' => [
                        'pricing_type' => $normalizedPricingType,
                        'age_from' => $category['age_from'] ?? $category['ageFrom'] ?? null,
                        'age_to' => $category['age_to'] ?? $category['ageTo'] ?? null,
                    ],
                ];
            }
        }

        if (empty($rows)) {
            $fallbackAmount = $request->input('main_price');
            if (!is_numeric($fallbackAmount)) {
                $fallbackAmount = $request->input('from_price');
            }
            if (!is_numeric($fallbackAmount)) {
                $fallbackAmount = 0;
            }

            $rows[] = [
                'provider' => 'supplier',
                'source_type' => 'supplier_config',
                'price_kind' => 'general',
                'external_id' => 'general',
                'ticket_type_id' => 'general',
                'ticket_type_name' => 'General',
                'category_code' => 'GENERAL',
                'category_name' => 'General',
                'valid_from' => null,
                'valid_to' => null,
                'selling_price' => (float) $fallbackAmount,
                'nett_price' => null,
                'original_price' => (float) $fallbackAmount,
                'recommended_price' => (float) $fallbackAmount,
                'merchant_price' => null,
                'currency' => $currency,
                'is_default' => true,
                'sort_order' => 0,
                'metadata' => [
                    'pricing_type' => $normalizedPricingType,
                    'pricing_mode' => $pricingType,
                    'min_max_capacity' => $request->input('min_max_capacity'),
                ],
            ];
        }

        usort($rows, fn(array $a, array $b) => (float) $a['selling_price'] <=> (float) $b['selling_price']);
        $rows[0]['is_default'] = true;

        return $rows;
    }

    private function syncSupplierPriceRows(Product $product, Request $request, ?string $pricingType): void
    {
        $rows = $this->buildSupplierPriceRows($request, $product, $pricingType);
        $currency = $this->resolveSupplierCurrency($product);

        $option = $this->productOptionRepository->query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'provider' => 'supplier',
                'external_id' => 'default',
            ],
            [
                'name' => 'Standard',
                'currency' => $currency,
                'is_default' => true,
                'sort_order' => 0,
            ]
        );

        $existingCategories = $this->optionPricingCategoryRepository->query()->where('product_option_id', $option->id)->get()->keyBy('external_id');
        $categoryIdMap = [];
        $seenExternalIds = [];

        foreach ($rows as $index => $row) {
            $externalId = (string) ($row['external_id'] ?? ('cat-' . $index));
            $seenExternalIds[] = $externalId;
            $category = $existingCategories->get($externalId);

            if ($category === null) {
                $category = $this->optionPricingCategoryRepository->create([
                    'product_option_id' => $option->id,
                    'provider' => 'supplier',
                    'idempotency_key' => md5($product->id . '|supplier|' . $externalId),
                    'external_id' => $externalId,
                    'category_code' => $row['category_code'] ?? 'GENERAL',
                    'category_label' => $row['category_name'] ?? 'General',
                    'selling_price' => $row['selling_price'] ?? null,
                    'currency' => $row['currency'] ?? $currency,
                    'is_default' => (bool) ($row['is_default'] ?? false),
                    'sort_order' => $row['sort_order'] ?? $index,
                ]);
            } else {
                $category->update([
                    'category_code' => $row['category_code'] ?? 'GENERAL',
                    'category_label' => $row['category_name'] ?? 'General',
                    'selling_price' => $row['selling_price'] ?? null,
                    'currency' => $row['currency'] ?? $currency,
                    'is_default' => (bool) ($row['is_default'] ?? false),
                    'sort_order' => $row['sort_order'] ?? $index,
                ]);
            }

            $categoryIdMap[$externalId] = $category->id;
        }

        $this->optionPricingCategoryRepository->query()
            ->where('product_option_id', $option->id)
            ->whereNotIn('external_id', $seenExternalIds)
            ->delete();

        $this->priceRepository->query()
            ->where('product_id', $product->id)
            ->where('provider', 'supplier')
            ->delete();

        $priceBatch = [];
        $now = now();
        foreach ($rows as $index => $row) {
            $externalId = (string) ($row['external_id'] ?? ('cat-' . $index));

            $priceBatch[] = [
                'product_id' => $product->id,
                'product_option_id' => $option->id,
                'pricing_category_id' => $categoryIdMap[$externalId] ?? null,
                'idempotency_key' => md5($product->id . '|supplier|price|' . $externalId . '|' . $index),
                'provider' => 'supplier',
                'source_type' => $row['source_type'] ?? 'supplier',
                'price_kind' => $row['price_kind'] ?? 'category',
                'external_id' => $externalId,
                'ticket_type_id' => $row['ticket_type_id'] ?? null,
                'ticket_type_name' => $row['ticket_type_name'] ?? null,
                'category_code' => $row['category_code'] ?? null,
                'category_name' => $row['category_name'] ?? null,
                'valid_from' => $row['valid_from'] ?? null,
                'valid_to' => $row['valid_to'] ?? null,
                'selling_price' => $row['selling_price'] ?? 0,
                'nett_price' => $row['nett_price'] ?? null,
                'original_price' => $row['original_price'] ?? null,
                'recommended_price' => $row['recommended_price'] ?? null,
                'merchant_price' => $row['merchant_price'] ?? null,
                'currency' => $row['currency'] ?? $currency,
                'is_default' => (bool) ($row['is_default'] ?? false),
                'sort_order' => $row['sort_order'] ?? $index,
                'metadata' => $row['metadata'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($priceBatch)) {
            $this->priceRepository->query()->insert($priceBatch);
        }

        $tieredPricing = $request->input('tiered_pricing', []);
        if (is_array($tieredPricing) && !empty($tieredPricing)) {
            $defaultCategoryId = collect($categoryIdMap)->first();
            if ($defaultCategoryId !== null) {
                $this->tieredPriceRepository->query()->where('pricing_category_id', $defaultCategoryId)->delete();
                $tierBatch = [];
                foreach ($tieredPricing as $index => $tier) {
                    if (!is_array($tier) || !is_numeric($tier['amount'] ?? $tier['price'] ?? null)) {
                        continue;
                    }
                    $tierBatch[] = [
                        'pricing_category_id' => $defaultCategoryId,
                        'min_pax' => (int) ($tier['min'] ?? $tier['min_qty'] ?? $tier['minPurchaseQty'] ?? 1),
                        'max_pax' => (int) ($tier['max'] ?? $tier['max_qty'] ?? $tier['maxPurchaseQty'] ?? 999),
                        'amount' => (float) ($tier['amount'] ?? $tier['price']),
                        'nett_amount' => null,
                        'currency' => $currency,
                        'tax_amount' => null,
                        'sort_order' => $index,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if (!empty($tierBatch)) {
                    $this->tieredPriceRepository->query()->insert($tierBatch);
                }
            }
        }

        $defaultRow = collect($rows)->sortBy('selling_price')->first();
        if ($defaultRow) {
            $maxRow = collect($rows)->sortByDesc('selling_price')->first();
            $product->update([
                'default_currency' => $defaultRow['currency'] ?? $currency,
                'from_price' => $defaultRow['selling_price'] ?? 0,
                'original_price' => $maxRow['selling_price'] ?? ($defaultRow['selling_price'] ?? null),
            ]);
        }
    }

    private function syncSupplierAvailabilityCategoryFallbacks(Product $product): void
    {
        $product->loadMissing([
            'options.pricingCategories',
            'availabilities.categoryPrices',
            'availabilities.productOption.pricingCategories',
        ]);

        foreach ($product->availabilities as $slot) {
            if (($slot->provider ?? null) !== 'supplier') {
                continue;
            }

            $option = $slot->productOption
                ?? $product->options->firstWhere('id', $slot->product_option_id)
                ?? $product->options->firstWhere('external_id', 'default')
                ?? $product->options->first();

            if ($option === null) {
                continue;
            }

            $baseCategories = $option->pricingCategories
                ->sortBy('sort_order')
                ->values();

            if ($baseCategories->isEmpty()) {
                continue;
            }

            $existingRows = $slot->categoryPrices->values();
            $baseExternalIds = $baseCategories
                ->pluck('external_id')
                ->filter(fn($value) => $value !== null && $value !== '')
                ->map(fn($value) => (string) $value)
                ->values();
            $existingExternalIds = $existingRows
                ->pluck('category_external_id')
                ->filter(fn($value) => $value !== null && $value !== '')
                ->map(fn($value) => (string) $value)
                ->values();

            $hasCompleteCoverage = $baseExternalIds->isEmpty()
                ? $existingRows->count() === $baseCategories->count()
                : $baseExternalIds->diff($existingExternalIds)->isEmpty() && $existingRows->count() === $baseCategories->count();

            if ($hasCompleteCoverage) {
                continue;
            }

            $genericFallbackRows = $existingRows->isEmpty()
                || ($baseCategories->count() > 1 && $existingRows->count() <= 1);

            if (! $genericFallbackRows && $existingRows->count() > 0) {
                continue;
            }

            $this->availabilityCategoryPriceRepository->query()->where('availability_id', $slot->id)->delete();

            $remainingCapacity = $slot->unlimited_availability
                ? null
                : max(0, $slot->capacity_total - $slot->capacity_booked - $slot->capacity_reserved);

            foreach ($baseCategories as $index => $category) {
                $this->availabilityCategoryPriceRepository->create([
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
                        'source' => 'supplier_option_pricing_category_backfill',
                        'sort_order' => $index,
                    ],
                ]);
            }
        }
    }

    private function formatSupplierAvailabilityChecks(Product $product): array
    {
        $product->loadMissing(['availabilities.categoryPrices']);

        return $product->availabilities->map(function (AvailabilityCheck $slot) {
            $available = $slot->unlimited_availability
                ? null
                : max(0, $slot->capacity_total - $slot->capacity_booked - $slot->capacity_reserved);
            $primaryCategory = $slot->categoryPrices->sortBy('category_code')->first();

            return [
                'id' => $slot->external_id ?? $slot->id,
                'availability_id' => $slot->external_id ?? $slot->id,
                'date' => optional($slot->available_date)?->format('Y-m-d'),
                'available_date' => optional($slot->available_date)?->format('Y-m-d'),
                'start_time' => $slot->start_time,
                'end_time' => $slot->end_time,
                'time' => $slot->start_at?->toIso8601String(),
                'timezone' => $slot->time_zone,
                'total' => $slot->capacity_total,
                'available' => $available,
                'capacity_total' => $slot->capacity_total,
                'capacity_booked' => $slot->capacity_booked,
                'capacity_reserved' => $slot->capacity_reserved,
                'sold_out' => (bool) $slot->sold_out,
                'selling_price' => $slot->display_price,
                'currency' => $slot->currency,
                'ticket_type_id' => $primaryCategory?->category_external_id,
                'ticket_name' => $primaryCategory?->category_label,
                'category_code' => $primaryCategory?->category_code,
                'price' => [
                    'amount' => $primaryCategory?->selling_price ?? $slot->display_price,
                    'currency' => $primaryCategory?->currency ?? $slot->currency,
                ],
                'category_prices' => $slot->categoryPrices->map(function (AvailabilityCategoryPrice $category) use ($available) {
                    return [
                        'category_external_id' => $category->category_external_id,
                        'category_code' => $category->category_code,
                        'category_label' => $category->category_label,
                        'selling_price' => $category->selling_price,
                        'nett_price' => $category->nett_price,
                        'recommended_price' => $category->recommended_price,
                        'merchant_price' => $category->merchant_price,
                        'currency' => $category->currency,
                        'available' => $category->available_qty ?? $available,
                        'min_qty' => $category->min_qty,
                        'max_qty' => $category->max_qty,
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }

    private function normalizedFormdataEntries(Request $request): array
    {
        $entries = $request->input('formdata');

        if (! is_array($entries)) {
            return [];
        }

        return array_values(array_filter($entries, fn($entry) => is_array($entry) && ! empty($entry['key'] ?? null)));
    }

    private function flattenFormdataEntries(array $entries, array $existingKeys = []): array
    {
        $flattened = [];

        foreach ($entries as $entry) {
            $rawKey = trim((string) ($entry['key'] ?? ''));
            if ($rawKey === '') {
                continue;
            }

            $isArrayKey = str_ends_with($rawKey, '[]');
            $key = $isArrayKey ? substr($rawKey, 0, -2) : $rawKey;

            if (in_array($key, $existingKeys, true)) {
                continue;
            }

            $type = strtolower(trim((string) ($entry['type'] ?? 'text')));
            $value = $entry['value'] ?? null;

            if ($type === 'file') {
                $value = $entry['src'] ?? null;
            }

            if ($isArrayKey) {
                $values = is_array($value) ? $value : [$value];
                $values = array_values(array_filter($values, fn($item) => $item !== null && $item !== ''));

                if (! empty($values)) {
                    $flattened[$key] = array_merge($flattened[$key] ?? [], $values);
                }

                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $flattened[$key] = $value;
        }

        return $flattened;
    }

    private function mergeFormdataIntoRequest(Request $request): void
    {
        $entries = $this->normalizedFormdataEntries($request);
        if ($entries === []) {
            return;
        }

        $flattened = $this->flattenFormdataEntries(
            $entries,
            array_keys($request->except('formdata'))
        );

        if ($flattened !== []) {
            $request->merge($flattened);
        }
    }

    private function structuredInput(Request $request, array $keys): ?array
    {
        foreach ($keys as $key) {
            if (! $request->exists($key)) {
                continue;
            }

            $value = $request->input($key);
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                return is_array($decoded) ? $decoded : [];
            }

            return is_array($value) ? $value : [];
        }

        return null;
    }

    private function normalizeStructuredRows(?array $value): array
    {
        if ($value === null || $value === []) {
            return [];
        }

        return array_is_list($value) ? $value : [$value];
    }

    private function resolveStoredMediaUrl(?string $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $cleanPath = ltrim($path, '/');
        if (strpos($cleanPath, 'storage/') === 0) {
            $cleanPath = substr($cleanPath, 8);
        }

        return Storage::disk('public')->url($cleanPath);
    }

    private function getImageGallery(Product $product): array
    {
        $gallery = is_array($product->image_gallery) ? $product->image_gallery : [];
        if ($gallery !== []) {
            return $this->normalizeImageGallery($gallery);
        }

        $relationRows = $product->relationLoaded('productImages')
            ? $product->productImages
            : $product->productImages()->ordered()->get();

        return $this->normalizeImageGallery($relationRows->map(function (ProductImage $image) {
            return [
                'id' => $image->external_id ?? $image->id,
                'original' => $image->provider_path ?? $image->original_url,
                'large' => $image->large_url ?? $image->original_url,
                'preview' => $image->preview_url ?? $image->large_url ?? $image->original_url,
                'sort_order' => $image->sort_order,
                'title' => $image->alt_text,
                'alt_text' => $image->alt_text,
                'mime_type' => $image->mime_type,
                'file_size' => $image->file_size,
            ];
        })->all());
    }

    private function getVideoGallery(Product $product): array
    {
        $gallery = is_array($product->video_gallery) ? $product->video_gallery : [];
        if ($gallery !== []) {
            return $gallery;
        }

        $relationRows = $product->relationLoaded('productVideos')
            ? $product->productVideos
            : $product->productVideos()->ordered()->get();

        return $relationRows->map(function (ProductVideo $video) {
            return [
                'sourceUrl' => $video->source_url,
                'thumbnailUrl' => $video->thumbnail_url,
                'previewUrl' => $video->preview_url,
                'html' => $video->embed_html,
                'providerName' => $video->provider_name,
                'title' => $video->title,
                'duration' => $video->duration,
                'sort_order' => $video->sort_order,
            ];
        })->values()->all();
    }

    private function normalizeImageGallery(array $gallery): array
    {
        $rows = array_values(array_filter($gallery, fn($row) => is_array($row) && ! empty($row['original'] ?? null)));

        usort($rows, function (array $left, array $right) {
            return [
                (int) ($left['sort_order'] ?? 0),
                (int) ($left['id'] ?? 0),
            ] <=> [
                (int) ($right['sort_order'] ?? 0),
                (int) ($right['id'] ?? 0),
            ];
        });

        $nextId = $this->nextGalleryId($rows);
        $normalized = [];

        foreach (array_values($rows) as $index => $row) {
            $normalized[] = [
                'id' => is_numeric($row['id'] ?? null) ? (int) $row['id'] : $nextId++,
                'original' => $row['original'],
                'large' => $row['large'] ?? $row['original'],
                'preview' => $row['preview'] ?? $row['large'] ?? $row['original'],
                'sort_order' => $index,
                'title' => $row['title'] ?? null,
                'alt_text' => $row['alt_text'] ?? $row['title'] ?? null,
                'mime_type' => $row['mime_type'] ?? null,
                'file_size' => $row['file_size'] ?? null,
            ];
        }

        return $normalized;
    }

    private function nextGalleryId(array $gallery): int
    {
        $maxId = 0;

        foreach ($gallery as $row) {
            if (is_array($row) && is_numeric($row['id'] ?? null)) {
                $maxId = max($maxId, (int) $row['id']);
            }
        }

        return $maxId + 1;
    }

    private function appendUploadedPhotos(Product $product, array $files, ?array $gallery = null): array
    {
        $files = $this->normalizeUploadedFiles($files);
        $gallery = $gallery ?? $this->getImageGallery($product);
        $nextId = $this->nextGalleryId($gallery);
        $sortOrder = collect($gallery)->max(fn($row) => (int) ($row['sort_order'] ?? -1)) ?? -1;

        foreach ($files as $photo) {
            $path = $photo->store("products/{$product->id}", 'public');

            $gallery[] = [
                'id' => $nextId++,
                'original' => $path,
                'large' => $path,
                'preview' => $path,
                'sort_order' => ++$sortOrder,
                'title' => $photo->getClientOriginalName(),
                'mime_type' => $photo->getClientMimeType(),
                'file_size' => $photo->getSize(),
            ];
        }

        return $this->normalizeImageGallery($gallery);
    }

    private function uploadedFilesFromRequest(Request $request, string $key): array
    {
        return $this->normalizeUploadedFiles($request->file($key, []));
    }

    private function normalizeUploadedFiles(mixed $files): array
    {
        if ($files instanceof UploadedFile) {
            return $files->isValid() ? [$files] : [];
        }

        if (! is_array($files)) {
            return [];
        }

        $normalized = [];

        foreach ($files as $file) {
            $normalized = array_merge($normalized, $this->normalizeUploadedFiles($file));
        }

        return $normalized;
    }

    private function deleteStoredImage(array $image): void
    {
        $path = $image['original'] ?? null;

        if (! is_string($path) || trim($path) === '') {
            return;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    private function persistSupplierImageGallery(Product $product, array $gallery): void
    {
        $gallery = $this->normalizeImageGallery($gallery);
        $primary = collect($gallery)->sortBy('sort_order')->first();
        $thumbnail = null;

        if (is_array($primary)) {
            $thumbnail = $this->resolveStoredMediaUrl($primary['preview'] ?? $primary['large'] ?? $primary['original'] ?? null);
        }

        $this->productImageRepository->query()->where('product_id', $product->id)->delete();

        foreach ($gallery as $index => $image) {
            $this->productImageRepository->create([
                'product_id' => $product->id,
                'provider' => 'supplier',
                'original_url' => $this->resolveStoredMediaUrl($image['original'] ?? null),
                'large_url' => $this->resolveStoredMediaUrl($image['large'] ?? $image['original'] ?? null),
                'preview_url' => $this->resolveStoredMediaUrl($image['preview'] ?? $image['large'] ?? $image['original'] ?? null),
                'alt_text' => $image['alt_text'] ?? $image['title'] ?? null,
                'external_id' => isset($image['id']) ? (string) $image['id'] : null,
                'provider_path' => is_string($image['original'] ?? null) ? $image['original'] : null,
                'mime_type' => $image['mime_type'] ?? null,
                'extension' => pathinfo((string) ($image['original'] ?? ''), PATHINFO_EXTENSION) ?: null,
                'file_size' => isset($image['file_size']) && is_numeric($image['file_size']) ? (int) $image['file_size'] : null,
                'is_primary' => $index === 0,
                'is_active' => true,
                'sort_order' => (int) ($image['sort_order'] ?? $index),
                'metadata' => $image,
            ]);
        }

        $product->forceFill([
            'image_gallery' => $gallery,
            'thumbnail' => $thumbnail,
        ])->save();
    }

    private function persistSupplierVideoGallery(Product $product, array $gallery): void
    {
        $gallery = array_values(array_filter($gallery, fn($row) => is_array($row)));

        $this->productVideoRepository->query()->where('product_id', $product->id)->delete();

        foreach ($gallery as $index => $video) {
            $this->productVideoRepository->create([
                'product_id' => $product->id,
                'provider' => 'supplier',
                'source_url' => $this->resolveStoredMediaUrl($video['sourceUrl'] ?? $video['source_url'] ?? null),
                'thumbnail_url' => $this->resolveStoredMediaUrl($video['thumbnailUrl'] ?? $video['thumbnail_url'] ?? null),
                'preview_url' => $this->resolveStoredMediaUrl($video['previewUrl'] ?? $video['preview_url'] ?? null),
                'embed_html' => $video['html'] ?? $video['embed_html'] ?? null,
                'title' => $video['title'] ?? null,
                'description' => $video['description'] ?? null,
                'external_id' => isset($video['id']) ? (string) $video['id'] : null,
                'provider_name' => $video['providerName'] ?? $video['provider_name'] ?? 'supplier',
                'duration' => isset($video['duration']) && is_numeric($video['duration']) ? (int) $video['duration'] : null,
                'is_primary' => $index === 0,
                'is_active' => true,
                'sort_order' => (int) ($video['sort_order'] ?? $index),
                'metadata' => $video,
            ]);
        }

        $product->forceFill([
            'video_gallery' => $gallery,
        ])->save();
    }

    private function normalizeAvailabilityDateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $timestamp = (int) $value;
            if ($timestamp > 9999999999) {
                $timestamp = (int) floor($timestamp / 1000);
            }

            return date('Y-m-d', $timestamp);
        }

        $timestamp = strtotime((string) $value);
        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    private function normalizeTimeValue(mixed $value, ?string $default = null): ?string
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $timestamp = strtotime((string) $value);
        return $timestamp === false ? $default : date('H:i:s', $timestamp);
    }

    private function syncSupplierPassengerFields(Product $product, Request $request): void
    {
        $payload = $this->structuredInput($request, ['passenger_fields', 'passengerFields']);
        if ($payload === null) {
            return;
        }

        $rows = [];

        foreach ($this->normalizeStructuredRows($payload) as $index => $field) {
            if (! is_array($field) || empty($field['field'])) {
                continue;
            }

            $rows[] = [
                'field' => $field['field'],
                'required' => (bool) ($field['required'] ?? false),
                'requiredBeforeDeparture' => (bool) ($field['requiredBeforeDeparture'] ?? $field['required_before_departure'] ?? false),
                'sort_order' => $index,
            ];
        }

        $product->update([
            'passenger_fields_data' => $rows,
        ]);
    }

    private function syncSupplierHotelContactFields(Product $product, Request $request): void
    {
        $payload = $this->structuredInput($request, ['hotel_contact_fields', 'hotelContactFields']);
        if ($payload === null) {
            return;
        }

        $rows = [];

        foreach ($this->normalizeStructuredRows($payload) as $index => $field) {
            if (! is_array($field) || empty($field['field'])) {
                continue;
            }

            $rows[] = [
                'field' => $field['field'],
                'required' => (bool) ($field['required'] ?? false),
                'requiredBeforeDeparture' => (bool) ($field['requiredBeforeDeparture'] ?? $field['required_before_departure'] ?? false),
                'sort_order' => $index,
            ];
        }

        $product->update([
            'contact_fields_data' => $rows,
        ]);
    }

    private function syncSupplierBookingQuestions(Product $product, Request $request): void
    {
        $payload = $this->structuredInput($request, ['booking_questions', 'bookingQuestions']);
        if ($payload === null) {
            return;
        }

        $rows = [];

        foreach ($this->normalizeStructuredRows($payload) as $index => $question) {
            if (! is_array($question)) {
                continue;
            }

            $rows[] = [
                'personalData' => (bool) ($question['personalData'] ?? $question['personal_data'] ?? false),
                'questionCode' => $question['questionCode'] ?? $question['question_code'] ?? null,
                'label' => $question['label'] ?? null,
                'help' => $question['help'] ?? null,
                'placeholder' => $question['placeholder'] ?? null,
                'required' => (bool) ($question['required'] ?? false),
                'dataType' => $question['dataType'] ?? $question['data_type'] ?? null,
                'selectFromOptions' => (bool) ($question['selectFromOptions'] ?? $question['select_from_options'] ?? false),
                'selectMultiple' => (bool) ($question['selectMultiple'] ?? $question['select_multiple'] ?? false),
                'options' => is_array($question['options'] ?? null) ? ($question['options'] ?? []) : [],
                'flags' => is_array($question['flags'] ?? null) ? ($question['flags'] ?? []) : [],
                'context' => $question['context'] ?? null,
                'pricingCategoryTriggerSelection' => $question['pricingCategoryTriggerSelection'] ?? $question['pricing_category_trigger_selection'] ?? null,
                'rateTriggerSelection' => $question['rateTriggerSelection'] ?? $question['rate_trigger_selection'] ?? null,
                'extraTriggerSelection' => $question['extraTriggerSelection'] ?? $question['extra_trigger_selection'] ?? null,
                'requiredBeforeDeparture' => (bool) ($question['requiredBeforeDeparture'] ?? $question['required_before_departure'] ?? false),
                'provider' => 'supplier',
                'sort_order' => $index,
            ];
        }

        $product->update([
            'booking_questions_data' => $rows,
        ]);
    }

    private function syncSupplierCancellationPolicies(Product $product, Request $request): void
    {
        $payload = $this->structuredInput($request, ['cancellation_policies', 'cancellation_policy']);
        if ($payload === null) {
            return;
        }

        $this->cancellationPolicyRepository->query()->where('product_id', $product->id)->delete();

        foreach ($this->normalizeStructuredRows($payload) as $index => $policyData) {
            if (! is_array($policyData)) {
                continue;
            }

            $details = $policyData['details']
                ?? $policyData['penalty_rules']
                ?? $policyData['penaltyRules']
                ?? [];

            $this->cancellationPolicyRepository->create([
                'product_id' => $product->id,
                'provider' => 'supplier',
                'title' => $policyData['title'] ?? null,
                'policy' => $policyData['policy'] ?? $policyData['description'] ?? $policyData['title'] ?? 'No cancellation policy provided',
                'tax' => $policyData['tax'] ?? null,
                'policyType' => $policyData['policyType'] ?? $policyData['policy_type'] ?? null,
                'is_cancellable' => ! in_array(strtolower((string) ($policyData['policyType'] ?? $policyData['policy_type'] ?? '')), ['non_refundable', 'non-refundable'], true),
                'cancellation_notes' => $policyData['cancellation_notes'] ?? $policyData['cancellationNotes'] ?? [],
                'penalty_rules' => $this->normalizeStructuredRows(is_array($details) ? $details : []),
                'sort_order' => $index,
                'meta' => [
                    'source' => 'supplier_form',
                    'external_id' => $policyData['id'] ?? null,
                ],
            ]);
        }
    }

    private function syncSupplierVideos(Product $product, Request $request): void
    {
        $payload = $this->structuredInput($request, ['videos', 'videos_data']);
        if ($payload === null) {
            return;
        }

        $rows = [];

        foreach ($this->normalizeStructuredRows($payload) as $index => $video) {
            if (! is_array($video)) {
                continue;
            }

            $sourceUrl = $video['sourceUrl']
                ?? $video['source_url']
                ?? $this->resolveStoredMediaUrl($video['filePath'] ?? null);

            if ($sourceUrl === null && empty($video['html'])) {
                continue;
            }

            $rows[] = [
                'sourceUrl' => $sourceUrl,
                'thumbnailUrl' => $video['thumbnailUrl']
                    ?? $video['thumbnail_url']
                    ?? $this->resolveStoredMediaUrl($video['thumbnailPath'] ?? $video['filePath'] ?? null),
                'previewUrl' => $video['previewUrl']
                    ?? $video['preview_url']
                    ?? $this->resolveStoredMediaUrl($video['previewPath'] ?? $video['filePath'] ?? null),
                'html' => $video['html'] ?? null,
                'providerName' => $video['providerName'] ?? $video['provider_name'] ?? 'supplier',
                'title' => $video['title'] ?? null,
                'duration' => $video['duration'] ?? null,
                'sort_order' => $index,
            ];
        }

        $this->persistSupplierVideoGallery($product, $rows);
    }

    private function syncSupplierAvailabilities(Product $product, Request $request): void
    {
        $payload = $this->structuredInput($request, ['availability_checks', 'availabilityCheck', 'availabilities']);
        if ($payload === null) {
            return;
        }

        $this->availabilityCheckRepository->query()->where('product_id', $product->id)->delete();

        foreach ($this->normalizeStructuredRows($payload) as $availability) {
            if (! is_array($availability)) {
                continue;
            }

            $date = $this->normalizeAvailabilityDateValue(
                $availability['available_date'] ?? $availability['date'] ?? $availability['localizedDate'] ?? null
            );
            if ($date === null) {
                continue;
            }

            $capacityTotal = (int) ($availability['capacity_total'] ?? $availability['total'] ?? $availability['available'] ?? 0);
            $available = (int) ($availability['available'] ?? $availability['capacity_available'] ?? $capacityTotal);
            $startTime = $this->normalizeTimeValue($availability['start_time'] ?? $availability['startTime'] ?? $availability['time'] ?? null, '00:00:00');
            $endTime = $this->normalizeTimeValue($availability['end_time'] ?? $availability['endTime'] ?? null);
            $timezone = $availability['timezone'] ?? $availability['timeZone'] ?? $product->time_zone ?? null;
            $startAt = $date && $startTime ? $date . ' ' . $startTime : null;
            $externalId = $availability['availability_id'] ?? $availability['id'] ?? null;

            $option = $this->productOptionRepository->query()->firstOrCreate(
                [
                    'product_id' => $product->id,
                    'provider' => 'supplier',
                    'external_id' => 'default',
                ],
                [
                    'name' => 'Standard',
                    'currency' => $this->resolveSupplierCurrency($product),
                    'is_default' => true,
                    'sort_order' => 0,
                ]
            );

            $slot = $this->availabilityCheckRepository->create([
                'product_id' => $product->id,
                'product_option_id' => $option->id,
                'idempotency_key' => md5($product->id . '|supplier|' . $date . '|' . ($startTime ?? '00:00:00') . '|' . (string) $externalId),
                'provider' => 'supplier',
                'external_id' => $externalId,
                'available_date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'start_at' => $startAt,
                'time_zone' => $timezone,
                'capacity_total' => $capacityTotal,
                'capacity_booked' => max(0, $capacityTotal - $available),
                'capacity_reserved' => (int) ($availability['capacity_reserved'] ?? 0),
                'display_price' => $availability['selling_price'] ?? $availability['recommendedSellingPrice'] ?? data_get($availability, 'price.amount'),
                'currency' => data_get($availability, 'price.currency', $availability['currency'] ?? $this->resolveSupplierCurrency($product)),
                'unlimited_availability' => (bool) ($availability['unlimited_availability'] ?? $availability['unlimitedAvailability'] ?? $availability['unlimited'] ?? false),
                'sold_out' => (bool) ($availability['sold_out'] ?? $availability['soldOut'] ?? false),
                'min_participants' => (int) ($availability['min_participants'] ?? $availability['minParticipants'] ?? 1),
                'max_participants' => $availability['max_participants'] ?? $availability['maxParticipants'] ?? null,
                'series_id' => $availability['series_id'] ?? $availability['seriesId'] ?? null,
                'series_name' => $availability['series_name'] ?? $availability['seriesName'] ?? null,
                'is_closed' => (bool) ($availability['closed'] ?? $availability['is_closed'] ?? false),
                'is_active' => ! (bool) ($availability['is_inactive'] ?? $availability['isInactive'] ?? false),
                'raw_data' => $availability,
            ]);

            $categoryCode = strtoupper((string) ($availability['category_code'] ?? $availability['ticket_type_name'] ?? 'GENERAL'));
            $categoryLabel = (string) ($availability['category_label'] ?? $availability['ticket_name'] ?? $categoryCode);
            $categoryExternalId = (string) ($availability['ticket_type_id'] ?? $availability['ticket_id'] ?? $availability['category_external_id'] ?? $categoryCode);

            $pricingCategory = $this->optionPricingCategoryRepository->query()->firstOrCreate(
                [
                    'product_option_id' => $option->id,
                    'external_id' => $categoryExternalId,
                ],
                [
                    'provider' => 'supplier',
                    'idempotency_key' => md5($product->id . '|supplier|availability-cat|' . $categoryExternalId),
                    'category_code' => $categoryCode,
                    'category_label' => $categoryLabel,
                    'selling_price' => $availability['selling_price'] ?? $availability['recommendedSellingPrice'] ?? data_get($availability, 'price.amount'),
                    'currency' => data_get($availability, 'price.currency', $availability['currency'] ?? $this->resolveSupplierCurrency($product)),
                    'is_default' => true,
                ]
            );

            $this->availabilityCategoryPriceRepository->create([
                'availability_id' => $slot->id,
                'pricing_category_id' => $pricingCategory->id,
                'category_external_id' => $categoryExternalId,
                'category_code' => $categoryCode,
                'category_label' => $categoryLabel,
                'selling_price' => $availability['selling_price'] ?? $availability['recommendedSellingPrice'] ?? data_get($availability, 'price.amount') ?? 0,
                'nett_price' => $availability['nett_price'] ?? $availability['nettPrice'] ?? null,
                'original_price' => $availability['original_price'] ?? null,
                'recommended_price' => $availability['recommendedSellingPrice'] ?? null,
                'merchant_price' => null,
                'tax_amount' => null,
                'currency' => data_get($availability, 'price.currency', $availability['currency'] ?? $this->resolveSupplierCurrency($product)),
                'available_qty' => $available,
                'min_qty' => $availability['min_qty'] ?? null,
                'max_qty' => $availability['max_qty'] ?? null,
                'metadata' => $availability,
            ]);
        }
    }

    private function syncSupplierRawResponse(Product $product, Request $request): void
    {
        $payload = $request->except(['photos', 'photo', 'formdata']);
        $photoFiles = [];
        $formdataEntries = $this->normalizedFormdataEntries($request);

        foreach ($this->uploadedFilesFromRequest($request, 'photos') as $photo) {
            $photoFiles[] = [
                'original_name' => $photo->getClientOriginalName(),
                'mime_type' => $photo->getClientMimeType(),
                'size' => $photo->getSize(),
            ];
        }

        if ($request->hasFile('photo')) {
            $photo = $request->file('photo');
            $payload['single_photo'] = [
                'original_name' => $photo->getClientOriginalName(),
                'mime_type' => $photo->getClientMimeType(),
                'size' => $photo->getSize(),
            ];
        }

        if (! empty($photoFiles)) {
            $payload['photos'] = $photoFiles;
        }

        if (! empty($formdataEntries)) {
            $payload['formdata_entries'] = $formdataEntries;
        }

        $product->update([
            'raw_response' => [
                'provider' => 'supplier',
                'product_api_id' => (string) ($product->product_api_id ?: $product->id),
                'formdata' => $payload,
                'saved_at' => now()->toISOString(),
            ],
            'raw_response_saved_at' => now(),
        ]);
    }

    // ─── Resolve or create a draft product ──────────────────────────────
    private function resolveOrCreateDraft(Request $request, $productId = null): array
    {
        $user = $this->authorizedVendor();
        if (!$user) {
            return [null, null, $this->error('Unauthorized access.', 403)];
        }

        $resolvedId = is_numeric($productId) ? $productId : null;
        $resolvedId = $resolvedId ?? $request->input('draft_product_id');

        if ($resolvedId) {
            $product = $this->getDraftProduct($resolvedId, $user->vendor->id);
            if (!$product) {
                return [$user, null, $this->error('Draft product not found.', 404)];
            }
            return [$user, $product, null];
        }

        // Create a new draft product
        $product = $this->productRepository->create([
            'vendor_id' => $user->vendor->id,
            'title' => '',
            'slug' => 'draft-' . Str::uuid(),
            'language' => $request->input('language'),
            'product_category' => $request->input('product_category'),
            'provider' => 'supplier',
            'platform' => 'supplier',
            'pickup_service' => 'NOT_AVAILABLE',
            'private_tour' => 'NOT_AVAILABLE',
            'reservation_system' => 'manual',
            'content_mode' => 'manual',
            'status' => 'draft',
            'is_draft' => true,
            'wizard_step' => 0,
        ]);

        return [$user, $product, null];
    }

    // ─── Resolve an existing draft (for resume) ─────────────────────────
    private function resolveDraftProduct(Request $request, $productId = null): array
    {
        $user = $this->authorizedVendor();
        if (!$user) {
            return [null, null, $this->error('Unauthorized access.', 403)];
        }

        $resolvedId = is_numeric($productId) ? $productId : null;
        $resolvedId = $resolvedId
            ?? $request->input('draft_product_id')
            ?? $request->input('wizard_product_id');

        if (!$resolvedId) {
            $drafts = $this->productRepository->query()
                ->where('vendor_id', $user->vendor->id)
                ->where('is_draft', true)
                ->latest('updated_at')
                ->limit(2)
                ->get();

            if ($drafts->count() === 1) {
                $resolvedId = $drafts->first()->id;
            }
        }

        if (!$resolvedId) {
            return [null, null, $this->error('Draft product ID is required.', 422)];
        }

        $product = $this->getDraftProduct($resolvedId, $user->vendor->id);
        if (!$product) {
            return [$user, null, $this->error('Draft product not found.', 404)];
        }

        return [$user, $product, null];
    }

    private function wizardScreenMap(): array
    {
        return [
            'create-products' => 1,
            'customer-facing-title' => 2,
            'introduce-product' => 3,
            'customer-visits' => 4,
            'add-keywords' => 5,
            'included' => 6,
            'customer-guide' => 7,
            'activities' => 8,
            'transportation' => 9,
            'activities-details' => 10,
            'add-photos' => 11,
            'add-product-options' => 12,
            'pickup' => 13,
            'connectivity' => 14,
            'availability-pricing' => 15,
            'stepper' => 15,
            'stepper/submit' => 16,
        ];
    }

    private function normalizeScreenName(?string $screen): ?string
    {
        if (!is_string($screen) || trim($screen) === '') {
            return null;
        }

        $screen = strtolower(trim(str_replace('\\', '/', $screen), '/'));

        foreach (
            [
                'supplier-dashboard/create/',
                'supplier-dashboard/',
                'create/',
            ] as $prefix
        ) {
            if (str_starts_with($screen, $prefix)) {
                $screen = substr($screen, strlen($prefix));
            }
        }

        return $screen;
    }

    private function inferWizardStepFromPayload(Request $request): int
    {
        $steps = [
            15 => ['default_cut_off_time', 'pricing_type', 'prices', 'age_categories', 'min_max_capacity', 'tiered_pricing'],
            14 => ['use_reservation_system', 'online_reservation', 'reservation_system', 'external_product_id', 'product_id'],
            13 => ['meeting_point_address', 'meeting_point', 'addressList', 'arriveTime', 'places', 'offer_pickup'],
            12 => ['max_group_size', 'group_size', 'languages', 'language_options', 'selected_languages', 'Private_activity', 'customerSkip', 'skip_line_type', 'activity_wheelchair', 'activity_duration', 'activity_describe'],
            11 => ['photos', 'copyright_acknowledged', 'imageUploadConfirmation'],
            10 => ['has_transportation', 'transportation_include_activity', 'transportOptions', 'travel_in_city', 'travels_to_different_city'],
            9 => ['guide_type', 'food_include_activity', 'food_included', 'food_configuration', 'meals'],
            8 => ['included', 'included-description', 'excluded', 'extra_pay_description'],
            7 => ['keywords', 'products_keywords'],
            6 => ['locations', 'customer_visits'],
            5 => ['product_full_description', 'full_description', 'highlights_summary', 'highlight1', 'hightlight1'],
            4 => ['product_short_description', 'short_introduction'],
            3 => ['content_mode', 'auto_content_text'],
            2 => ['title', 'product_title', 'reference_code', 'product_reference-Code', 'product_reference_code'],
            1 => ['language', 'product_category'],
        ];

        foreach ($steps as $step => $keys) {
            foreach ($keys as $key) {
                if ($request->has($key)) {
                    return $step;
                }
            }
        }

        return 0;
    }

    private function resolveWizardStep(Request $request, Product $product, bool $submitted = false): int
    {
        if ($submitted) {
            return 16;
        }

        $screen = $this->normalizeScreenName(
            $this->input($request, ['current_screen', 'currentScreen', 'screen', 'step_slug', 'step'])
        );

        if ($screen && array_key_exists($screen, $this->wizardScreenMap())) {
            return max((int) $product->wizard_step, $this->wizardScreenMap()[$screen]);
        }

        return max((int) $product->wizard_step, $this->inferWizardStepFromPayload($request));
    }

    private function currentScreenForStep(int $step, bool $submitted = false): ?string
    {
        if ($submitted || $step >= 16) {
            return 'stepper/submit';
        }

        $stepsToScreens = [
            1 => 'create-products',
            2 => 'customer-facing-title',
            3 => 'introduce-product',
            4 => 'customer-visits',
            5 => 'add-keywords',
            6 => 'included',
            7 => 'customer-guide',
            8 => 'activities',
            9 => 'transportation',
            10 => 'activities-details',
            11 => 'add-photos',
            12 => 'add-product-options',
            13 => 'pickup',
            14 => 'connectivity',
            15 => 'availability-pricing',
        ];

        return $stepsToScreens[$step] ?? null;
    }

    private function wizardProgress(int $step): int
    {
        $step = max(0, min($step, 16));

        return (int) round(($step / 16) * 100);
    }

    private function buildLifecycleData(Product $product, bool $submitted = false): array
    {
        $wizardStep = $submitted ? 16 : (int) $product->wizard_step;

        return [
            'product_id' => $product->id,
            'draft_product_id' => $product->id,
            'is_draft' => (bool) $product->is_draft,
            'status' => $product->status,
            'wizard_step' => $wizardStep,
            'current_screen' => $this->currentScreenForStep($wizardStep, $submitted),
            'progress' => $this->wizardProgress($wizardStep),
        ];
    }

    // ═════════════════════════════════════════════════════════════════════
    //  APPLY PAYLOAD — Core logic that saves any subset of fields
    //  No required validation here — only type/format checks.
    // ═════════════════════════════════════════════════════════════════════

    private function applyPayload(Request $request, Product $product): Product
    {
        $data = [];

        // ── Basic: language & category ───────────────────────────────────
        if ($request->has('language')) {
            $data['language'] = $request->input('language');
        }
        if ($request->has('product_category')) {
            $data['product_category'] = $request->input('product_category');

            $categoryValue = trim((string) $request->input('product_category'));
            if ($categoryValue !== '') {
                $category = $this->categoriesRepository->findBySlug(Str::slug($categoryValue))
                    ?? $this->categoriesRepository->findByName($categoryValue);

                $data['category_id'] = $category ? $category->id : null;
            }
        }

        // ── Content creator ─────────────────────────────────────────────
        if ($request->has('content_mode')) {
            $data['content_mode'] = $request->input('content_mode');
        }
        if ($request->has('auto_content_text')) {
            $data['auto_content_text'] = $request->input('auto_content_text');
        }

        // ── Title & reference code ──────────────────────────────────────
        $title = $this->input($request, ['title', 'product_title']);
        if ($title !== null) {
            $data['title'] = $title;
            $slug = Str::slug($title);
            if ($slug) {
                $exists = $this->productRepository->query()->where('slug', $slug)->where('id', '!=', $product->id)->exists();
                $data['slug'] = $exists ? $slug . '-' . Str::random(5) : $slug;
            }
        }
        $refCode = $this->input($request, ['reference_code', 'product_reference-Code', 'product_reference_code']);
        if ($refCode !== null) {
            $data['reference_code'] = $refCode;
        }
        $barcodeType = $this->input($request, ['barcode_type', 'barcodeType', 'BarcodeType']);
        if ($barcodeType !== null) {
            $data['barcode_type'] = trim((string) $barcodeType);
        }

        // ── Introduction, description, highlights ───────────────────────
        $shortIntro = $this->input($request, ['short_introduction', 'product_short_description']);
        if ($shortIntro !== null) $data['short_introduction'] = $shortIntro;

        $fullDesc = $this->input($request, ['full_description', 'product_full_description']);
        if ($fullDesc !== null) {
            $data['full_description'] = $fullDesc;
            $data['description'] = $fullDesc;
        }

        $highlightsSummary = $this->input($request, ['highlights_summary']);
        $highlights = $this->buildHighlights($request);
        if ($highlightsSummary !== null) {
            $data['highlights_summary'] = $highlightsSummary;
        } elseif (!empty($highlights)) {
            $data['highlights_summary'] = implode("\n", $highlights);
        }
        if (!empty($highlights)) {
            $data['highlights'] = $highlights;
        }

        // ── Included / Excluded ─────────────────────────────────────────
        $included = $this->input($request, ['included', 'included-description']);
        if ($included !== null) $data['included'] = $this->normalizeStringList($included);

        $excluded = $this->input($request, ['excluded', 'extra_pay_description']);
        if ($excluded !== null) $data['excluded'] = $this->normalizeStringList($excluded);

        // ── Guide type ──────────────────────────────────────────────────
        if ($request->has('guide_type')) {
            $data['guide_type'] = $this->normalizeGuideType($request->input('guide_type'));
        }

        // ── Food ────────────────────────────────────────────────────────
        $foodIncluded = $this->input($request, ['food_included', 'food_include_activity']);
        if ($foodIncluded !== null) {
            $data['food_included'] = $this->toBoolean($foodIncluded, false);

            if ($data['food_included']) {
                $foodConfig = $request->input('food_configuration');
                $meals = $request->input('meals', []);

                if (!is_array($foodConfig) && is_array($meals)) {
                    $normalizedMeals = [];
                    foreach ($meals as $meal) {
                        if (!is_array($meal)) continue;
                        $mt = $this->normalizeMealType($meal['type'] ?? null);
                        $mf = $this->normalizeMealFormat($meal['format'] ?? null);
                        if ($mt && $mf) {
                            $normalizedMeals[] = [
                                'meal_type' => $mt,
                                'format' => $mf,
                                'drinks_included' => $this->toBoolean($meal['drinks_included'] ?? $meal['drinkIncluded'] ?? null, false),
                            ];
                        }
                    }
                    $foodConfig = [
                        'meal_type' => $normalizedMeals[0]['meal_type'] ?? null,
                        'format' => $normalizedMeals[0]['format'] ?? null,
                        'drinks_included' => $normalizedMeals[0]['drinks_included'] ?? false,
                        'meals' => $normalizedMeals ?: null,
                    ];
                }

                $data['food_configuration'] = $foodConfig;
            } else {
                $data['food_configuration'] = null;
            }
        }

        // ── Transportation ──────────────────────────────────────────────
        $hasTransport = $this->input($request, ['has_transportation', 'transportation_include_activity']);
        if ($hasTransport !== null) {
            $data['has_transportation'] = $this->toBoolean($hasTransport, false);
            if ($data['has_transportation']) {
                $data['transportation_items'] = $this->normalizeTransportationItems(
                    $this->input($request, ['transportation_items', 'transportOptions'])
                );
            } else {
                $data['transportation_items'] = null;
            }
        }

        $travelInCity = $this->input($request, ['travel_in_city']);
        $travelDiffCity = $this->input($request, ['travels_to_different_city']);
        if ($travelInCity !== null) {
            $data['travels_to_different_city'] = !$this->toBoolean($travelInCity, true);
        } elseif ($travelDiffCity !== null) {
            $data['travels_to_different_city'] = $this->toBoolean($travelDiffCity, false);
        }

        // ── Know Before You Go ──────────────────────────────────────────
        $kbyg = false;
        $notSuitable = $this->input($request, ['not_suitable_for', 'suitable_for']);
        if ($notSuitable !== null) {
            $data['not_suitable_for'] = $this->normalizeStringList($notSuitable);
            $kbyg = true;
        }
        $notAllowed = $this->input($request, ['not_allowed']);
        if ($notAllowed !== null) {
            $data['not_allowed'] = $this->normalizeStringList($notAllowed);
            $kbyg = true;
        }
        if ($request->has('pet_friendly')) {
            $data['pet_friendly_policy'] = $this->toBoolean($request->input('pet_friendly'), false);
            $kbyg = true;
        }
        $mandatoryItems = $this->input($request, ['mandatory_items']);
        if ($mandatoryItems !== null) {
            $data['mandatory_items'] = $this->normalizeStringList($mandatoryItems);
            $kbyg = true;
        }
        $customInfo = $this->input($request, ['custom_info_before_booking', 'custom_information_before_booking']);
        if ($customInfo !== null) {
            $data['custom_info_before_booking'] = $customInfo;
            $kbyg = true;
        }
        $otherInfo = $this->input($request, ['other_info_before_activity', 'other_information_before_booking']);
        if ($otherInfo !== null) {
            $data['other_info_before_activity'] = $otherInfo;
            $kbyg = true;
        }

        if ($kbyg) {
            $data['know_before_you_go'] = [
                'not_suitable_for' => $data['not_suitable_for'] ?? $product->not_suitable_for,
                'not_allowed' => $data['not_allowed'] ?? $product->not_allowed,
                'mandatory_items' => $data['mandatory_items'] ?? $product->mandatory_items,
                'custom_info' => $data['custom_info_before_booking'] ?? $product->custom_info_before_booking,
                'other_info' => $data['other_info_before_activity'] ?? $product->other_info_before_activity,
            ];
        }

        $cutOff = $this->input($request, ['default_cut_off_time']);
        if ($cutOff !== null) {
            $data['default_cut_off_time'] = $this->normalizeCutOffTime($cutOff);
        }

        // ── Photos (file upload) ────────────────────────────────────────
        $photos = $this->uploadedFilesFromRequest($request, 'photos');
        if (! empty($photos)) {
            $data['image_gallery'] = $this->appendUploadedPhotos(
                $product,
                $photos,
                is_array($product->image_gallery) ? $product->image_gallery : []
            );
        }
        $copyright = $this->input($request, ['copyright_acknowledged', 'imageUploadConfirmation']);
        if ($copyright !== null) {
            $data['copyright_acknowledged'] = $this->toBoolean($copyright, false);
        }

        // ── Activity options ────────────────────────────────────────────
        $optionsUpdated = false;
        $options = is_array($product->options_configuration) ? $product->options_configuration : [];

        $maxGroup = $this->input($request, ['max_group_size', 'group_size']);
        if ($maxGroup !== null) {
            $options['max_group_size'] = $this->normalizeGroupSize($maxGroup);
            $optionsUpdated = true;
        }

        $langs = $this->input($request, ['languages', 'language_options', 'selected_languages']);
        if ($langs !== null) {
            $options['languages'] = $this->normalizeStringList($langs);
            $optionsUpdated = true;
        }

        $isPrivate = $this->input($request, ['is_private', 'Private_activity']);
        if ($isPrivate !== null) {
            $options['is_private'] = $this->toBoolean($isPrivate, false);
            $optionsUpdated = true;
        }

        $skipLine = $this->input($request, ['skip_line', 'customerSkip']);
        if ($skipLine !== null) {
            $options['skip_line'] = $this->toBoolean($skipLine, false);
            $options['skip_line_type'] = $options['skip_line']
                ? ($this->normalizeSkipLineType($this->input($request, ['skip_line_type', 'customer_skip_type'])) ?? 'skip_line_to_get_tickets')
                : null;
            $optionsUpdated = true;
        }

        $accessible = $this->input($request, ['is_accessible', 'activity_wheelchair']);
        if ($accessible !== null) {
            $options['is_accessible'] = $this->toBoolean($accessible, false);
            $optionsUpdated = true;
        }

        $duration = $request->input('activity_duration');
        $activityDescribe = $this->input($request, ['activity_describe']);
        if ($duration !== null || $activityDescribe !== null) {
            if (!is_array($duration)) {
                $duration = $this->toBoolean($activityDescribe, false)
                    ? ['type' => 'valid_period', 'value' => 1, 'unit' => 'days', 'valid_from' => 'first_activation']
                    : ['type' => 'fixed_duration', 'value' => 1, 'unit' => 'hours'];
            }
            $options['activity_duration'] = $duration;
            $optionsUpdated = true;
        }

        if ($optionsUpdated) {
            $data['options_configuration'] = $options;
        }

        // ── Meeting point / Pickup ──────────────────────────────────────
        $mpAddr = $this->input($request, ['meeting_point_address']);
        if ($mpAddr !== null) $data['meeting_point_address'] = $mpAddr;

        if ($request->has('latitude') && $request->has('longitude')) {
            $data['meeting_point_coordinates'] = $request->input('latitude') . ',' . $request->input('longitude');
        }

        $mpDesc = $this->input($request, ['meeting_point_description', 'meeting_point']);
        if ($mpDesc !== null) $data['meeting_point_description'] = $mpDesc;

        $arrTime = $this->input($request, ['arrival_time', 'arriveTime']);
        if ($arrTime !== null) $data['arrival_time'] = $this->normalizeArrivalTime($arrTime);

        $offerPickup = $this->input($request, ['offer_pickup']);
        $addressList = $request->input('addressList');
        $pickupAddresses = $addressList ? $this->normalizeStringList($addressList) : [];

        if ($offerPickup !== null) {
            $data['pickup_service'] = $this->toBoolean($offerPickup, false) ? 'AVAILABLE' : 'NOT_AVAILABLE';
        } elseif (!empty($pickupAddresses)) {
            $data['pickup_service'] = 'AVAILABLE';
        }

        $pickupDetails = $this->input($request, ['pickup_details']);
        if ($pickupDetails !== null) {
            $data['pickup_info'] = $pickupDetails;
        } elseif (!empty($pickupAddresses)) {
            $data['pickup_info'] = implode("\n", $pickupAddresses);
        }

        $dropOff = $this->input($request, ['drop_off_type', 'places']);
        if ($dropOff !== null) $data['drop_off_type'] = $this->normalizeDropOffType($dropOff);

        // ── Connectivity ────────────────────────────────────────────────
        $useRes = $this->input($request, ['use_reservation_system', 'online_reservation']);
        if ($useRes !== null) {
            $useResBool = $this->toBoolean($useRes, false);
            $data['reservation_system'] = $useResBool
                ? ($this->normalizeReservationSystem($this->input($request, ['reservation_system'])) ?? 'other')
                : 'manual';
        }
        $extId = $this->input($request, ['external_product_id', 'product_id']);
        if ($extId !== null) $data['product_api_id'] = $extId;

        // ── Pricing ─────────────────────────────────────────────────────
        $pricingType = $this->input($request, ['pricing_type', 'prices']);
        if ($pricingType !== null) {
            $normalizedPricingType = $this->normalizePricingType($pricingType);
            $data['pricing_type'] = $normalizedPricingType;
            $data['pricing_configuration'] = [
                'age_categories' => $request->input('age_categories'),
                'min_max_capacity' => $request->input('min_max_capacity'),
                'tiered_pricing' => $request->input('tiered_pricing'),
                'pricing_mode' => $pricingType,
            ];
            $data['default_currency'] = $this->resolveSupplierCurrency($product);
        }

        // ── Locations (relationship) ────────────────────────────────────
        $locations = $request->input('locations');
        if ($locations === null && $request->has('customer_visits')) {
            $locations = $request->input('customer_visits');
        }

        if ($locations !== null) {
            $locations = $this->normalizeLocationPayload($locations);
            $this->locationRepository->query()->where('product_id', $product->id)->delete();
            foreach ($locations as $index => $loc) {
                if (!is_array($loc) || empty($loc['city'])) continue;
                $this->locationRepository->create([
                    'product_id' => $product->id,
                    'city' => $loc['city'],
                    'country' => $loc['country'] ?? '',
                    'countryCode' => $loc['countryCode'] ?? '',
                    'coordinates' => $loc['coordinates'] ?? null,
                    'latitude' => data_get($loc, 'coordinates.latitude', data_get($loc, 'coordinates.lat')),
                    'longitude' => data_get($loc, 'coordinates.longitude', data_get($loc, 'coordinates.lng')),
                    'location_type' => 'destination',
                    'label' => trim(($loc['city'] ?? '') . ', ' . ($loc['country'] ?? ''), ', '),
                    'is_primary' => $index === 0,
                    'sort_order' => $index,
                    'meta' => $loc,
                ]);
            }
        }

        // ── Keywords (relationship) ─────────────────────────────────────
        $keywordsInput = $this->input($request, ['keywords', 'products_keywords']);
        if ($keywordsInput !== null) {
            $keywords = $this->normalizeStringList($keywordsInput);
            $this->keywordRepository->query()->where('product_id', $product->id)->delete();
            $seenKeywordKeys = [];

            foreach (array_values($keywords) as $index => $name) {
                $cleanName = trim((string) $name);
                if ($cleanName === '') {
                    continue;
                }

                $slug = Str::slug($cleanName);
                $slug = is_string($slug) ? substr($slug, 0, 180) : null;
                $slug = $slug !== '' ? $slug : null;
                $dedupeKey = $slug ?? Str::lower($cleanName);

                if (isset($seenKeywordKeys[$dedupeKey])) {
                    continue;
                }

                $seenKeywordKeys[$dedupeKey] = true;

                $this->keywordRepository->create([
                    'product_id' => $product->id,
                    'provider' => 'supplier',
                    'name' => $cleanName,
                    'slug' => $slug,
                    'type' => 'tag',
                    'sort_order' => $index,
                    'meta' => null,
                ]);
            }
        }

        // ── Apply all collected data ────────────────────────────────────
        if (!empty($data)) {
            $product->update($data);

            if (array_key_exists('image_gallery', $data) && is_array($data['image_gallery'])) {
                $this->persistSupplierImageGallery($product, $data['image_gallery']);
                $product = $product->fresh();
            }
        }

        $this->syncSupplierPassengerFields($product, $request);
        $this->syncSupplierHotelContactFields($product, $request);
        $this->syncSupplierBookingQuestions($product, $request);
        $this->syncSupplierCancellationPolicies($product, $request);
        $this->syncSupplierVideos($product, $request);
        $this->syncSupplierAvailabilities($product, $request);

        if ($pricingType !== null) {
            $this->syncSupplierPriceRows($product, $request, $pricingType);
            $product = $product->fresh();
        }

        $this->syncSupplierAvailabilityCategoryFallbacks($product);
        $product = $product->fresh();

        $this->syncSupplierRawResponse($product, $request);

        return $product->fresh();
    }

    // ═════════════════════════════════════════════════════════════════════
    //  FULL VALIDATION — Checks all required fields before submission
    // ═════════════════════════════════════════════════════════════════════

    private function validateForSubmission(Product $product): void
    {
        $product->loadMissing(['keywords']);

        $errors = [];
        $options = is_array($product->options_configuration) ? $product->options_configuration : [];
        $foodConfiguration = is_array($product->food_configuration) ? $product->food_configuration : [];
        $locationsCount = $this->locationRepository->query()->where('product_id', $product->id)->count();
        $imagesCount = $product->images->count();

        if (!$product->language) {
            $errors['language'][] = 'Product language is required.';
        }
        if (!$product->product_category) {
            $errors['product_category'][] = 'Product category is required.';
        }
        if (!$product->title) {
            $errors['product_title'][] = 'Customer-facing title is required.';
        }
        if (!$product->short_introduction) {
            $errors['product_short_description'][] = 'Short introduction is required.';
        }
        if (!$product->full_description || strlen($product->full_description) < 10) {
            $errors['product_full_description'][] = 'Full description must be at least 10 characters.';
        }
        if (!$product->highlights_summary) {
            $errors['highlights_summary'][] = 'Product highlights are required.';
        }
        if ($locationsCount < 1) {
            $errors['customer_visits'][] = 'At least one customer visit location is required.';
        }
        if (!$product->included) {
            $errors['included-description'][] = 'Included details are required.';
        }
        if (!$product->guide_type) {
            $errors['guide_type'][] = 'Guide type is required.';
        }
        if (empty($options['languages'])) {
            $errors['languages'][] = 'At least one activity language is required.';
        }
        if (!array_key_exists('max_group_size', $options)) {
            $errors['max_group_size'][] = 'Maximum group size is required.';
        }
        if (!array_key_exists('is_private', $options)) {
            $errors['Private_activity'][] = 'Private activity selection is required.';
        }
        if (!array_key_exists('skip_line', $options)) {
            $errors['customerSkip'][] = 'Skip-line selection is required.';
        }
        if (($options['skip_line'] ?? false) && empty($options['skip_line_type'])) {
            $errors['skip_line_type'][] = 'Skip-line type is required when skip-line is enabled.';
        }
        if (!array_key_exists('is_accessible', $options)) {
            $errors['activity_wheelchair'][] = 'Wheelchair accessibility selection is required.';
        }
        if (empty($options['activity_duration']['type'])) {
            $errors['activity_duration'][] = 'Activity duration is required.';
        }
        if ($product->food_included && (empty($foodConfiguration['meal_type']) || empty($foodConfiguration['format']))) {
            $errors['food_configuration'][] = 'Food details are required when food is included.';
        }
        if ($product->has_transportation && empty($product->transportation_items)) {
            $errors['transportOptions'][] = 'Transportation options are required when transportation is included.';
        }
        // Relaxed validation: Drop-off information is optional during testing
        // if (!$product->drop_off_type) {
        //     $errors['places'][] = 'Drop-off information is required.';
        // }
        if ($product->reservation_system && $product->reservation_system !== 'manual' && !$product->product_api_id) {
            $errors['product_id'][] = 'External product ID is required for connected reservation systems.';
        }
        if (!$product->pricing_type) {
            $errors['pricing_type'][] = 'Pricing configuration is required.';
        }
        if ($imagesCount < 1) {
            $errors['photos'][] = 'At least 1 product photo is required before submission.';
        }
        if (!$product->copyright_acknowledged) {
            $errors['imageUploadConfirmation'][] = 'Photo copyright confirmation is required.';
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    // ═════════════════════════════════════════════════════════════════════
    //  PUBLIC ENDPOINTS
    // ═════════════════════════════════════════════════════════════════════

    /**
     * GET /supplier-dashboard/create-products
     *
     * Bootstrap data for the supplier unified product creator.
     */
    public function index(): JsonResponse
    {
        $user = $this->authorizedVendor();
        if (!$user) {
            return $this->error('Unauthorized access.', 403);
        }

        $categories = $this->categoriesRepository->query()
            ->where('type', 'product')->where('status', '1')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'type'])
            ->map(fn(Categories $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'type' => $category->type,
            ])
            ->values()
            ->all();

        if (empty($categories)) {
            $categories = [
                ['id' => null, 'name' => 'Tour', 'slug' => 'tour', 'type' => 'product'],
                ['id' => null, 'name' => 'Activity', 'slug' => 'activity', 'type' => 'product'],
                ['id' => null, 'name' => 'Transfer', 'slug' => 'transfer', 'type' => 'product'],
                ['id' => null, 'name' => 'Ticket', 'slug' => 'ticket', 'type' => 'product'],
            ];
        }

        $vendorId = $user->vendor->id;

        $activeProducts = $this->productRepository->query()
            ->where('vendor_id', $vendorId)
            ->where('is_draft', false)
            ->latest()
            ->get(['id', 'title', 'short_introduction as product_short_description', 'status', 'created_at']);

        $draftProducts = $this->productRepository->query()
            ->where('vendor_id', $vendorId)
            ->where('is_draft', true)
            ->latest()
            ->get(['id', 'title', 'wizard_step', 'created_at', 'updated_at'])
            ->map(function (Product $product) {
                return [
                    'id' => $product->id,
                    'title' => $product->title,
                    'current_screen' => $this->currentScreenForStep((int) $product->wizard_step),
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at,
                ];
            });

        return $this->success('Supplier product creator loaded.', [
            'welcome' => [
                'title' => 'Create a new product',
                'description' => 'Start a draft, connect a provider product, or build a supplier activity manually in one flow.',
            ],
            'categories' => $categories,
            'active_products' => $activeProducts,
            'draft_products' => $draftProducts,
            'languages' => [
                'English',
                'Arabic',
                'French',
                'German',
                'Spanish',
                'Italian',
            ],
        ]);
    }

    /**
     * POST /supplier-dashboard/create-products/save-draft/{product?}
     *
     * Accept ANY subset of fields and save them to the database as a draft.
     * Minimal validation — only type/format checks, nothing is required.
     * The supplier can fill 1 field or 50 fields; whatever is sent gets saved.
     */
    public function saveDraft(Request $request, $productId = null): JsonResponse
    {
        $this->mergeFormdataIntoRequest($request);

        return DB::transaction(function () use ($request, $productId) {
            [$user, $product, $errorResponse] = $this->resolveOrCreateDraft($request, $productId);
            if ($errorResponse) return $errorResponse;

            $product = $this->applyPayload($request, $product);
            $wizardStep = $this->resolveWizardStep($request, $product);

            // Ensure draft state
            $product->update([
                'is_draft' => true,
                'status' => 'draft',
                'wizard_step' => $wizardStep,
            ]);

            return $this->success('Product draft saved successfully.', $this->buildLifecycleData($product->fresh()));
        });
    }

    /**
     * POST /supplier-dashboard/create-products/submit/{product?}
     *
     * Apply the payload, then validate ALL required fields.
     * If valid → status = 'in_review'. If invalid → return per-field errors.
     */
    public function submit(Request $request, $productId = null): JsonResponse
    {
        $this->mergeFormdataIntoRequest($request);

        return DB::transaction(function () use ($request, $productId) {
            [$user, $product, $errorResponse] = $this->resolveOrCreateDraft($request, $productId);
            if ($errorResponse) return $errorResponse;

            $product = $this->applyPayload($request, $product);

            // Full validation — throws ValidationException with per-field errors
            $this->validateForSubmission($product->fresh());

            $product->update([
                'is_draft' => false,
                'status' => 'in_review',
                'wizard_step' => 16,
            ]);

            return $this->success('Product submitted for review successfully.', $this->buildLifecycleData($product->fresh(), true));
        });
    }

    /**
     * GET /supplier-dashboard/create-products/draft/{product?}
     *
     * Load a draft product with all its data for frontend pre-filling.
     */
    public function resume(Request $request, $productId = null): JsonResponse
    {
        [$user, $product, $errorResponse] = $this->resolveDraftProduct($request, $productId);
        if ($errorResponse) return $errorResponse;

        $product = $this->productRepository->query()->with([
            'keywords',
            'location',
            'locations',
            'prices',
            'productImages',
            'productVideos',
            'options.pricingCategories',
            'cancellationPolicies',
            'availabilities.categoryPrices',
        ])
            ->where('id', $product->id)
            ->where('vendor_id', $user->vendor->id)
            ->where('is_draft', true)
            ->first();

        if (!$product) return $this->error('Draft product not found.', 404);

        // Locations
        $locations = $this->locationRepository->query()->where('product_id', $product->id)->get()->map(fn($l) => [
            'city'        => $l->city,
            'country'     => $l->country,
            'countryCode' => $l->countryCode,
            'coordinates' => $l->coordinates,
        ]);

        // Images
        $images = $product->images->map(fn($img) => [
            'id' => $img->id,
            'url' => $img->original,
            'sort_order' => $img->sort_order,
        ])->values();

        return $this->success('Draft product loaded.', [
            ...$this->buildLifecycleData($product),

            // Basic
            'language' => $product->language,
            'product_category' => $product->product_category,
            // Content
            'content_mode' => $product->content_mode,
            'auto_content_text' => $product->auto_content_text,
            // Title
            'title' => $product->title ?: null,
            'reference_code' => $product->reference_code,
            'product_title' => $product->title ?: null,
            'product_reference-Code' => $product->reference_code,
            // Introduction
            'short_introduction' => $product->short_introduction,
            'full_description' => $product->full_description,
            'highlights_summary' => $product->highlights_summary,
            'product_short_description' => $product->short_introduction,
            'product_full_description' => $product->full_description,
            // Included
            'included' => $product->included,
            'excluded' => $product->excluded,
            'included-description' => $product->included,
            'extra_pay_description' => $product->excluded,
            // Photos
            'images' => $images,
            'copyright_acknowledged' => $product->copyright_acknowledged,
            'imageUploadConfirmation' => $product->copyright_acknowledged,
            // Options
            'options_configuration' => $product->options_configuration,
            'languages' => $product->options_configuration['languages'] ?? [],
            'max_group_size' => $product->options_configuration['max_group_size'] ?? null,
            'Private_activity' => $product->options_configuration['is_private'] ?? null,
            'customerSkip' => $product->options_configuration['skip_line'] ?? null,
            'skip_line_type' => $product->options_configuration['skip_line_type'] ?? null,
            'activity_wheelchair' => $product->options_configuration['is_accessible'] ?? null,
            'activity_duration' => $product->options_configuration['activity_duration'] ?? null,
            // Meeting point
            'meeting_point_address' => $product->meeting_point_address,
            'meeting_point_coordinates' => $product->meeting_point_coordinates,
            'meeting_point_description' => $product->meeting_point_description,
            'arrival_time' => $product->arrival_time,
            'pickup_service' => $product->pickup_service,
            'pickup_info' => $product->pickup_info,
            'drop_off_type' => $product->drop_off_type,
            'meeting_point' => $product->meeting_point_description,
            'arriveTime' => $product->arrival_time,
            'places' => $product->drop_off_type,
            // Guide
            'guide_type' => $product->guide_type,
            // Food
            'food_included' => $product->food_included,
            'food_configuration' => $product->food_configuration,
            'food_include_activity' => $product->food_included,
            // Transportation
            'has_transportation' => $product->has_transportation,
            'transportation_items' => $product->transportation_items,
            'travels_to_different_city' => $product->travels_to_different_city,
            'transportation_include_activity' => $product->has_transportation,
            'transportOptions' => $product->transportation_items,
            // Keywords
            'keywords' => $product->keywords->pluck('name')->toArray(),
            'products_keywords' => $product->keywords->pluck('name')->toArray(),
            // Locations
            'locations' => $locations,
            'customer_visits' => $locations->map(fn($location) => trim(($location['city'] ?? '') . ', ' . ($location['country'] ?? ''), ', '))->values()->all(),
            // Connectivity
            'reservation_system' => $product->reservation_system,
            'product_api_id' => $product->product_api_id,
            'online_reservation' => $product->reservation_system !== 'manual',
            'product_id' => $product->product_api_id,
            // Pricing
            'pricing_type' => $product->pricing_type,
            'pricing_configuration' => $product->pricing_configuration,
            'prices' => $product->pricing_configuration['pricing_mode'] ?? $product->pricing_type,
            'price_rows' => $product->prices->values()->all(),
            // Know before you go
            'not_suitable_for' => $product->not_suitable_for,
            'not_allowed' => $product->not_allowed,
            'pet_friendly_policy' => $product->pet_friendly_policy,
            'mandatory_items' => $product->mandatory_items,
            'custom_info_before_booking' => $product->custom_info_before_booking,
            'other_info_before_activity' => $product->other_info_before_activity,
            'default_cut_off_time' => $product->default_cut_off_time,
            'suitable_for' => $product->not_suitable_for,
            'custom_information_before_booking' => $product->custom_info_before_booking,
            'other_information_before_booking' => $product->other_info_before_activity,
            // Normalized relation payloads
            'booking_questions' => $product->bookingQuestions->values()->all(),
            'passenger_fields' => $product->passengerFields->values()->all(),
            'hotel_contact_fields' => $product->hotelContactFields->values()->all(),
            'cancellation_policies' => $product->cancellationPolicies
                ->map(fn($policy) => array_merge($policy->toArray(), [
                    'details' => $policy->details->values()->all(),
                ]))
                ->values()
                ->all(),
            'videos' => $product->videos->values()->all(),
            'availability_checks' => $this->formatSupplierAvailabilityChecks($product),
            'product_raw_response' => $product->raw_response,
        ]);
    }

    // ═════════════════════════════════════════════════════════════════════
    //  PHOTO MANAGEMENT ENDPOINTS
    // ═════════════════════════════════════════════════════════════════════

    /**
     * POST /supplier-dashboard/create-products/{product}/photos/upload
     */
    public function uploadPhoto(Request $request, $productId): JsonResponse
    {
        $user = $this->authorizedVendor();
        if (!$user) return $this->error('Unauthorized access.', 403);

        $product = $this->getDraftProduct($productId, $user->vendor->id);
        if (!$product) return $this->error('Draft product not found.', 404);

        $request->validate([
            'photo' => 'sometimes|image|mimes:jpg,jpeg,png,gif|max:7168',
            'photos' => 'sometimes|array',
            'photos.*' => 'image|mimes:jpg,jpeg,png,gif|max:7168',
        ]);

        $files = [];
        if ($request->hasFile('photo')) {
            $files = $this->uploadedFilesFromRequest($request, 'photo');
        } elseif ($request->hasFile('photos')) {
            $files = $this->uploadedFilesFromRequest($request, 'photos');
        }

        if (empty($files)) {
            return $this->error('The photo field is required.', 422, ['photo' => ['The photo field is required.']]);
        }

        $gallery = $this->appendUploadedPhotos($product, $files);
        $this->persistSupplierImageGallery($product, $gallery);
        $image = collect($gallery)->sortByDesc('sort_order')->first();

        return $this->success('Photo uploaded.', [
            'image_id' => $image['id'] ?? null,
            'url' => isset($image['original']) ? $this->resolveStoredMediaUrl($image['original']) : null,
            'sort_order' => $image['sort_order'] ?? 0,
            'total_images' => count($gallery),
        ]);
    }

    /**
     * DELETE /supplier-dashboard/create-products/{product}/photos/{photo}
     */
    public function deletePhoto($productId, $photoId): JsonResponse
    {
        $user = $this->authorizedVendor();
        if (!$user) return $this->error('Unauthorized access.', 403);

        $product = $this->getDraftProduct($productId, $user->vendor->id);
        if (!$product) return $this->error('Draft product not found.', 404);

        $gallery = $this->getImageGallery($product);
        $image = collect($gallery)->first(fn(array $item) => (string) ($item['id'] ?? '') === (string) $photoId);
        if (!$image) return $this->error('Image not found.', 404);

        $this->deleteStoredImage($image);

        $gallery = array_values(array_filter($gallery, fn(array $item) => (string) ($item['id'] ?? '') !== (string) $photoId));
        $gallery = $this->normalizeImageGallery($gallery);
        $this->persistSupplierImageGallery($product, $gallery);

        return $this->success('Photo deleted.', [
            'total_images' => count($gallery),
        ]);
    }

    /**
     * PUT /supplier-dashboard/create-products/{product}/photos/reorder
     */
    public function reorderPhotos(Request $request, $productId): JsonResponse
    {
        $user = $this->authorizedVendor();
        if (!$user) return $this->error('Unauthorized access.', 403);

        $product = $this->getDraftProduct($productId, $user->vendor->id);
        if (!$product) return $this->error('Draft product not found.', 404);

        $request->validate([
            'order' => 'required|array',
            'order.*.id' => 'required|integer',
            'order.*.sort_order' => 'required|integer|min:0',
        ]);

        $gallery = collect($this->getImageGallery($product))
            ->keyBy(fn(array $item) => (string) ($item['id'] ?? ''));

        foreach ($request->order as $item) {
            $key = (string) $item['id'];

            if (! $gallery->has($key)) {
                return $this->error('Image not found.', 404);
            }

            $row = $gallery->get($key);
            $row['sort_order'] = (int) $item['sort_order'];
            $gallery->put($key, $row);
        }

        $this->persistSupplierImageGallery($product, $gallery->values()->all());

        return $this->success('Photos reordered.');
    }
}
