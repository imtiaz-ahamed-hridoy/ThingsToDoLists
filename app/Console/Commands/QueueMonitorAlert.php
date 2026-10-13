<?php

namespace App\Console\Commands;

use App\Jobs\SendCustomerEmailJob;
use App\Models\User;
use App\Notifications\AdminSystemNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class QueueMonitorAlert extends Command
{
    protected $signature = 'queue:monitor-alert
                            {--threshold=200 : Alert when any queue exceeds this many pending jobs}';

    protected $description = 'Check queue sizes and alert admins if any queue exceeds the threshold.';

    public function handle(): int
    {
        $threshold = (int) $this->option('threshold');
        $queueNames = ['default', 'sync', 'import', 'bulk', 'email'];

        // ── Query pending jobs per queue ──
        $perQueue = DB::table('jobs')
            ->select('queue', DB::raw('COUNT(*) as total'))
            ->groupBy('queue')
            ->get()
            ->keyBy('queue');

        $overThreshold = [];
        $totalPending = 0;
        $details = [];

        foreach ($queueNames as $name) {
            $count = (int) ($perQueue[$name]->total ?? 0);
            $totalPending += $count;
            $details[] = "{$name}: {$count}";

            if ($count > $threshold) {
                $overThreshold[] = "{$name}: {$count} pending (threshold: {$threshold})";
            }
        }

        // ── Log current state (debug level to avoid log spam — runs every 5 min) ──
        $stateLine = 'Queue state: ' . implode(', ', $details);
        $this->line($stateLine);
        Log::debug("[QueueMonitorAlert] {$stateLine}");

        // ── Alert if any queue exceeds threshold ──
        if (!empty($overThreshold)) {
            $alertLines = implode("\n- ", array_merge(
                ['⚠️ Queue Alert — queues above threshold:'],
                $overThreshold
            ));

            $this->warn($alertLines);

            $admins = User::where('role', 'Admin')->get();

            if ($admins->isNotEmpty()) {
                $title = '🚨 Queue Alert — ' . count($overThreshold) . ' queue(s) over ' . $threshold;
                $message = implode("\n", $overThreshold)
                    . "\n\nTotal pending across all queues: {$totalPending}"
                    . "\n\nThreshold: {$threshold} jobs per queue"
                    . "\n\nTip: Check /admin/queue-monitor for details.\n"
                    . "Run: php artisan queue:work database --queue=<queue_name> --timeout=600";

                // ── Database notification (appears in admin notification bell) ──
                Notification::send($admins, new AdminSystemNotification(
                    $title,
                    $message,
                    'warning'
                ));

                // ── Direct email alert (reaches admin even if they're not in the panel) ──
                foreach ($admins as $admin) {
                    $recipientEmail = (!empty($admin->email) && $admin->email !== 'admin@admin.com')
                        ? $admin->email
                        : 'alerts@example.com';

                    try {
                        \Illuminate\Support\Facades\Mail::to($recipientEmail)->send(
                            new \App\Mail\CustomUserMail(
                                '🚨 Queue Alert — Action Required',
                                nl2br(e("{$title}\n\n{$message}"))
                            )
                        );
                    } catch (\Throwable $mailException) {
                        Log::error("[QueueMonitorAlert] Failed to send email alert to {$recipientEmail}: " . $mailException->getMessage());
                    }
                }

                $this->info('✓ Admin notification sent (database + email).');
                Log::warning("[QueueMonitorAlert] Alert sent — queues over threshold: " . implode(', ', $overThreshold));
            }

            return self::FAILURE;
        }

        $this->info("✓ All queues below threshold ({$threshold}). Total pending: {$totalPending}");
        // Only log healthy state at debug level to avoid 288 log lines/day
        Log::debug("[QueueMonitorAlert] All queues healthy. Total pending: {$totalPending}");

        return self::SUCCESS;
    }
}
