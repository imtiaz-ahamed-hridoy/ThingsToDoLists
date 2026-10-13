<?php

namespace App\Services\Bokun;

use App\Services\Contracts\ProductSyncInterface;
use App\Services\Import\ProductImportMapper;
use App\Services\Provider\ProviderFactory;
use Illuminate\Support\Facades\Log;

class BokunProductSyncService implements ProductSyncInterface
{
    public function __construct(
        private readonly ProductImportMapper $importMapper,
    ) {}

    protected function getProviderName(): string
    {
        return 'bokun';
    }

    /**
     * Import multiple products from a selection list of API IDs.
     */
    public function importFromTempSelection(array $productApiIds): array
    {
        $summary = [
            'imported'        => 0,
            'updated'         => 0,
            'errors'          => [],
        ];

        foreach ($productApiIds as $productApiId) {
            $result = $this->importOrUpdateByApiId('bokun', (string) $productApiId, false);

            if (!$result['success']) {
                $summary['errors'][] = "Product {$productApiId} failed: {$result['message']}";
                continue;
            }

            if (($result['status'] ?? '') === 'created') {
                $summary['imported']++;
            } elseif (($result['status'] ?? '') === 'updated') {
                $summary['updated']++;
            }
        }

        return $summary;
    }

    /**
     * Import or update a single product by its API ID.
     */
    public function importOrUpdateByApiId(string $platform, string $productApiId, bool $forceUpdate = false): array
    {
        try {
            $provider = ProviderFactory::make('bokun');
            $rawApiData = $provider->getFullProductDataRaw($productApiId);

            if (empty($rawApiData['data']['activity']) || !is_array($rawApiData['data']['activity'])) {
                $message = $rawApiData['message']
                    ?? data_get($rawApiData, 'error.message')
                    ?? 'Failed to fetch product data from vendor (Bokun).';

                return [
                    'success' => false,
                    'status' => 'failed',
                    'product_api_id' => $productApiId,
                    'message' => $message,
                ];
            }

            $dto = $this->importMapper->normalizeBokunRaw($rawApiData);

            // All products are imported unconditionally — no availability gate.
            $product = $this->importMapper->persistNormalizedProduct($dto, $forceUpdate);

            return [
                'success'        => true,
                'status'         => $product->wasRecentlyCreated ? 'created' : 'updated',
                'product_api_id' => $productApiId,
                'product_id'     => $product->id,
                'title'          => $product->title,
                'message'        => $product->wasRecentlyCreated
                    ? 'Bokun product imported successfully.'
                    : 'Bokun product updated successfully.',
            ];
        } catch (\Throwable $e) {
            $message = substr($e->getMessage(), 0, 2000);

            Log::error('BokunProductSyncService importOrUpdateByApiId failed', [
                'platform' => 'bokun',
                'product_api_id' => $productApiId,
                'message' => $message,
                'trace' => substr($e->getTraceAsString(), 0, 4000),
            ]);

            return [
                'success' => false,
                'status' => 'failed',
                'product_api_id' => $productApiId,
                'message' => $message,
            ];
        }
    }
}
