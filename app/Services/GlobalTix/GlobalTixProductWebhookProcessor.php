<?php

namespace App\Services\GlobalTix;

use App\Models\AvailabilityCategoryPrice;
use App\Models\AvailabilityCheck;
use App\Models\Location;
use App\Models\OptionPricingCategory;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\TempProduct;

class GlobalTixProductWebhookProcessor
{
    public const EVENT_PRODUCT_INFO_UPDATE = 'PRODUCT_INFO_UPDATE';
    public const EVENT_PRICE_UPDATE = 'PRICE_UPDATE';

    public function process(string $eventType, array $data): array
    {
        return match (strtoupper($eventType)) {
            self::EVENT_PRODUCT_INFO_UPDATE => $this->handleProductInfoUpdate($data),
            self::EVENT_PRICE_UPDATE => $this->handlePriceUpdate($data),
            default => $this->summary(
                skipped: 1,
                result: ['reason' => "Unsupported GlobalTix product webhook event: {$eventType}"],
            ),
        };
    }

    public function handleProductInfoUpdate(array $data): array
    {
        $productId = trim((string) ($data['productId'] ?? ''));
        if ($productId === '') {
            return $this->summary(
                skipped: 1,
                result: ['reason' => 'Missing productId for PRODUCT_INFO_UPDATE'],
            );
        }

        $title = trim((string) ($data['name'] ?? ''));
        $countryCode = trim((string) ($data['countryCode'] ?? ''));
        $cityId = trim((string) ($data['cityId'] ?? ''));

        $updated = 0;
        $result = [
            'productId' => $productId,
        ];

        if ($title !== '') {
            $tempUpdated = TempProduct::where('product_api_id', $productId)
                ->where('api_platform', 'globaltix')
                ->update(['title' => $title]);

            $productUpdated = Product::where('product_api_id', $productId)
                ->where('provider', 'globaltix')
                ->update([
                    'title' => $title,
                    'last_synced_at' => now(),
                ]);

            $updated += $tempUpdated + $productUpdated;
            $result['title'] = $title;
            $result['temp_products_updated'] = $tempUpdated;
            $result['products_updated'] = $productUpdated;
        }

        if ($countryCode !== '' || $cityId !== '') {
            $productIds = Product::where('product_api_id', $productId)
                ->where('provider', 'globaltix')
                ->pluck('id');

            if ($productIds->isNotEmpty()) {
                $locationUpdates = [];
                if ($countryCode !== '') {
                    $locationUpdates['countryCode'] = strtoupper($countryCode);
                }
                if ($cityId !== '') {
                    $locationUpdates['city_id'] = $cityId;
                }

                if ($locationUpdates !== []) {
                    $locationUpdated = Location::whereIn('product_id', $productIds)->update($locationUpdates);
                    $updated += $locationUpdated;
                    $result['locations_updated'] = $locationUpdated;
                }
            }
        }

        if ($updated === 0) {
            return $this->summary(
                skipped: 1,
                result: array_merge($result, ['reason' => 'No matching product records updated']),
            );
        }

        return $this->summary(
            processed: $updated,
            updated: $updated,
            result: $result,
        );
    }

    public function handlePriceUpdate(array $data): array
    {
        $productId = trim((string) ($data['productId'] ?? ''));
        $newNettPrice = $data['newNettPrice'] ?? null;

        if ($productId === '' || ! is_numeric($newNettPrice)) {
            return $this->summary(
                skipped: 1,
                result: ['reason' => 'Missing productId or numeric newNettPrice for PRICE_UPDATE'],
            );
        }

        $optionExternalId = trim((string) ($data['optionId'] ?? ''));
        $ticketTypeExternalId = trim((string) ($data['ticketTypeId'] ?? ''));
        $currency = strtoupper(trim((string) ($data['currency'] ?? '')));
        $nettPrice = round((float) $newNettPrice, 2);

        $productIds = Product::where('provider', 'globaltix')
            ->where('product_api_id', $productId)
            ->pluck('id');

        if ($productIds->isEmpty()) {
            return $this->summary(
                skipped: 1,
                result: ['reason' => 'No matching Product rows found', 'productId' => $productId],
            );
        }

        $optionIds = collect();
        if ($optionExternalId !== '') {
            $optionIds = ProductOption::whereIn('product_id', $productIds)
                ->where('provider', 'globaltix')
                ->where('external_id', $optionExternalId)
                ->pluck('id');

            if ($optionIds->isEmpty()) {
                return $this->summary(
                    skipped: 1,
                    result: [
                        'reason' => 'No matching ProductOption rows found for optionId',
                        'productId' => $productId,
                        'optionId' => $optionExternalId,
                    ],
                );
            }
        }

        $categoryIds = collect();
        if ($ticketTypeExternalId !== '') {
            $categoryQuery = OptionPricingCategory::where('external_id', $ticketTypeExternalId);
            if ($optionIds->isNotEmpty()) {
                $categoryQuery->whereIn('product_option_id', $optionIds);
            } else {
                $categoryQuery->whereIn(
                    'product_option_id',
                    ProductOption::whereIn('product_id', $productIds)
                        ->where('provider', 'globaltix')
                        ->select('id')
                );
            }

            $categoryIds = $categoryQuery->pluck('id');

            if ($categoryIds->isEmpty()) {
                return $this->summary(
                    skipped: 1,
                    result: [
                        'reason' => 'No matching pricing category found for ticketTypeId',
                        'productId' => $productId,
                        'ticketTypeId' => $ticketTypeExternalId,
                    ],
                );
            }
        }

        $priceUpdate = ['nett_price' => $nettPrice];
        if ($currency !== '') {
            $priceUpdate['currency'] = $currency;
        }

        $priceQuery = Price::where('provider', 'globaltix')
            ->whereIn('product_id', $productIds);

        if ($optionIds->isNotEmpty()) {
            $priceQuery->whereIn('product_option_id', $optionIds);
        }
        if ($categoryIds->isNotEmpty()) {
            $priceQuery->whereIn('pricing_category_id', $categoryIds);
        }

        $updatedPriceRows = $priceQuery->update($priceUpdate);

        $availabilityPriceUpdate = ['nett_price' => $nettPrice];
        if ($currency !== '') {
            $availabilityPriceUpdate['currency'] = $currency;
        }

        $updatedAvailabilityCategoryRows = 0;
        if ($categoryIds->isNotEmpty()) {
            $updatedAvailabilityCategoryRows = AvailabilityCategoryPrice::whereIn('pricing_category_id', $categoryIds)
                ->update($availabilityPriceUpdate);
        } else {
            $availabilityQuery = AvailabilityCheck::whereIn('product_id', $productIds)
                ->where('provider', 'globaltix');

            if ($optionIds->isNotEmpty()) {
                $availabilityQuery->whereIn('product_option_id', $optionIds);
            }

            $availabilityIds = $availabilityQuery->pluck('id');
            if ($availabilityIds->isNotEmpty()) {
                $updatedAvailabilityCategoryRows = AvailabilityCategoryPrice::whereIn('availability_id', $availabilityIds)
                    ->update($availabilityPriceUpdate);
            }
        }

        $totalUpdated = $updatedPriceRows + $updatedAvailabilityCategoryRows;
        if ($totalUpdated === 0) {
            return $this->summary(
                skipped: 1,
                result: [
                    'reason' => 'No matching price rows found to update',
                    'productId' => $productId,
                    'optionId' => $optionExternalId ?: null,
                    'ticketTypeId' => $ticketTypeExternalId ?: null,
                ],
            );
        }

        return $this->summary(
            processed: $totalUpdated,
            updated: $totalUpdated,
            result: [
                'productId' => $productId,
                'optionId' => $optionExternalId ?: null,
                'ticketTypeId' => $ticketTypeExternalId ?: null,
                'newNettPrice' => $nettPrice,
                'currency' => $currency ?: null,
                'price_rows_updated' => $updatedPriceRows,
                'availability_category_rows_updated' => $updatedAvailabilityCategoryRows,
            ],
        );
    }

    private function summary(
        int $processed = 0,
        int $created = 0,
        int $updated = 0,
        int $skipped = 0,
        int $failed = 0,
        array $result = []
    ): array {
        return [
            'processed' => max(0, $processed),
            'created' => max(0, $created),
            'updated' => max(0, $updated),
            'skipped' => max(0, $skipped),
            'failed' => max(0, $failed),
            'result' => $result,
        ];
    }
}
