<?php

namespace App\Services\Import;

use App\DTOs\SyncedProductDTO;
use App\Models\Product;
use App\Models\TempProduct;
use App\Services\Provider\ProviderFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class TempProductImportService
{
    public function __construct(
        private readonly ProductImportMapper $importMapper,
    ) {}

    public function bulkImport(array $rawIds): array
    {
        $ids = collect($rawIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        /** @var Collection<int, TempProduct> $items */
        $items = TempProduct::whereIn('id', $ids->all())->get();

        if ($items->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No matching products found to import.',
                'status_code' => 404,
            ];
        }

        $summary = [
            'success' => true,
            'message' => '',
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'skipped_products' => [],
            'errors' => [],
        ];

        $successfulTempIds = [];

        foreach ($items as $item) {
            $result = $this->importTempProduct($item);

            match ($result['status']) {
                'created' => $summary['imported']++,
                'updated' => $summary['updated']++,
                'skipped' => $summary['skipped']++,
                default => $summary['failed']++,
            };

            if (($result['status'] ?? null) === 'created' || ($result['status'] ?? null) === 'updated') {
                $successfulTempIds[] = $item->id;
                continue;
            }

            if (($result['status'] ?? null) === 'skipped') {
                $summary['skipped_products'][] = [
                    'id' => $item->product_api_id,
                    'title' => $item->title ?? 'Unknown',
                    'reason' => $result['message'] ?? 'Skipped',
                ];
                continue;
            }

            $summary['errors'][] = sprintf(
                '%s [%s] failed: %s',
                strtoupper($result['provider'] ?? $this->normalizeProviderName($item->api_platform)),
                (string) $item->product_api_id,
                $result['message'] ?? 'Unknown error'
            );
        }

        if ($successfulTempIds !== []) {
            TempProduct::whereIn('id', array_values(array_unique($successfulTempIds)))->delete();
        }

        $message = "{$summary['imported']} product(s) imported successfully.";
        if ($summary['updated'] > 0) {
            $message .= " {$summary['updated']} product(s) updated.";
        }
        if ($summary['skipped'] > 0) {
            $message .= " {$summary['skipped']} product(s) skipped.";
        }
        if ($summary['failed'] > 0) {
            $message .= " {$summary['failed']} product(s) failed.";
        }

        $summary['message'] = $message;

        return $summary;
    }

    private function importTempProduct(TempProduct $tempProduct): array
    {
        $provider = $this->normalizeProviderName($tempProduct->api_platform);
        $productApiId = (string) $tempProduct->product_api_id;

        try {
            $rawPayload = $this->resolveRawPayload($tempProduct, $provider, $productApiId);
            $dto = $this->normalizePayload($provider, $rawPayload);

            if ($dto->productApiId === '') {
                throw new \RuntimeException('Provider payload is missing a product ID.');
            }

            $existing = Product::query()
                ->where('product_api_id', $dto->productApiId)
                ->where('platform', $provider)
                ->first();

            // Availability check intentionally removed:
            // Products are imported unconditionally regardless of availability status.
            $product = $this->importMapper->persistNormalizedProduct($dto, false);

            return [
                'success' => true,
                'status' => $existing ? 'updated' : 'created',
                'provider' => $provider,
                'product_id' => $product->id,
                'message' => $existing
                    ? ucfirst($provider) . ' product updated successfully.'
                    : ucfirst($provider) . ' product imported successfully.',
            ];
        } catch (\Throwable $e) {
            $message = substr($e->getMessage(), 0, 2000);

            Log::error('Temp product import failed', [
                'provider' => $provider,
                'temp_product_id' => $tempProduct->id,
                'product_api_id' => $productApiId,
                'message' => $message,
            ]);

            $this->markImportResult($tempProduct, 'failed', $message);

            return [
                'success' => false,
                'status' => 'failed',
                'provider' => $provider,
                'message' => $message,
            ];
        }
    }

    private function resolveRawPayload(TempProduct $tempProduct, string $provider, string $productApiId): array
    {
        $rawData = is_array($tempProduct->raw_data) ? $tempProduct->raw_data : [];

        if ($this->hasCompleteRawPayload($provider, $rawData)) {
            return $rawData;
        }

        $providerService = ProviderFactory::make($provider);
        $payload = $providerService->getFullProductDataRaw($productApiId);

        if (!$this->hasCompleteRawPayload($provider, $payload)) {
            $message = $payload['message']
                ?? data_get($payload, 'error.message')
                ?? 'Provider did not return a complete raw payload.';

            throw new \RuntimeException($message);
        }

        return $payload;
    }

    private function normalizePayload(string $provider, array $payload): SyncedProductDTO
    {
        return match ($provider) {
            'bokun' => $this->importMapper->normalizeBokunRaw($payload),
            'globaltix' => $this->importMapper->normalizeGlobalTixRaw($payload),
            default => throw new \InvalidArgumentException("Unsupported provider [{$provider}]"),
        };
    }

    private function hasCompleteRawPayload(string $provider, array $payload): bool
    {
        // Availability data is NOT required — products import regardless of availability.
        return match ($provider) {
            'bokun'     => isset($payload['data']['activity']) && is_array($payload['data']['activity'])
                        && isset($payload['data']['priceList']) && is_array($payload['data']['priceList']),
            'globaltix' => isset($payload['data']['activity']['data']) && is_array($payload['data']['activity']['data'])
                        && isset($payload['data']['activityOptions']['data']) && is_array($payload['data']['activityOptions']['data']),
            default     => false,
        };
    }

    private function normalizeProviderName(?string $provider): string
    {
        return match (strtolower(trim((string) $provider))) {
            'bokun' => 'bokun',
            'globaltix' => 'globaltix',
            default => throw new \InvalidArgumentException('Unsupported temp product provider.'),
        };
    }

    private function markImportResult(TempProduct $tempProduct, string $status, string $reason): void
    {
        $tempProduct->forceFill([
            'api_platform' => $this->normalizeProviderName($tempProduct->api_platform),
            'last_import_status' => $status,
            'last_import_reason' => $reason,
            'last_import_attempted_at' => now(),
        ])->save();
    }
}
