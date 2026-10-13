<?php

namespace App\Console\Commands;

use App\Jobs\SyncCatalogChunkJob;
use App\Models\Product;
use App\Models\User;
use App\Notifications\AdminSystemNotification;
use App\Services\Provider\ProductSyncFactory;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Syncs all imported products from Bokun and GlobalTix.
 *
 * Runs non-blocking queued batch jobs on the 'sync' queue by default,
 * ensuring no duplicate overlapping runs and full continuous coverage of all products.
 */
class SyncAllProducts extends Command
{
    protected $signature = 'products:sync-all
                            {--platform= : Only sync a specific platform (bokun/globaltix)}
                            {--stale-only : Only sync products older than 3 hours}
                            {--chunk=20 : Process products in chunks of N}
                            {--inline : Run synchronously in the current process instead of dispatching to queue}
                            {--force : Force dispatch even if a previous batch is marked active}';

    protected $description = 'Sync all imported products from Bokun/GlobalTix to refresh prices & availability';

    public function handle(): int
    {
        $allowedPlatforms = ['bokun', 'globaltix'];
        $platform = $this->option('platform')
            ? strtolower(trim((string) $this->option('platform')))
            : null;
        $staleOnly = (bool) $this->option('stale-only');
        $inline = (bool) $this->option('inline');
        $force = (bool) $this->option('force');
        $chunkSize = max(1, min(100, (int) $this->option('chunk') ?: 20));

        if ($platform !== null && ! in_array($platform, $allowedPlatforms, true)) {
            $this->error("Invalid platform [{$platform}]. Use bokun or globaltix.");

            return self::INVALID;
        }

        // Check if an existing queue batch is currently active (prevents stacking & duplicate queueing)
        if (! $inline && ! $force) {
            $activeBatchId = Cache::get('catalog_sync_active_batch_id');
            if ($activeBatchId) {
                try {
                    $activeBatch = Bus::findBatch($activeBatchId);
                    if ($activeBatch && ! $activeBatch->finished()) {
                        $this->info("Catalog sync batch [{$activeBatchId}] is currently running ({$activeBatch->progress()}% complete). Skipping new dispatch to prevent overlap.");

                        return self::SUCCESS;
                    }
                } catch (\Throwable $e) {
                    Cache::forget('catalog_sync_active_batch_id');
                }
            }
        }

        $query = Product::query()
            ->whereNotNull('product_api_id')
            ->where(function ($q) use ($allowedPlatforms) {
                $q->whereIn('platform', $allowedPlatforms)
                    ->orWhereIn('provider', $allowedPlatforms);
            });

        if ($platform !== null) {
            $query->where(function ($q) use ($platform) {
                $q->where('platform', $platform)
                    ->orWhere('provider', $platform);
            });
        }

        if ($staleOnly) {
            $query->where(function ($q) {
                $q->whereNull('last_synced_at')
                    ->orWhere('last_synced_at', '<', now()->subHours(3));
            });
        }

        // Always order by oldest synced first so products are continuously cycled fairly
        $query->orderByRaw('last_synced_at IS NULL DESC, last_synced_at ASC');

        $products = $query->get(['id', 'product_api_id', 'platform', 'provider', 'title', 'last_synced_at']);

        if ($products->isEmpty()) {
            $this->info('No products to sync.');

            return self::SUCCESS;
        }

        $this->info("Found {$products->count()} products to sync.");

        // Mode 1: Dispatch to Supervisor 'sync' Queue (Recommended default)
        if (! $inline) {
            $jobs = [];
            $grouped = $products->groupBy(fn(Product $product) => strtolower(
                (string) ($product->platform ?: $product->provider)
            ));

            foreach ($grouped as $platformName => $platformProducts) {
                if (! in_array($platformName, $allowedPlatforms, true)) {
                    continue;
                }

                $apiIds = $platformProducts->pluck('product_api_id')->filter()->values()->all();
                foreach (array_chunk($apiIds, $chunkSize) as $chunkIds) {
                    $jobs[] = new SyncCatalogChunkJob($platformName, $chunkIds, true);
                }
            }

            if (empty($jobs)) {
                $this->info('No sync jobs generated.');
                return self::SUCCESS;
            }

            $batch = Bus::batch($jobs)
                ->name('Continuous Full Catalog Sync')
                ->onQueue('sync')
                ->allowFailures()
                ->finally(function (Batch $batch) {
                    Cache::forget('catalog_sync_active_batch_id');
                    Log::info("Continuous Full Catalog Sync completed: Batch [{$batch->id}] finished with {$batch->totalJobs} chunks.");
                })
                ->dispatch();

            Cache::put('catalog_sync_active_batch_id', $batch->id, now()->addHours(6));

            $this->info("Successfully dispatched {$products->count()} products in " . count($jobs) . " chunked jobs to the 'sync' queue (Batch ID: {$batch->id}).");

            return self::SUCCESS;
        }

        // Mode 2: Inline synchronous execution (for manual debugging)
        $this->info("Syncing {$products->count()} products inline...");
        $bar = $this->output->createProgressBar($products->count());

        $summary = [
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $grouped = $products->groupBy(fn(Product $product) => strtolower(
            (string) ($product->platform ?: $product->provider)
        ));

        foreach ($grouped as $platformName => $platformProducts) {
            if (! in_array($platformName, $allowedPlatforms, true)) {
                $this->warn("Unknown platform: {$platformName}");
                continue;
            }

            $syncService = ProductSyncFactory::make($platformName);

            foreach ($platformProducts->chunk($chunkSize) as $chunk) {
                foreach ($chunk as $product) {
                    try {
                        $result = $syncService->importOrUpdateByApiId(
                            $platformName,
                            (string) $product->product_api_id,
                            true
                        );

                        if ($result['success'] ?? false) {
                            $summary['updated']++;
                        } else {
                            $summary['failed']++;
                            $summary['errors'][] = $this->formatProductError(
                                $product,
                                $result['message'] ?? 'Unknown error'
                            );
                        }
                    } catch (\Throwable $e) {
                        $summary['failed']++;
                        $summary['errors'][] = $this->formatProductError($product, $e->getMessage());

                        Log::error("SyncAllProducts failed for {$product->product_api_id}", [
                            'platform' => $platformName,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    $bar->advance();
                }

                usleep(100_000);
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Updated: {$summary['updated']}");
        if ($summary['failed'] > 0) {
            $this->warn("Failed: {$summary['failed']}");
            foreach ($summary['errors'] as $error) {
                $this->error("  -> {$error}");
            }
        }

        $admins = User::where('role', 'Admin')->get();
        if ($admins->isNotEmpty()) {
            $statusType = $summary['failed'] > 0 ? 'warning' : 'success';
            $msg = "Scheduled product sync completed. Updated: {$summary['updated']}, Failed: {$summary['failed']}.";
            Notification::send($admins, new AdminSystemNotification('Product Sync Completed', $msg, $statusType));
        }

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function formatProductError(Product $product, string $message): string
    {
        return sprintf(
            '%s (%s): %s',
            $product->title ?: 'Untitled product',
            $product->product_api_id,
            $message
        );
    }
}
