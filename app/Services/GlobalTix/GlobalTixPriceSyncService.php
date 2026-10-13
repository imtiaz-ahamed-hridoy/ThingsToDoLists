<?php

namespace App\Services\GlobalTix;

use App\Models\Product;
use App\Models\TempProduct;
use App\Services\CurrencyService;
use App\Services\Sync\PriceCalculator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GlobalTixPriceSyncService
{
    public function __construct(
        protected GlobalTixProductService $productService,
        protected CurrencyService $currencyService
    ) {}

    /**
     * Normalize a ticket label/code string with spacing, handling camelCase, underscores, hyphens, and whitespace.
     */
    public function normalizeString(?string $str): string
    {
        if ($str === null || $str === '') {
            return '';
        }

        // Convert camelCase or PascalCase to spaced words: "PerPax" -> "Per Pax", "ChildTicket" -> "Child Ticket"
        $withSpaces = preg_replace('/(?<!^)([A-Z][a-z]|(?<=[a-z])[A-Z])/', ' $1', $str);

        // Replace common delimiters with spaces
        $clean = str_replace(['_', '-', '/', '\\', '|', ':', '.'], ' ', (string) $withSpaces);

        // Collapse multiple whitespace into a single space and trim
        $collapsed = trim(preg_replace('/\s+/', ' ', $clean));

        return strtoupper($collapsed);
    }

    /**
     * Check if a ticket type or category code represents an ADULT type.
     * First priority is checking exact uppercase "ADULT", then normalized representations.
     */
    public function isAdultType(?string $label, ?string $code = null, ?string $nameTranslated = null): bool
    {
        $labelStr = trim((string) $label);
        $codeStr  = trim((string) $code);
        $transStr = trim((string) $nameTranslated);

        // 1. Direct uppercase exact match (top priority)
        if ($labelStr === 'ADULT' || $transStr === 'ADULT' || $codeStr === 'ADULT') {
            return true;
        }

        // 2. Normalized check with spacing and case insensitivity
        foreach ([$labelStr, $transStr, $codeStr] as $val) {
            if ($val === '') {
                continue;
            }
            $normalized = $this->normalizeString($val);
            if (
                $normalized === 'ADULT'
                || str_starts_with($normalized, 'ADULT ')
                || str_ends_with($normalized, ' ADULT')
                || str_contains($normalized, ' ADULT ')
                || $normalized === 'DEWASA'
                || str_contains($normalized, 'DEWASA')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a ticket type represents a PER PAX / Per Person type.
     */
    public function isPerPaxType(?string $label, ?string $code = null, ?string $nameTranslated = null): bool
    {
        $labelStr = trim((string) $label);
        $codeStr  = trim((string) $code);
        $transStr = trim((string) $nameTranslated);

        // 1. Direct uppercase exact match
        if (
            $labelStr === 'PER PAX' || $transStr === 'PER PAX' || $codeStr === 'PER PAX'
            || $labelStr === 'PER_PAX' || $transStr === 'PER_PAX' || $codeStr === 'PER_PAX'
        ) {
            return true;
        }

        // 2. Normalized check with spacing and case insensitivity
        foreach ([$labelStr, $transStr, $codeStr] as $val) {
            if ($val === '') {
                continue;
            }
            $normalized = $this->normalizeString($val);
            if (
                $normalized === 'PER PAX'
                || $normalized === 'PER PERSON'
                || $normalized === 'PAX'
                || $normalized === 'PERSON'
                || $normalized === 'PERPAX'
                || str_contains($normalized, 'PER PAX')
                || str_contains($normalized, 'PER PERSON')
                || str_contains($normalized, 'PERPAX')
                || str_starts_with($normalized, 'PAX ')
                || str_ends_with($normalized, ' PAX')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a ticket type represents a CHILD or INFANT type.
     */
    public function isChildOrInfantType(?string $label, ?string $code = null, ?string $nameTranslated = null): bool
    {
        $labelStr = trim((string) $label);
        $codeStr  = trim((string) $code);
        $transStr = trim((string) $nameTranslated);

        // 1. Direct uppercase exact match
        if (
            $labelStr === 'CHILD' || $transStr === 'CHILD' || $codeStr === 'CHILD'
            || $labelStr === 'INFANT' || $transStr === 'INFANT' || $codeStr === 'INFANT'
        ) {
            return true;
        }

        // 2. Normalized check with spacing and case insensitivity
        $keywords = [
            'CHILD', 'CHILDREN', 'KID', 'KIDS', 'NINO', 'NINOS', 'NIÑO', 'NIÑOS', 'CRIANCA', 'CRIANÇA',
            'ENFANT', 'KIND', 'KINDER', 'BAMBINO',
            'INFANT', 'INFANTS', 'BABY', 'BABIES', 'TODDLER', 'TODDLERS', 'BEBE', 'BÉBÉ',
        ];

        foreach ([$labelStr, $transStr, $codeStr] as $val) {
            if ($val === '') {
                continue;
            }
            $normalized = $this->normalizeString($val);
            foreach ($keywords as $kw) {
                if ($normalized === $kw || str_contains($normalized, $kw)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Helper to detect non-adult types.
     */
    private function isChildOrNonAdultType(string $str): bool
    {
        if ($str === '') {
            return false;
        }

        $nonAdultKeywords = [
            'child', 'kid', 'nino', 'niño', 'criança', 'enfant', 'kind',
            'infant', 'baby', 'toddler',
            'youth', 'teen', 'junior',
            'senior', 'elder', 'pensioner',
            'student',
        ];

        foreach ($nonAdultKeywords as $kw) {
            if (str_contains($str, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract selling price from any ticket/slot structure, converted to EUR.
     * Checks all possible GlobalTix and application price fields.
     */
    public function resolveTicketSellingPrice(array|object $ticket, ?string $country = null, ?string $city = null): ?float
    {
        $arr = is_array($ticket) ? $ticket : (is_object($ticket) && method_exists($ticket, 'toArray') ? $ticket->toArray() : (array) $ticket);

        $selling = PriceCalculator::firstNumeric([
            $arr['recommendedSellingPrice'] ?? null,
            $arr['selling_price'] ?? null,
            $arr['sellingPrice'] ?? null,
            $arr['recommendedMerchantSellingPrice'] ?? null,
            $arr['merchantSellingPrice'] ?? null,
            $arr['price'] ?? (is_array($arr['price'] ?? null) ? ($arr['price']['amount'] ?? null) : null),
            $arr['amount'] ?? null,
            $arr['from_price'] ?? null,
            $arr['fromPrice'] ?? null,
            $arr['minimumSellingPrice'] ?? null,
            $arr['minimumMerchantSellingPrice'] ?? null,
            $arr['nettPrice'] ?? null,
            $arr['nett_price'] ?? null,
            $arr['nettMerchantPrice'] ?? null,
            $arr['directContractPrice'] ?? null,
            $arr['directMerchantContractPrice'] ?? null,
        ]);

        if ($selling === null || $selling <= 0) {
            return null;
        }

        $currency = strtoupper((string) (
            $arr['currency']
            ?? (is_array($arr['price'] ?? null) ? ($arr['price']['currency'] ?? 'EUR') : 'EUR')
        ));
        $itemCountry = $arr['country'] ?? $country;
        $itemCity = $arr['city'] ?? $city;

        // Convert to EUR if IDR or unscaled
        if ($currency === 'IDR' || $selling > 150) {
            $converted = $this->currencyService->convertToEur($selling, $currency, $itemCountry, $itemCity);
            if ($converted !== null && $converted > 0) {
                return $converted;
            }
        }

        return (float) $selling;
    }

    /**
     * Extract original/recommended price from any ticket/slot structure, converted to EUR.
     */
    public function resolveTicketOriginalPrice(array|object $ticket, ?string $country = null, ?string $city = null, ?float $sellingPrice = null): ?float
    {
        $arr = is_array($ticket) ? $ticket : (is_object($ticket) && method_exists($ticket, 'toArray') ? $ticket->toArray() : (array) $ticket);

        $orig = PriceCalculator::firstNumeric([
            $arr['originalPrice'] ?? null,
            $arr['original_price'] ?? null,
            $arr['originalMerchantPrice'] ?? null,
            $arr['recommendedPrice'] ?? null,
            $arr['recommended_price'] ?? null,
            $arr['recommendedSellingPrice'] ?? null,
            $arr['recommendedMerchantSellingPrice'] ?? null,
            $arr['selling_price'] ?? null,
            $arr['sellingPrice'] ?? null,
        ]);

        if ($orig === null || $orig <= 0) {
            return $sellingPrice;
        }

        $currency = strtoupper((string) (
            $arr['currency']
            ?? (is_array($arr['price'] ?? null) ? ($arr['price']['currency'] ?? 'EUR') : 'EUR')
        ));
        $itemCountry = $arr['country'] ?? $country;
        $itemCity = $arr['city'] ?? $city;

        if ($currency === 'IDR' || $orig > 150) {
            $converted = $this->currencyService->convertToEur($orig, $currency, $itemCountry, $itemCity);
            if ($converted !== null && $converted > 0) {
                $orig = $converted;
            }
        }

        if ($sellingPrice !== null && $orig < $sellingPrice) {
            $orig = $sellingPrice;
        }

        return (float) $orig;
    }

    /**
     * Core calculation hierarchy:
     * 1. Check for ADULT ticket types -> pick lowest adult recommendedSellingPrice.
     * 2. If NO adult tickets exist, check for PER PAX -> pick lowest PER PAX price.
     * 3. If NO PER PAX exist, check for CHILD and INFANT -> pick lowest among them.
     * 4. If NONE of the above exist, check ALL remaining ticket types and pick the single lowest price among them.
     * 5. Fallback to candidate root fromPrice / originalPrice.
     *
     * @param iterable $ticketsOrSlots
     * @param float|null $fallbackFromPrice
     * @param float|null $fallbackOriginalPrice
     * @param string|null $country
     * @param string|null $city
     * @return array{from_price: ?float, original_price: ?float}
     */
    public function resolveFromPrice(
        iterable $ticketsOrSlots,
        ?float $fallbackFromPrice = null,
        ?float $fallbackOriginalPrice = null,
        ?string $country = null,
        ?string $city = null
    ): array {
        $adultCandidates = [];
        $perPaxCandidates = [];
        $childInfantCandidates = [];
        $otherCandidates = [];

        foreach ($ticketsOrSlots as $item) {
            $arr = is_array($item) ? $item : (is_object($item) && method_exists($item, 'toArray') ? $item->toArray() : (array) $item);

            $sellingPrice = $this->resolveTicketSellingPrice($arr, $country, $city);
            if ($sellingPrice === null || $sellingPrice <= 0) {
                continue;
            }

            $originalPrice = $this->resolveTicketOriginalPrice($arr, $country, $city, $sellingPrice);

            $name = (string) (
                $arr['name']
                ?? $arr['title']
                ?? $arr['ticket_type_name']
                ?? $arr['ticketTypeName']
                ?? $arr['ticket_name']
                ?? $arr['ticketName']
                ?? ''
            );

            $nameTranslated = (string) (
                $arr['nameTranslated']
                ?? $arr['name_translated']
                ?? ''
            );

            $code = (string) (
                $arr['category_code']
                ?? $arr['categoryCode']
                ?? $arr['ticketCategory']
                ?? $arr['ticket_category']
                ?? $arr['type']
                ?? $arr['rate_code']
                ?? $arr['rateCode']
                ?? $arr['category_label']
                ?? $arr['categoryLabel']
                ?? ''
            );

            $candidate = [
                'selling_price'  => $sellingPrice,
                'original_price' => $originalPrice ?? $sellingPrice,
            ];

            if ($this->isAdultType($name, $code, $nameTranslated)) {
                $adultCandidates[] = $candidate;
            } elseif ($this->isPerPaxType($name, $code, $nameTranslated)) {
                $perPaxCandidates[] = $candidate;
            } elseif ($this->isChildOrInfantType($name, $code, $nameTranslated)) {
                $childInfantCandidates[] = $candidate;
            } else {
                $otherCandidates[] = $candidate;
            }
        }

        // 1. Priority 1: ADULT lowest price priority
        if (!empty($adultCandidates)) {
            usort($adultCandidates, fn($a, $b) => $a['selling_price'] <=> $b['selling_price']);
            return [
                'from_price'     => $adultCandidates[0]['selling_price'],
                'original_price' => $adultCandidates[0]['original_price'],
            ];
        }

        // 2. Priority 2: PER PAX lowest price priority
        if (!empty($perPaxCandidates)) {
            usort($perPaxCandidates, fn($a, $b) => $a['selling_price'] <=> $b['selling_price']);
            return [
                'from_price'     => $perPaxCandidates[0]['selling_price'],
                'original_price' => $perPaxCandidates[0]['original_price'],
            ];
        }

        // 3. Priority 3: CHILD & INFANT lowest price priority
        if (!empty($childInfantCandidates)) {
            usort($childInfantCandidates, fn($a, $b) => $a['selling_price'] <=> $b['selling_price']);
            return [
                'from_price'     => $childInfantCandidates[0]['selling_price'],
                'original_price' => $childInfantCandidates[0]['original_price'],
            ];
        }

        // 4. Priority 4: If NONE of the above exist, check ALL other ticket types and take the single lowest price among them
        if (!empty($otherCandidates)) {
            usort($otherCandidates, fn($a, $b) => $a['selling_price'] <=> $b['selling_price']);
            return [
                'from_price'     => $otherCandidates[0]['selling_price'],
                'original_price' => $otherCandidates[0]['original_price'],
            ];
        }

        // 3. Fallback to candidate root fromPrice / originalPrice
        $from = ($fallbackFromPrice !== null && $fallbackFromPrice > 0) ? (float) $fallbackFromPrice : null;
        $orig = ($fallbackOriginalPrice !== null && $fallbackOriginalPrice > 0) ? (float) $fallbackOriginalPrice : $from;

        if ($from !== null && $from > 0) {
            $convertedFrom = $this->currencyService->convertToEur($from, 'EUR', $country, $city);
            if ($convertedFrom !== null && $convertedFrom > 0) {
                $from = $convertedFrom;
            }
        }

        if ($orig !== null && $orig > 0) {
            $convertedOrig = $this->currencyService->convertToEur($orig, 'EUR', $country, $city);
            if ($convertedOrig !== null && $convertedOrig > 0) {
                $orig = $convertedOrig;
            }
        }

        if ($from === null && $orig !== null && $orig > 0) {
            $from = $orig;
        }

        if ($orig === null && $from !== null && $from > 0) {
            $orig = $from;
        }

        // Clamp extreme multipliers
        if ($from !== null && $orig !== null && $orig > ($from * 5)) {
            $orig = $from;
        }

        return [
            'from_price'     => $from,
            'original_price' => $orig ?? $from,
        ];
    }

    /**
     * Extract all ticket type records from GlobalTix options payload or product info.
     */
    public function extractTicketsFromOptions(array $optionsData): array
    {
        $tickets = [];
        $optionsList = array_is_list($optionsData) ? $optionsData : [$optionsData];

        foreach ($optionsList as $opt) {
            if (!is_array($opt)) continue;

            // If this item is already a ticket type record itself
            if (!empty($opt['name']) || !empty($opt['nameTranslated']) || isset($opt['recommendedSellingPrice'])) {
                if (!isset($opt['ticketType'])) {
                    $tickets[] = $opt;
                    continue;
                }
            }

            foreach (($opt['ticketType'] ?? []) as $t) {
                if (is_array($t)) {
                    $tickets[] = $t;
                }
            }
        }

        return $tickets;
    }

    /**
     * Synchronize a saved database Product with live GlobalTix prices.
     * If a price mismatch is found, auto-updates the database record in EUR.
     *
     * @param Product|int|string $productOrId
     * @param array|null $prefetchedOptions
     * @param array|null $prefetchedAvailability
     * @return array{from_price: ?float, original_price: ?float, updated: bool}
     */
    public function syncProductLivePrice(
        Product|int|string $productOrId,
        ?array $prefetchedOptions = null,
        ?array $prefetchedAvailability = null
    ): array {
        $product = is_object($productOrId) && $productOrId instanceof Product
            ? $productOrId
            : Product::with(['options.pricingCategories', 'location'])->find($productOrId);

        if (!$product) {
            return ['from_price' => null, 'original_price' => null, 'updated' => false];
        }

        $provider = strtolower((string) ($product->provider ?: $product->platform ?: ''));
        if ($provider !== 'globaltix') {
            return [
                'from_price'     => (float) ($product->from_price ?? 0),
                'original_price' => (float) ($product->original_price ?? $product->from_price ?? 0),
                'updated'        => false,
            ];
        }

        $apiId = (string) $product->product_api_id;
        if ($apiId === '') {
            return [
                'from_price'     => (float) ($product->from_price ?? 0),
                'original_price' => (float) ($product->original_price ?? 0),
                'updated'        => false,
            ];
        }

        $tickets = [];

        // 1. Use pre-fetched availability slots if available
        if (!empty($prefetchedAvailability)) {
            $tickets = array_merge($tickets, $prefetchedAvailability);
        }

        // 2. Use pre-fetched options or fetch live options from GlobalTix
        if (!empty($prefetchedOptions)) {
            $tickets = array_merge($tickets, $this->extractTicketsFromOptions($prefetchedOptions));
        } else {
            try {
                $optionsRes = $this->productService->getProductOptions($apiId, false, 'en', 'EUR');
                $optionsData = $optionsRes['data'] ?? [];

                // If isDynamicPrice=false returned empty data, retry with isDynamicPrice=true
                if (empty($optionsData)) {
                    $optionsRes = $this->productService->getProductOptions($apiId, true, 'en', 'EUR');
                    $optionsData = $optionsRes['data'] ?? [];
                }

                if (!empty($optionsData)) {
                    $tickets = array_merge($tickets, $this->extractTicketsFromOptions($optionsData));
                }
            } catch (\Throwable $e) {
                Log::warning("GlobalTixPriceSyncService: Failed to fetch live options for product {$apiId}: " . $e->getMessage());
            }
        }

        // 3. If still empty, check existing DB pricing categories
        if (empty($tickets) && $product->relationLoaded('options')) {
            foreach ($product->options as $opt) {
                foreach ($opt->pricingCategories as $cat) {
                    $tickets[] = $cat->toArray();
                }
            }
        }

        $country = $product->location?->country;
        $city = $product->location?->city;

        $resolved = $this->resolveFromPrice(
            $tickets,
            $product->getRawOriginal('from_price') ? (float) $product->getRawOriginal('from_price') : null,
            $product->getRawOriginal('original_price') ? (float) $product->getRawOriginal('original_price') : null,
            $country,
            $city
        );

        $newFromPrice = $resolved['from_price'];
        $newOrigPrice = $resolved['original_price'] ?? $newFromPrice;
        $currentFromPrice = $product->getRawOriginal('from_price') !== null ? (float) $product->getRawOriginal('from_price') : null;
        $currentOrigPrice = $product->getRawOriginal('original_price') !== null ? (float) $product->getRawOriginal('original_price') : null;

        $mismatch = false;
        if ($newFromPrice !== null && $newFromPrice > 0) {
            if ($currentFromPrice === null || abs($currentFromPrice - $newFromPrice) > 0.01) {
                $mismatch = true;
            }
            if ($newOrigPrice !== null && ($currentOrigPrice === null || abs($currentOrigPrice - $newOrigPrice) > 0.01)) {
                $mismatch = true;
            }
        }

        if ($mismatch && $newFromPrice !== null && $newFromPrice > 0) {
            $product->update([
                'from_price'     => $newFromPrice,
                'original_price' => $newOrigPrice,
            ]);

            // Invalidate cached product details
            Cache::forget('product_detail:' . $product->id);
            Cache::forget('product_detail:' . $apiId . '|globaltix');
            Cache::forget('product_detail:' . $apiId . '|bokun');

            return [
                'from_price'     => $newFromPrice,
                'original_price' => $newOrigPrice,
                'updated'        => true,
            ];
        }

        return [
            'from_price'     => $newFromPrice ?? $currentFromPrice,
            'original_price' => $newOrigPrice ?? $currentOrigPrice,
            'updated'        => false,
        ];
    }

    /**
     * Synchronize a TempProduct with live GlobalTix prices.
     * Auto-updates TempProduct::price in EUR on mismatch.
     *
     * @param TempProduct $tempProduct
     * @param array|null $liveOptions
     * @return array{price: ?float, updated: bool}
     */
    public function syncTempProductPrice(TempProduct $tempProduct, ?array $liveOptions = null): array
    {
        $platform = strtolower((string) ($tempProduct->api_platform ?? 'globaltix'));
        if ($platform !== 'globaltix') {
            return [
                'price'   => (float) ($tempProduct->price ?? 0),
                'updated' => false,
            ];
        }

        $tickets = [];

        if (!empty($liveOptions)) {
            $tickets = $this->extractTicketsFromOptions($liveOptions);
        } else {
            // Check raw_data on TempProduct
            $rawData = $tempProduct->raw_data ?? [];
            if (!empty($rawData['ticketType'])) {
                $tickets = $rawData['ticketType'];
            } elseif (!empty($rawData['activityOptions'])) {
                $tickets = $this->extractTicketsFromOptions($rawData['activityOptions']);
            }
        }

        $country = $tempProduct->raw_data['country'] ?? $tempProduct->raw_data['countryCode'] ?? null;
        $city = $tempProduct->city ?? $tempProduct->raw_data['city'] ?? null;

        $rawFallback = (float) (
            $tempProduct->raw_data['fromPrice']
            ?? $tempProduct->raw_data['originalPrice']
            ?? $tempProduct->raw_data['price']
            ?? 0
        );

        $resolved = $this->resolveFromPrice(
            $tickets,
            $rawFallback > 0 ? $rawFallback : null,
            $rawFallback > 0 ? $rawFallback : null,
            $country,
            $city
        );

        $calcPrice = $resolved['from_price'];
        $currentPrice = (float) ($tempProduct->price ?? 0);

        if ($calcPrice !== null && $calcPrice > 0 && abs($currentPrice - $calcPrice) > 0.01) {
            $tempProduct->update(['price' => $calcPrice]);
            return [
                'price'   => $calcPrice,
                'updated' => true,
            ];
        }

        return [
            'price'   => $calcPrice ?? $currentPrice,
            'updated' => false,
        ];
    }

    /**
     * Synchronize product price directly from normalized availability slots.
     */
    public function syncFromAvailabilitySlots(Product $product, array $slots): array
    {
        $candidates = [];

        foreach ($slots as $slot) {
            if (!empty($slot['categories']) && is_array($slot['categories'])) {
                foreach ($slot['categories'] as $cat) {
                    $candidates[] = $cat;
                }
            } else {
                $candidates[] = $slot;
            }
        }

        $country = $product->location?->country;
        $city = $product->location?->city;

        $resolved = $this->resolveFromPrice(
            $candidates,
            $product->getRawOriginal('from_price') ? (float) $product->getRawOriginal('from_price') : null,
            $product->getRawOriginal('original_price') ? (float) $product->getRawOriginal('original_price') : null,
            $country,
            $city
        );

        $newFromPrice = $resolved['from_price'];
        $newOrigPrice = $resolved['original_price'] ?? $newFromPrice;
        $currentFromPrice = $product->getRawOriginal('from_price') !== null ? (float) $product->getRawOriginal('from_price') : null;

        if ($newFromPrice !== null && $newFromPrice > 0 && ($currentFromPrice === null || abs($currentFromPrice - $newFromPrice) > 0.01)) {
            $product->update([
                'from_price'     => $newFromPrice,
                'original_price' => $newOrigPrice,
            ]);

            Cache::forget('product_detail:' . $product->id);
            Cache::forget('product_detail:' . $product->product_api_id . '|globaltix');
            Cache::forget('product_detail:' . $product->product_api_id . '|bokun');

            return [
                'from_price'     => $newFromPrice,
                'original_price' => $newOrigPrice,
                'updated'        => true,
            ];
        }

        return [
            'from_price'     => $newFromPrice ?? $currentFromPrice,
            'original_price' => $newOrigPrice ?? $product->getRawOriginal('original_price'),
            'updated'        => false,
        ];
    }
}
