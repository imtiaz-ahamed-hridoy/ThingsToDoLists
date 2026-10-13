<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait ImagePathTrait
{
    /**
     * Convert a file path into a full public URL.
     *
     * Uses Storage::url() for direct symlink-based URLs (/storage/...)
     * via the public/storage -> storage/app/public symlink.
     */
    public function fullImageUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        // If it's already an absolute URL, return as-is
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        // Clean any leading '/storage/' or 'storage/' prefix from stored paths
        $cleanPath = ltrim($path, '/');
        if (strpos($cleanPath, 'storage/') === 0) {
            $cleanPath = substr($cleanPath, 8);
        }

        return Storage::disk('public')->url($cleanPath);
    }

    /**
     * Convert ONLY for API requests.
     */
    public function fullImageUrlForApi(?string $path): ?string
    {
        if (request()->is('api/*')) {
            return $this->fullImageUrl($path);
        }

        return $path;
    }
}
