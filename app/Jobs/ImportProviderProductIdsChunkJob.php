<?php

namespace App\Jobs;

use App\Services\Provider\ProductSyncFactory;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportProviderProductIdsChunkJob implements ShouldQueue, ShouldBeUnique
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200;
    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly string $provider,
        public readonly array $productApiIds,
        public readonly bool $forceUpdate = false
    ) {
        $this->onQueue('import');
    }

    public function uniqueId(): string
    {
        return $this->provider . '_' . md5(json_encode($this->productApiIds));
    }

    public function handle(): void
    {
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        $provider = $this->normalizeProvider($this->provider);
        $syncService = ProductSyncFactory::make($provider);

        $summary = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($this->productApiIds as $rawId) {
            $productApiId = trim((string) $rawId);

            if ($productApiId === '') {
                $summary['skipped']++;
                continue;
            }

            try {
                $result = $syncService->importOrUpdateByApiId($provider, $productApiId, $this->forceUpdate);

                if (! ($result['success'] ?? false)) {
                    $summary['failed']++;
                    Log::error('Queued provider product import failed', [
                        'provider' => $provider,
                        'product_api_id' => $productApiId,
                        'message' => $result['message'] ?? 'Unknown failure',
                    ]);
                    continue;
                }

                match ($result['status'] ?? 'skipped') {
                    'created' => $summary['created']++,
                    'updated' => $summary['updated']++,
                    default => $summary['skipped']++,
                };
            } catch (\Throwable $e) {
                $summary['failed']++;

                Log::error('Queued provider product import threw exception', [
                    'provider' => $provider,
                    'product_api_id' => $productApiId,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($this->batch()) {
            $batchId = $this->batch()->id;
            $imported = $summary['created'] + $summary['updated'];
            if ($imported > 0) \Illuminate\Support\Facades\Cache::increment("batch_{$batchId}_imported", $imported);
            if ($summary['updated'] > 0) \Illuminate\Support\Facades\Cache::increment("batch_{$batchId}_updated", $summary['updated']);
            if ($summary['skipped'] > 0) \Illuminate\Support\Facades\Cache::increment("batch_{$batchId}_skipped", $summary['skipped']);
            if ($summary['failed'] > 0) \Illuminate\Support\Facades\Cache::increment("batch_{$batchId}_failed", $summary['failed']);
        }

    }

    private function normalizeProvider(string $provider): string
    {
        return match (strtolower(trim($provider))) {
            'bokun' => 'bokun',
            'globaltix' => 'globaltix',
            default => throw new \InvalidArgumentException("Unsupported provider [{$provider}]."),
        };
    }
}
