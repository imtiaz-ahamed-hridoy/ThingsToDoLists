<?php

namespace App\Observers;

use App\Models\BlogPost;
use Illuminate\Support\Facades\Cache;

class BlogPostObserver
{
    /**
     * Clear all blog-related public API caches on any create/update/delete.
     */
    public function saved(BlogPost $post): void
    {
        $this->clearCache($post);
    }

    public function deleted(BlogPost $post): void
    {
        $this->clearCache($post);
    }

    private function clearCache(BlogPost $post): void
    {
        // Clear detail cache for the specific post
        Cache::forget('blog:detail:' . $post->id);
        Cache::forget('blog:detail:' . $post->slug);

        // Clear list/featured/latest caches (broad invalidation)
        Cache::forget('blog:featured');

        // Clear latest caches for common limits
        foreach ([3, 4, 5, 6, 8, 9, 10, 12, 15, 20] as $limit) {
            Cache::forget('blog:latest:limit:' . $limit);
        }

        // Clear paginated index caches for common combinations
        foreach ([6, 9, 12, 15, 20] as $perPage) {
            for ($page = 1; $page <= 5; $page++) {
                Cache::forget('blogs:index:' . md5("{$perPage}|{$page}||"));
            }
        }
    }
}
