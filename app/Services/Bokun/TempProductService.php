<?php

namespace App\Services\Bokun;


use Illuminate\Support\Facades\Log;
use App\Models\{Product, TempProduct};

class TempProductService
{
    public function fetchAndStore(array $params): array
    {
        try {
            // -------------------------------
            // 1. Extract API Params
            // -------------------------------
            $platform = $params['api_platform'] ?? 'bokun';
            $language = $params['language'] ?? 'EN';
            $currency = $params['currency'] ?? 'EUR';
            $page     = (int) ($params['page'] ?? 1);
            $pageSize = (int) ($params['size'] ?? 10);

            // -------------------------------
            // 2. Search Filters
            // -------------------------------
            $filters = [];
            if (strtolower($platform) === 'globaltix') {
                if (!empty($params['countryCode'])) {
                    $filters['countryCode'] = strtoupper((string) $params['countryCode']);
                }

                if (!empty($params['city'])) {
                    // GlobalTix /api/product/list expects cityIds.
                    $filters['cityIds'] = (string) $params['city'];
                }

                if (!empty($params['textSearch'])) {
                    // GlobalTix docs: searchText
                    $filters['searchText'] = (string) $params['textSearch'];
                }

                if (!empty($params['categoryIds'])) {
                    $filters['categoryIds'] = (string) $params['categoryIds'];
                }

                if (!empty($params['pageSize'])) {
                    $filters['pageSize'] = (int) $params['pageSize'];
                }
            } else {
                // Bokun: Use the new Clean Architecture ActivitySearchService
                $dto = \App\DTOs\Bokun\ActivitySearchDTO::fromRequest($params);
                
                $client = new \App\Services\Bokun\BokunClient(new \App\Services\Bokun\BokunService());
                $repository = new \App\Repositories\Bokun\ActivitySearchRepository($client);
                $geocodingService = new \App\Services\Bokun\GeocodingService();
                
                $searchService = new \App\Services\Bokun\ActivitySearchService($repository, $geocodingService);
                
                $activities = $searchService->search($dto);
            }

            // -------------------------------
            // 3. Execute Unified Search (GlobalTix only - Bokun already handled above)
            // -------------------------------
            // Only GlobalTix reaches here because Bokun's else block already executed the search
            if (strtolower($platform) === 'globaltix') {
                $provider = \App\Services\Provider\ProviderFactory::make($platform);
                $activities = $provider->searchActivities([
                    'language' => $language,
                    'currency' => $currency,
                    'page' => $page,
                    'pageSize' => $pageSize,
                    'filters' => $filters
                ]);
            }

            if (empty($activities['items'])) {
                Log::warning('TempProductService::fetchAndStore - no activities found from provider', [
                    'platform' => $platform,
                    'provider_response' => $activities,
                ]);

                return [
                    'status'  => true,
                    'created' => 0,
                    'skipped' => 0,
                    'total'   => 0,
                    'items'   => [],
                    'message' => 'No activities found.',
                ];
            }

            // -------------------------------
            // 4. Metrics
            // -------------------------------
            $created = 0;
            $skipped = 0;
            $storedItems = [];

            // -------------------------------
            // 5. Persist Data (no availability check — deferred to bulk import)
            // -------------------------------
            foreach ($activities['items'] as $item) {

                if (empty($item['id'])) {
                    $skipped++;
                    continue;
                }

                if (Product::where('product_api_id', $item['id'])->where('platform', strtolower($platform))->exists()) {
                    $skipped++;
                    continue;
                }

                $city = $item['googlePlace']['city'] ?? $item['places'][0]['location']['city'] ?? $item['city'] ?? 'N/A';
                $country = $item['googlePlace']['country'] ?? $item['places'][0]['location']['countryCode'] ?? $item['country'] ?? null;
                $currency = $item['currency'] ?? $item['currencyCode'] ?? 'EUR';

                $resolvedPrice = 0;
                if (!empty($item['ticketType']) || !empty($item['tickets']) || !empty($item['activityOptions'])) {
                    $tickets = $item['ticketType'] ?? $item['tickets'] ?? [];
                    if (empty($tickets) && !empty($item['activityOptions'])) {
                        foreach ($item['activityOptions'] as $opt) {
                            if (!empty($opt['ticketType'])) {
                                $tickets = array_merge($tickets, $opt['ticketType']);
                            }
                        }
                    }
                    if (!empty($tickets)) {
                        $resolved = \App\Services\Sync\PriceCalculator::resolveProductDisplayPrices($tickets, null, null, $country, $city);
                        $resolvedPrice = (float) ($resolved['from_price'] ?? 0);
                    }
                }
                if ($resolvedPrice <= 0) {
                    $rawPrice = (float) ($item['price'] ?? $item['fromPrice'] ?? $item['originalPrice'] ?? 0);
                    if ($rawPrice > 0) {
                        $cs = app(\App\Services\CurrencyService::class);
                        $converted = $cs->convertToEur($rawPrice, $currency, $country, $city);
                        $resolvedPrice = ($converted !== null && $converted > 0) ? $converted : $rawPrice;
                    }
                }

                $temp = TempProduct::updateOrCreate(
                    [
                        'product_api_id' => $item['id'],
                        'api_platform' => strtolower($platform),
                    ],
                    [
                        'title'        => $item['title'] ?? null,
                        'slug'         => $item['slug'] ?? null,
                        'vendor_name'  => $item['vendor']['title'] ?? null,
                        'price'        => $resolvedPrice,
                        'rating'       => $item['rating'] ?? 0,
                        'review_count' => $item['reviewCount'] ?? 0,
                        'city'         => $city,
                        'thumbnail'    => $item['keyPhoto']['originalUrl'] ?? null,
                        'raw_data'     => $item,
                        'status'       => 'pending',
                        'last_import_status' => null,
                        'last_import_reason' => null,
                        'last_import_attempted_at' => null,
                    ]
                );

                $storedItems[] = $temp;
                $created++;
            }

            // -------------------------------
            // 6. Return Metrics
            // -------------------------------
            $totalFound = $activities['totalCount'] ?? count($activities['items']);
            $message = "Search completed. Found {$totalFound} activities. {$created} saved to temp products.";

            return [
                'status'   => true,
                'created'  => $created,
                'skipped'  => $skipped,
                'total'    => $totalFound,
                'items'    => $storedItems,
                'message'  => $message,
            ];
        } catch (\Throwable $e) {

            Log::error('TempProductService::fetchAndStore failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status'  => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ];
        }
    }

    public function fetchActivityDetails(string $platform, int|string $id, string $language = 'EN', string $currency = 'EUR'): array
    {
        try {
            $provider = \App\Services\Provider\ProviderFactory::make($platform);
            return $provider->getFullProductData($id, $language, $currency);
        } catch (\Throwable $e) {
            return [];
        }
    }
}
