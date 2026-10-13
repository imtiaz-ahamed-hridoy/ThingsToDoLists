<?php

namespace App\Jobs;

use App\Services\Provider\ProductSyncFactory;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncCatalogChunkJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 2;
    public int $backoff = 30;

    /**
     * @param string $platform
     * @param array<int, string> $productApiIds
     * @param bool $forceUpdate
     */
    public function __construct(
        public readonly string $platform,
        public readonly array $productApiIds,
        public readonly bool $forceUpdate = true
    ) {
        $this->onQueue('sync');
    }

    public function handle(): void
    {
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        $platform = strtolower(trim($this->platform));
        $syncService = ProductSyncFactory::make($platform);

        $successCount = 0;
        $failureCount = 0;

        foreach ($this->productApiIds as $rawId) {
            $productApiId = trim((string) $rawId);
            if ($productApiId === '') {
                continue;
            }

            try {
                $result = $syncService->importOrUpdateByApiId($platform, $productApiId, $this->forceUpdate);

                if ($result['success'] ?? false) {
                    $successCount++;
                } else {
                    $failureCount++;
                }
            } catch (\Throwable $e) {
                $failureCount++;
                Log::error("SyncCatalogChunkJob: Exception syncing product {$platform}:{$productApiId}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
