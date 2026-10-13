<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait FileManager
{
    /**
     * Normalize any file input into a flat array of UploadedFile objects.
     */
    private function normalizeFiles($files): array
    {
        if (empty($files)) return [];

        if ($files instanceof UploadedFile) return [$files];

        $iterator = is_array($files) ? $files : [$files];
        $normalized = [];

        foreach ($iterator as $item) {
            if ($item instanceof UploadedFile) {
                $normalized[] = $item;
            } elseif (is_array($item)) {
                $normalized = array_merge($normalized, $this->normalizeFiles($item));
            }
        }

        return $normalized;
    }

    /**
     * Upload single or multiple files to public storage folder.
     *
     * Uses Storage::disk('public') so files are stored in storage/app/public/
     * and served via the public/storage symlink. This works consistently
     * on both local dev and production (nginx) servers.
     */
    public function uploadToPublic($files, string $folder = 'uploads'): array
    {
        return $this->uploadToStorage($files, $folder, 'public');
    }

    /**
     * Delete single or multiple files from public storage + DB update.
     */
    public function deleteFromPublic(object $model, string $column): bool
    {
        return $this->deleteFromStorage($model, $column, 'public');
    }

    /**
     * Upload single or multiple files to storage folder
     */
    public function uploadToStorage($files, string $folder = 'uploads', string $disk = 'public'): array
    {
        $files = $this->normalizeFiles($files);
        $folder = trim($folder, '/');
        $paths = [];

        foreach ($files as $file) {
            $paths[] = $file->store($folder, $disk);
        }

        return $paths;
    }

    /**
     * Delete single or multiple files from storage folder + DB update
     */
    public function deleteFromStorage(object $model, string $column,  string $disk = 'public'): bool
    {
        $raw = $model->getRawOriginal($column);
        if (empty($raw)) return false;

        $files = is_array($raw) ? $raw : json_decode($raw, true);

        foreach ((array)$files as $filePath) {
            if (Storage::disk($disk)->exists($filePath)) Storage::disk($disk)->delete($filePath);
        }

        $model->{$column} = null;
        return $model->save();
    }

    /**
     * Update files in public storage: delete old + upload new
     */
    public function updateToPublic(object $model, string $column, $newFiles, string $folder = 'uploads'): array
    {
        return $this->updateToStorage($model, $column, $newFiles, $folder, 'public');
    }

    /**
     * Update files in storage folder: delete old + upload new
     */
    public function updateToStorage(object $model, string $column, $newFiles, string $folder = 'uploads',  string $disk = 'public'): array
    {
        $this->deleteFromStorage($model, $column, $disk);
        $paths = $this->uploadToStorage($newFiles, $folder, $disk);

        $model->{$column} = $paths;
        $model->save();

        return $paths;
    }
}
