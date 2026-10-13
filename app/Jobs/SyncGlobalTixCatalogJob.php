<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\AdminSystemNotification;
use App\Services\GlobalTix\GlobalTixProductService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SyncGlobalTixCatalogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly int $chunkSize = 10
    ) {
    }

    public function handle(GlobalTixProductService $productService): void
    {
        try {
            $response = $productService->getNewProducts($this->startDate, $this->endDate);
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            Log::error('SyncGlobalTixCatalogJob: Failed to fetch new products from GlobalTix API', [
                'start_date' => $this->startDate,
                'end_date' => $this->endDate,
                'message' => $message,
            ]);

            // If credentials are failing (403/401), release immediately with no retry
            if (str_contains($message, '403') || str_contains($message, '401') || str_contains($message, 'token')) {
                $this->fail($e);
                return;
            }

            throw $e;
        }

        $productIds = $productService->extractProductIdsFromResponse($response);
        $productIds = array_values(array_unique(array_filter(array_map(
            fn ($id) => trim((string) $id),
            $productIds
        ))));

        $jobs = [];
        foreach (array_chunk($productIds, max(1, $this->chunkSize)) as $chunk) {
            $jobs[] = new SyncGlobalTixTempProductsChunkJob($chunk);
        }

        if (!empty($jobs)) {
            $totalJobs = count($jobs);
            $dispatchedCount = 0;
            $failedCount = 0;

            foreach ($jobs as $job) {
                try {
                    dispatch($job);
                    $dispatchedCount++;
                } catch (\Throwable $e) {
                    $failedCount++;
                    Log::error('Failed to dispatch GlobalTix chunk job.', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('GlobalTix Catalog Sync completed', [
                'dispatched' => $dispatchedCount,
                'failed' => $failedCount,
            ]);

            $admins = User::where('role', 'Admin')->get();
            if ($admins->isNotEmpty()) {
                $failedThisRun = $failedCount;
                $imported = $dispatchedCount;
                $msg = "GlobalTix Sync Completed\n\nImported: {$imported}\nFailed: {$failedThisRun}\n\nTotal products to process: {$totalJobs}";
                Notification::send($admins, new AdminSystemNotification(
                    'GlobalTix Sync Summary',
                    $msg,
                    $failedCount > 0 ? 'warning' : 'success'
                ));
            }
        }


    }
}
