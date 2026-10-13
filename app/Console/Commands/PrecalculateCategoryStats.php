<?php

namespace App\Console\Commands;

use App\Jobs\PrecalculateCategoryStats as StatsJob;
use Illuminate\Console\Command;

class PrecalculateCategoryStats extends Command
{
    protected $signature = 'category-stats:precalculate';
    protected $description = 'Pre-calculate and cache statistics (price, count, rating) for all active categories';

    /**
     * Execute the console command.
     *
     * Dispatches a background job that computes aggregate stats
     * for each category and stores them in Redis cache.
     * Schedule this to run hourly: Schedule::command('category-stats:precalculate')->hourly()
     */
    public function handle(): int
    {
        $this->info('Dispatching PrecalculateCategoryStats job...');

        StatsJob::dispatch();

        $this->info('PrecalculateCategoryStats job dispatched successfully.');

        return self::SUCCESS;
    }
}
