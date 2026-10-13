<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QueueMonitorController extends Controller
{
    /**
     * Display the queue monitoring dashboard.
     */
    public function index(Request $request): View|JsonResponse
    {
        // Support JSON response for AJAX pending jobs fetch
        if ($request->has('pending_json')) {
            $pendingJobs = DB::table('jobs')->orderBy('id')->limit(100)->get()->map(function ($job) {
                $payload = json_decode($job->payload ?? '{}', true);
                return [
                    'id' => $job->id,
                    'queue' => $job->queue,
                    'display_name' => $payload['displayName'] ?? 'Unknown',
                    'available_at' => $job->available_at,
                ];
            });
            return response()->json(['pendingJobs' => $pendingJobs]);
        }
        // ── Jobs pending per queue ──
        $perQueue = DB::table('jobs')
            ->select('queue', DB::raw('COUNT(*) as total'))
            ->groupBy('queue')
            ->orderBy('queue')
            ->get()
            ->keyBy('queue');

        $queueNames = ['default', 'sync', 'import', 'bulk', 'email'];
        $queueStats = [];
        $totalPending = 0;

        foreach ($queueNames as $name) {
            $count = (int) ($perQueue[$name]->total ?? 0);
            $queueStats[$name] = $count;
            $totalPending += $count;
        }

        // ── Failed jobs summary ──
        $failedCount = DB::table('failed_jobs')->count();

        $recentFailed = DB::table('failed_jobs')
            ->orderBy('failed_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($job) {
                // Decode the payload to extract useful info
                $payload = json_decode($job->payload ?? '{}', true);
                $displayName = $payload['displayName'] ?? 'Unknown';
                $queue = $payload['queue'] ?? 'default';

                // Clean up the display name for readability
                $jobClass = preg_replace('/^([A-Za-z0-9_\\\\]+).*/', '$1', $displayName);

                // Extract exception message (first line only)
                $exception = '';
                if (!empty($job->exception)) {
                    $lines = explode("\n", $job->exception);
                    $exception = $lines[0] ?? '';
                    // Truncate very long messages
                    if (mb_strlen($exception) > 200) {
                        $exception = mb_substr($exception, 0, 200) . '...';
                    }
                }

                return (object) [
                    'id' => $job->id,
                    'queue' => $queue,
                    'job_class' => $jobClass,
                    'exception' => $exception,
                    'failed_at' => $job->failed_at,
                ];
            });

        // ── Queue health indicators ──
        $queueHealth = [];
        foreach ($queueNames as $name) {
            $pending = $queueStats[$name];
            $queueHealth[$name] = $this->getQueueHealth($name, $pending);
        }

        // ── Recent completed jobs (job history) ──
        $recentCompleted = DB::table('job_histories')
            ->where('status', 'processed')
            ->orderBy('processed_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($job) {
                // Clean up the job class name for readability
                $jobClass = preg_replace('/^([A-Za-z0-9_\\\\]+).*/', '$1', $job->job_class);

                return (object) [
                    'id' => $job->id,
                    'queue' => $job->queue,
                    'job_class' => $jobClass,
                    'duration_ms' => $job->duration_ms ? round((float) $job->duration_ms, 1) : null,
                    'processed_at' => $job->processed_at,
                ];
            });

        $totalCompleted = DB::table('job_histories')
            ->where('status', 'processed')
            ->count();

        // ── Aggregate per queue ──
        $recentHistoryPerQueue = DB::table('job_histories')
            ->select('queue', DB::raw('COUNT(*) as total'))
            ->where('status', 'processed')
            ->groupBy('queue')
            ->orderBy('queue')
            ->get()
            ->keyBy('queue');

        $queueProcessedCounts = [];
        foreach ($queueNames as $name) {
            $queueProcessedCounts[$name] = (int) ($recentHistoryPerQueue[$name]->total ?? 0);
        }

        return view('backend.layouts.queue-monitor.index', compact(
            'queueStats',
            'totalPending',
            'failedCount',
            'recentFailed',
            'queueHealth',
            'queueNames',
            'recentCompleted',
            'totalCompleted',
            'queueProcessedCounts'
        ));
    }

    /**
     * Retry a single failed job by ID.
     */
    public function retry(int $id): RedirectResponse
    {
        $exitCode = Artisan::call('queue:retry', ['id' => [$id]]);

        if ($exitCode === 0) {
            return redirect()->route('queue-monitor.index')
                ->with('t-success', "Job #{$id} requeued for retry.");
        }

        return redirect()->route('queue-monitor.index')
            ->with('error', "Failed to retry job #{$id}.");
    }

    /**
     * Retry a batch of selected failed jobs by their IDs.
     */
    public function retrySelected(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'min:1'],
        ]);

        $selectedIds = $request->input('ids');
        $count = count($selectedIds);

        $exitCode = Artisan::call('queue:retry', ['id' => $selectedIds]);

        if ($exitCode === 0) {
            return redirect()->route('queue-monitor.index')
                ->with('t-success', "{$count} selected job(s) requeued for retry.");
        }

        return redirect()->route('queue-monitor.index')
            ->with('error', 'Failed to retry selected jobs.');
    }

    /**
     * Retry all failed jobs.
     */
    public function retryAll(): RedirectResponse
    {
        $exitCode = Artisan::call('queue:retry', ['id' => ['all']]);

        if ($exitCode === 0) {
            return redirect()->route('queue-monitor.index')
                ->with('t-success', 'All failed jobs requeued for retry.');
        }

        return redirect()->route('queue-monitor.index')
            ->with('error', 'Failed to retry all jobs.');
    }

    /**
     * Forget (delete) a single failed job record.
     */
    public function forget(int $id): RedirectResponse
    {
        Artisan::call('queue:forget', ['id' => $id]);

        return redirect()->route('queue-monitor.index')
            ->with('t-success', "Failed job #{$id} record deleted.");
    }

    /**
     * Flush (delete) ALL failed job records.
     */
    public function flushFailed(): RedirectResponse
    {
        Artisan::call('queue:flush');

        return redirect()->route('queue-monitor.index')
            ->with('t-success', 'All failed job records flushed.');
    }

    // ════════════════════════════════════════
    //  QUEUE CONTROL ACTIONS
    // ════════════════════════════════════════

    /**
     * Restart all queue workers (sends SIGTERM to gracefully stop workers).
     */
    public function restartWorkers(): RedirectResponse
    {
        Artisan::call('queue:restart');

        return redirect()->route('queue-monitor.index')
            ->with('t-success', 'Queue restart signal sent. Workers will gracefully stop and be restarted by supervisor.');
    }

    /**
     * Clear ALL pending jobs from the jobs table.
     */
    public function clearAllQueues(): RedirectResponse
    {
        DB::table('jobs')->truncate();

        return redirect()->route('queue-monitor.index')
            ->with('t-success', 'All pending jobs cleared from all queues.');
    }

    /**
     * Clear pending jobs for a specific queue.
     */
    public function clearQueue(string $queue): RedirectResponse
    {
        $allowed = ['default', 'sync', 'import', 'bulk', 'email'];
        if (!in_array($queue, $allowed, true)) {
            return redirect()->route('queue-monitor.index')
                ->with('error', "Invalid queue name: {$queue}");
        }

        $deleted = DB::table('jobs')->where('queue', $queue)->delete();

        return redirect()->route('queue-monitor.index')
            ->with('t-success', "Cleared {$deleted} pending job(s) from the '{$queue}' queue.");
    }

    /**
     * Prune old job history records (older than 7 days).
     */
    public function pruneHistory(): RedirectResponse
    {
        $cutoff = now()->subDays(7);

        $deleted = DB::table('job_histories')
            ->where('processed_at', '<', $cutoff)
            ->delete();

        return redirect()->route('queue-monitor.index')
            ->with('t-success', "Pruned {$deleted} old job history record(s) older than 7 days.");
    }

    /**
     * Cancel/destroy specific pending jobs by their IDs from the jobs table.
     */
    public function cancelPending(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'min:1'],
        ]);

        $ids = $request->input('ids');
        $count = DB::table('jobs')->whereIn('id', $ids)->delete();

        return redirect()->route('queue-monitor.index')
            ->with('t-success', "Cancelled and removed {$count} pending job(s).");
    }

    /**
     * Determine health status of a queue based on pending jobs.
     */
    private function getQueueHealth(string $queue, int $pending): array
    {
        return match (true) {
            $pending > 500 => ['status' => 'critical', 'label' => 'Critical — Over 500 pending', 'color' => 'danger'],
            $pending > 100 => ['status' => 'warning', 'label' => "Warning — {$pending} pending", 'color' => 'warning'],
            $pending > 0   => ['status' => 'active', 'label' => "Active — {$pending} pending", 'color' => 'info'],
            default        => ['status' => 'idle', 'label' => 'Idle — No pending jobs', 'color' => 'success'],
        };
    }
}
