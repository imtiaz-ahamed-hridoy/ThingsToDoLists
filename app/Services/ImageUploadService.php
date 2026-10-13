<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Single, reusable service for all image upload and management operations.
 *
 * All uploaded files are stored on the 'public' disk (storage/app/public/)
 * and exposed via the public/storage -> storage/app/public symlink.
 *
 * Only relative paths are stored in the database (e.g., "products/photo.jpg").
 * URLs are generated using Storage::url() at display time.
 */
class ImageUploadService
{
    /**
     * Upload a single file to the specified folder on the public disk.
     *
     * @param UploadedFile $file
     * @param string $folder e.g., "products", "categories", "users/avatar"
     * @return string The relative storage path (e.g., "products/abc123.jpg")
     */
    public function upload(UploadedFile $file, string $folder): string
    {
        return $file->store($folder, 'public');
    }

    /**
     * Upload a file with a custom filename.
     *
     * @param UploadedFile $file
     * @param string $folder
     * @param string $name
     * @return string
     */
    public function uploadAs(UploadedFile $file, string $folder, string $name): string
    {
        return $file->storeAs($folder, $name, 'public');
    }

    /**
     * Replace an old image with a new one. Deletes the old file if it exists.
     *
     * @param UploadedFile $newFile
     * @param string $folder
     * @param string|null $oldPath Relative path of the old file to delete
     * @return string The new relative storage path
     */
    public function replace(UploadedFile $newFile, string $folder, ?string $oldPath): string
    {
        if ($oldPath) {
            $this->delete($oldPath);
        }

        return $this->upload($newFile, $folder);
    }

    /**
     * Delete a file from the public disk.
     *
     * @param string|null $path Relative storage path
     * @return bool
     */
    public function delete(?string $path): bool
    {
        if (empty($path)) {
            return false;
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->delete($path);
        }

        return false;
    }

    /**
     * Check if a file exists on the public disk.
     *
     * @param string $path Relative storage path
     * @return bool
     */
    public function exists(string $path): bool
    {
        return Storage::disk('public')->exists($path);
    }

    /**
     * Get the public URL for a stored file.
     *
     * @param string|null $path Relative storage path
     * @return string|null
     */
    public function url(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        // Return absolute URLs as-is (external provider images)
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        // Clean any legacy '/storage/' prefix
        $cleanPath = ltrim($path, '/');
        if (str_starts_with($cleanPath, 'storage/')) {
            $cleanPath = substr($cleanPath, 8);
        }

        return Storage::disk('public')->url($cleanPath);
    }

    /**
     * Delete all files in a directory (recursively).
     *
     * @param string $directory Relative directory path
     * @return bool
     */
    public function deleteDirectory(string $directory): bool
    {
        return Storage::disk('public')->deleteDirectory($directory);
    }
}
