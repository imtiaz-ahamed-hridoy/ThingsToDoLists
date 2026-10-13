<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Continuous Full Catalog Product Sync Scheduler
// Checks every 5 minutes: if no sync batch is currently active, it dispatches the next
// round-robin batch of products to the Supervisor 'sync' queue.
// If a batch is already in progress, it cleanly skips to prevent duplicate queueing.
Schedule::command('products:sync-all --chunk=20')
    ->everyFiveMinutes()
    ->withoutOverlapping(30)
    ->runInBackground();

// Sync exchange rates daily
Schedule::command('currency:sync')
    ->daily()
    ->runInBackground();
    // ->appendOutputTo(storage_path('logs/currency-sync.log'));

// Pre-calculate category stats (min price, product count, avg rating) hourly
// This replaces on-the-fly aggregate queries for search/collection/destination pages.
// Cache expires in 2 hours, so hourly refresh ensures freshness.
Schedule::command('category-stats:precalculate')
    ->hourly()
    ->withoutOverlapping(30)
    ->runInBackground();

// Monitor queue sizes — alert admins when any queue exceeds 200 pending jobs
// Runs every 5 minutes so issues are caught quickly but rate-limited to avoid spam.
Schedule::command('queue:monitor-alert --threshold=200')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->runInBackground();

// Prune old job history records (keep 7 days)
Schedule::call(function () {
    \Illuminate\Support\Facades\DB::table('job_histories')
        ->where('processed_at', '<', now()->subDays(7))
        ->delete();
})->daily()->description('Prune job history older than 7 days');

