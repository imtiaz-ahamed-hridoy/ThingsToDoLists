<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\TempProduct;
use App\Services\GlobalTix\GlobalTixProductService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncGlobalTixTempProductsChunkJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200;
    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly array $productApiIds
    ) {
    }

    public function handle(GlobalTixProductService $productService): void
    {
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        $createdOrUpdated = 0;
        $skipped = 0;
        $failed = 0;

        $existingProductIds = Product::query()
            ->whereIn('product_api_id', $this->productApiIds)
            ->where(function ($q) {
                $q->where('platform', 'globaltix')
                  ->orWhere('provider', 'globaltix');
            })
            ->pluck('product_api_id')
            ->map(fn($id) => (string)$id)
            ->toArray();

        foreach ($this->productApiIds as $rawId) {
            $productApiId = trim((string) $rawId);

            if ($productApiId === '' || in_array($productApiId, $existingProductIds)) {
                $skipped++;
                continue;
            }

            try {
                $infoResponse = $productService->getProductInfo($productApiId);
                $fullInfo = is_array($infoResponse['data'] ?? null) ? $infoResponse['data'] : [];

                if ($fullInfo === []) {
                    $failed++;

                    continue;
                }

                $thumbnail = null;
                if (! empty($fullInfo['image'])) {
                    $thumbnail = $productService->getImage($fullInfo['image']);
                } elseif (! empty($fullInfo['media'][0]['name'])) {
                    $thumbnail = $fullInfo['media'][0]['name'];
                }

                $city = $fullInfo['city'] ?? $fullInfo['cityName'] ?? $fullInfo['destination'] ?? 'N/A';
                $country = $fullInfo['country'] ?? $fullInfo['countryCode'] ?? null;
                $currency = $fullInfo['currency'] ?? $fullInfo['currencyCode'] ?? 'EUR';

                $resolvedPrice = 0;
                if (!empty($fullInfo['ticketType']) || !empty($fullInfo['tickets']) || !empty($fullInfo['activityOptions'])) {
                    $tickets = $fullInfo['ticketType'] ?? $fullInfo['tickets'] ?? [];
                    if (empty($tickets) && !empty($fullInfo['activityOptions'])) {
                        foreach ($fullInfo['activityOptions'] as $opt) {
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
                    $rawPrice = (float) ($fullInfo['fromPrice'] ?? $fullInfo['originalPrice'] ?? 0);
                    if ($rawPrice > 0) {
                        $cs = app(\App\Services\CurrencyService::class);
                        $converted = $cs->convertToEur($rawPrice, $currency, $country, $city);
                        $resolvedPrice = ($converted !== null && $converted > 0) ? $converted : $rawPrice;
                    }
                }

                TempProduct::updateOrCreate(
                    [
                        'product_api_id' => $productApiId,
                        'api_platform' => 'globaltix',
                    ],
                    [
                        'title' => $fullInfo['name'] ?? $fullInfo['title'] ?? 'Untitled',
                        'description' => $fullInfo['description'] ?? null,
                        'price' => $resolvedPrice,
                        'city' => $city,
                        'thumbnail' => $thumbnail,
                        'status' => 'pending',
                        'raw_data' => $fullInfo,
                        'last_import_status' => null,
                        'last_import_reason' => null,
                        'last_import_attempted_at' => null,
                    ]
                );

                $createdOrUpdated++;
            } catch (\Throwable $e) {
                $failed++;

                Log::error('GlobalTix temp sync product info failed', [
                    'product_api_id' => $productApiId,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($this->batch()) {
            $batchId = $this->batch()->id;
            if ($createdOrUpdated > 0) \Illuminate\Support\Facades\Cache::increment("batch_{$batchId}_imported", $createdOrUpdated);
            if ($skipped > 0) \Illuminate\Support\Facades\Cache::increment("batch_{$batchId}_skipped", $skipped);
            if ($failed > 0) \Illuminate\Support\Facades\Cache::increment("batch_{$batchId}_failed", $failed);
        }
    }
}
