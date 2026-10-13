<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\AdminSystemNotification;
use App\Services\Bokun\BokunService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class QueueBokunActiveActivitiesImportJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly int $chunkSize = 10
    ) {
        $this->onQueue('sync');
    }

    /**
     * Prevent duplicate full Bokun catalog syncs from running at the same time.
     * If a full sync is already queued/running, subsequent dispatches are dropped.
     */
    public function uniqueId(): string
    {
        return 'bokun_active_activities_import';
    }

    /** Lock expires after 10 minutes (same as timeout) to ensure cleanup on crash. */
    public int $uniqueFor = 600;

    public function handle(BokunService $bokunService): void
    {
        $response = $bokunService->getActiveActivityIds();
        $activityIds = [];

        foreach (($response['suppliers'] ?? []) as $supplier) {
            if (! empty($supplier['activityIds']) && is_array($supplier['activityIds'])) {
                $activityIds = array_merge($activityIds, $supplier['activityIds']);
            }
        }

        $activityIds = array_values(array_unique(array_filter(array_map(
            fn ($id) => trim((string) $id),
            $activityIds
        ))));

        if (empty($activityIds)) {
            Log::error('QueueBokunActiveActivitiesImportJob: No activity IDs returned from Bokun.');
            return;
        }

        $jobs = [];
        foreach (array_chunk($activityIds, max(1, $this->chunkSize)) as $chunk) {
            $jobs[] = new ImportProviderProductIdsChunkJob('bokun', $chunk);
        }

        if (!empty($jobs)) {
            $totalJobs = count($jobs);
            $dispatchedCount = 0;
            $failedCount = 0;

            foreach ($jobs as $job) {
                try {
                    dispatch($job->onQueue('import'));
                    $dispatchedCount++;
                } catch (\Throwable $e) {
                    $failedCount++;
                    Log::error('Failed to dispatch Bokun import job.', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Bokun Active Activities Import completed', [
                'dispatched' => $dispatchedCount,
                'failed' => $failedCount,
            ]);

            $admins = User::where('role', 'Admin')->get();
            if ($admins->isNotEmpty()) {
                $msg = "Bokun/GlobalTix Sync Completed\n\n"
                    . "Dispatched: {$dispatchedCount}\n"
                    . "Failed: {$failedCount}\n\n"
                    . "Total products to process: {$totalJobs}";

                Notification::send($admins, new AdminSystemNotification(
                    'Bokun Sync Summary ✅',
                    $msg,
                    $failedCount > 0 ? 'warning' : 'success'
                ));
            }
        }


    }
}
