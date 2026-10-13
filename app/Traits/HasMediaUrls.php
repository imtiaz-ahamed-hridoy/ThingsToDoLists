<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HasMediaUrls
{
    /**
     * Convert a relative storage path into an absolute public URL
     * using Laravel's public storage symlink.
     *
     * URL format: https://APP_URL/storage/folder/filename.jpg
     *
     * Requires the public/storage -> storage/app/public symlink.
     * Files must be stored via Storage::disk('public')->put() or $file->store('folder', 'public').
     *
     * @param string|null $path Relative storage path (e.g., "products/photo.jpg")
     * @return string|null
     */
    protected function resolveMediaUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        // Return absolute URLs as-is (external provider images)
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        // Clean legacy '/storage/' or 'storage/' prefix
        $cleanPath = ltrim($path, '/');
        if (str_starts_with($cleanPath, 'storage/')) {
            $cleanPath = substr($cleanPath, 8);
        }

        return Storage::disk('public')->url($cleanPath);
    }
}
