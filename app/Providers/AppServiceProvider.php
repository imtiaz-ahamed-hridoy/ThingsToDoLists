<?php

namespace App\Providers;

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\UserRepository;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

use App\Models\SystemSetting;
use App\Models\HomePageBanner;
use App\Models\HomePageSection;
use App\Models\HomePageSectionLocation;
use App\Models\BlogPost;
use App\Models\BlogCategory;
use App\Models\Testimonial;
use App\Models\DestinationHighlight;
use App\Models\Currency;
use App\Models\City;
use App\Models\Faq;
use App\Models\DynamicPage;
use App\Models\Product;
use App\Models\Categories;

use App\Observers\SystemSettingObserver;
use App\Observers\HomePageBannerObserver;
use App\Observers\HomePageSectionObserver;
use App\Observers\HomePageSectionLocationObserver;
use App\Observers\BlogPostObserver;
use App\Observers\BlogCategoryObserver;
use App\Observers\TestimonialObserver;
use App\Observers\DestinationHighlightObserver;
use App\Observers\CurrencyObserver;
use App\Observers\CityObserver;
use App\Observers\FaqObserver;
use App\Observers\DynamicPageObserver;
use App\Observers\ProductObserver;
use App\Observers\CategoriesObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * In-memory tracker for job start times, keyed by job ID.
     * Avoids PHP 8.2+ dynamic property deprecation on queue job objects.
     */
    private static array $jobStartTimes = [];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Deprecated service removed
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        $this->bootQueueHistory();
        $this->registerObservers();
    }

    /**
     * Register model observers.
     */
    private function registerObservers(): void
    {
        SystemSetting::observe(SystemSettingObserver::class);
        HomePageBanner::observe(HomePageBannerObserver::class);
        HomePageSection::observe(HomePageSectionObserver::class);
        HomePageSectionLocation::observe(HomePageSectionLocationObserver::class);
        BlogPost::observe(BlogPostObserver::class);
        BlogCategory::observe(BlogCategoryObserver::class);
        Testimonial::observe(TestimonialObserver::class);
        DestinationHighlight::observe(DestinationHighlightObserver::class);
        Currency::observe(CurrencyObserver::class);
        City::observe(CityObserver::class);
        Faq::observe(FaqObserver::class);
        DynamicPage::observe(DynamicPageObserver::class);
        Product::observe(ProductObserver::class);
        Categories::observe(CategoriesObserver::class);
    }

    /**
     * Listen for queue events to persist job history for the Queue Monitor.
     */
    private function bootQueueHistory(): void
    {
        // Track job start time (using static array to avoid dynamic property deprecation)
        Queue::before(function (JobProcessing $event) {
            $jobId = $event->job->getJobId();
            self::$jobStartTimes[$jobId] = microtime(true);
        });

        // Log successful job completions
        Queue::after(function (JobProcessed $event) {
            $jobId = $event->job->getJobId();
            $startedAt = self::$jobStartTimes[$jobId] ?? null;
            unset(self::$jobStartTimes[$jobId]);
            $duration = $startedAt ? (microtime(true) - $startedAt) * 1000 : null;
            $payload = $event->job->payload();
            $displayName = $this->resolveJobDisplayName($payload, $event->job);

            try {
                DB::table('job_histories')->insert([
                    'queue' => $event->job->getQueue(),
                    'job_class' => $displayName,
                    'status' => 'processed',
                    'duration_ms' => $duration !== null ? round($duration, 2) : null,
                    'error_message' => null,
                    'processed_at' => now(),
                ]);
            } catch (\Throwable $e) {
                // Silent fail — logging history should never break the app
                logger()->warning('Failed to persist job history (processed): ' . $e->getMessage());
            }
        });

        // Log failed jobs
        Queue::failing(function (JobFailed $event) {
            $jobId = $event->job->getJobId();
            $startedAt = self::$jobStartTimes[$jobId] ?? null;
            unset(self::$jobStartTimes[$jobId]);
            $duration = $startedAt ? (microtime(true) - $startedAt) * 1000 : null;
            $payload = $event->job->payload();
            $displayName = $this->resolveJobDisplayName($payload, $event->job);

            try {
                $errorMessage = $event->exception ? mb_substr((string) $event->exception->getMessage(), 0, 65000) : null;
                DB::table('job_histories')->insert([
                    'queue' => $event->job->getQueue(),
                    'job_class' => $displayName,
                    'status' => 'failed',
                    'duration_ms' => $duration !== null ? round($duration, 2) : null,
                    'error_message' => $errorMessage,
                    'processed_at' => now(),
                ]);
            } catch (\Throwable $e) {
                // Silent fail
                logger()->warning('Failed to persist job history (failed): ' . $e->getMessage());
            }
        });
    }

    /**
     * Resolve the display name from a job's payload, handling both
     * string (database/redis driver) and array (sync driver) formats.
     */
    private function resolveJobDisplayName(array|string $payload, object $job): string
    {
        if (is_array($payload)) {
            return $payload['displayName'] ?? get_class($job);
        }

        if (is_string($payload)) {
            $decoded = json_decode($payload, true);

            return is_array($decoded) && isset($decoded['displayName'])
                ? $decoded['displayName']
                : get_class($job);
        }

        return get_class($job);
    }
}
