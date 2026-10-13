<?php

namespace App\Jobs;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PrecalculateCategoryStats implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 2;
    public int $backoff = 30;

    /**
     * Pre-calculate and cache statistics for all active categories.
     *
     * Stores: cache key "category:stats:{id}" → array of stats
     * Also stores: cache key "category:stats:updated_at" → timestamp
     *
     * Cached data expires in 2 hours. Schedule this job every hour
     * to ensure fresh data is always available.
     */
    public function handle(): void
    {
        try {
            // Group by category_id to fetch stats for all categories in a single query
            $statsCollection = Product::query()
                ->where('status', 'active')
                ->where('published', true)
                ->whereNotNull('category_id')
                ->selectRaw('category_id')
                ->selectRaw('COUNT(*) as experiences_count')
                ->selectRaw('MIN(from_price) as listing_price')
                ->selectRaw('MIN(COALESCE(original_price, from_price)) as original_listing_price')
                ->selectRaw('SUM(COALESCE(reviewCount, 0)) as ratings_count')
                ->selectRaw('
                    CASE
                        WHEN SUM(COALESCE(reviewCount, 0)) > 0
                        THEN SUM(COALESCE(reviewRating, 0) * COALESCE(reviewCount, 0)) / SUM(COALESCE(reviewCount, 0))
                        ELSE AVG(reviewRating)
                    END as ratings_value
                ')
                ->selectRaw('MIN(NULLIF(default_currency, \'\')) as detected_currency')
                ->groupBy('category_id')
                ->get()
                ->keyBy('category_id');

            $allStats = [];

            foreach ($statsCollection as $categoryId => $stats) {
                $statsArray = [
                    'experiences_count' => (int) ($stats->experiences_count ?? 0),
                    'listing_price' => $stats->listing_price !== null ? (float) $stats->listing_price : null,
                    'original_listing_price' => $stats->original_listing_price !== null ? (float) $stats->original_listing_price : null,
                    'ratings_count' => (int) ($stats->ratings_count ?? 0),
                    'ratings_value' => $stats->ratings_value !== null ? (float) $stats->ratings_value : null,
                    'detected_currency' => $stats->detected_currency ?: 'EUR',
                ];

                Cache::put(
                    "category:stats:{$categoryId}",
                    $statsArray,
                    now()->addHours(2)
                );

                $allStats[$categoryId] = $statsArray;
            }

            if (!empty($allStats)) {
                Cache::put('category:stats:all', $allStats, now()->addHours(2));
            }

            Cache::put('category:stats:updated_at', now()->toIso8601String(), now()->addHours(3));
        } catch (\Throwable $e) {
            Log::error('Failed to pre-calculate stats for categories', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
