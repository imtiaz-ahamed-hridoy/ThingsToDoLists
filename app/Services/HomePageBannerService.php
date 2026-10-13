<?php

namespace App\Services;

use App\Models\HomePageBanner;
use Illuminate\Http\UploadedFile;

class HomePageBannerService
{
    public function __construct(
        private ImageUploadService $imageUploadService
    ) {}

    /**
     * Get or create the single banner.
     */
    public function firstOrCreate(): HomePageBanner
    {
        $banner = HomePageBanner::first();

        if (!$banner) {
            $banner = HomePageBanner::create([
                'title' => 'Home Page Banner',
                'status' => true,
                'sort_order' => 0,
            ]);
        }

        return $banner;
    }

    /**
     * Get the single banner by ID.
     */
    public function findOrFail(int $id): HomePageBanner
    {
        return HomePageBanner::findOrFail($id);
    }

    /**
     * Get the first banner without auto-creating.
     */
    public function first(): ?HomePageBanner
    {
        return HomePageBanner::first();
    }

    /**
     * Update the banner with video file, poster image, title, and status.
     */
    public function update(
        HomePageBanner $banner,
        array $data,
        ?UploadedFile $videoFile,
        ?UploadedFile $posterImage,
        bool $clearVideo = false,
        bool $clearPoster = false
    ): HomePageBanner {
        $banner->title = $data['title'] ?? $banner->title;
        $banner->video_url = array_key_exists('video_url', $data)
            ? ($data['video_url'] ?: null)
            : $banner->video_url;
        $banner->status = array_key_exists('status', $data)
            ? (bool) $data['status']
            : $banner->status;

        // Handle video file upload
        if ($videoFile) {
            $this->imageUploadService->delete($banner->getRawOriginal('video_path'));
            $banner->video_path = $this->imageUploadService->upload($videoFile, 'cms/home_page/banners');

            if (empty($data['video_url'])) {
                $banner->video_url = null;
            }
        }

        // Handle poster image upload
        if ($posterImage) {
            $this->imageUploadService->delete($banner->getRawOriginal('poster_image'));
            $banner->poster_image = $this->imageUploadService->upload($posterImage, 'cms/home_page/banners/posters');
        }

        // Handle clearing video file
        if ($clearVideo && !$videoFile && $banner->getRawOriginal('video_path')) {
            $this->imageUploadService->delete($banner->getRawOriginal('video_path'));
            $banner->video_path = null;
        }

        // Handle clearing poster image
        if ($clearPoster && !$posterImage && $banner->getRawOriginal('poster_image')) {
            $this->imageUploadService->delete($banner->getRawOriginal('poster_image'));
            $banner->poster_image = null;
        }

        $banner->save();

        return $banner;
    }
}
