<?php

namespace App\Jobs;

use App\Services\Bokun\TempProductService;
use App\Services\Provider\ProductSyncFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class QueueTempProductSearchJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;
    public int $tries = 3;
    public int $backoff = 30;


    public function __construct(
        public readonly array $criteria
    ) {
        $this->onQueue('import');
    }

    public function uniqueId(): string
    {
        return md5(json_encode($this->criteria));
    }

    public function handle(TempProductService $tempProductService): void
    {
        $criteria = $this->criteria;
        $provider = strtolower((string) ($criteria['api_platform'] ?? 'bokun'));
        $productApiId = trim((string) ($criteria['product_api_id'] ?? ''));

        if ($productApiId !== '') {
            $syncService = ProductSyncFactory::make($provider);
            
            $apiIds = array_map('trim', explode(',', $productApiId));
            foreach ($apiIds as $id) {
                if ($id !== '') {
                    try {
                        $result = $syncService->importOrUpdateByApiId($provider, $id, (bool) ($criteria['force_update'] ?? false));
                        Log::info('Product import job result', [
                            'provider'        => $provider,
                            'product_api_id'  => $id,
                            'status'          => $result['status'] ?? 'unknown',
                            'product_id'      => $result['product_id'] ?? null,
                            'message'         => $result['message'] ?? null,
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('Product import job failed for ID', [
                            'provider'       => $provider,
                            'product_api_id' => $id,
                            'error'          => $e->getMessage(),
                        ]);
                    }
                }
            }

            return;
        }

        $result = $tempProductService->fetchAndStore($criteria);
    }
}
