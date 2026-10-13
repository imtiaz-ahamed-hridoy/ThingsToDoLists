<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Banner\UpdateBannerRequest;
use App\Services\HomePageBannerService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class HomePageBannerController extends Controller
{
    use ApiResponse;

    public function __construct(
        private HomePageBannerService $bannerService
    ) {}

    private function isAdmin(Request $request): bool
    {
        $role = strtolower((string) ($request->user()?->role ?? ''));
        return in_array($role, ['admin', 'administrator', 'super-admin', 'superadmin'], true);
    }

    /**
     * Get the single banner.
     */
    public function index(Request $request)
    {
        if (! $this->isAdmin($request)) {
            return $this->error('Unauthorized access.', 403);
        }

        $banner = $this->bannerService->firstOrCreate();

        return $this->success('Banner retrieved successfully.', $banner);
    }

    /**
     * Show the single banner.
     */
    public function show(Request $request)
    {
        if (! $this->isAdmin($request)) {
            return $this->error('Unauthorized access.', 403);
        }

        $banner = $this->bannerService->first();

        if (! $banner) {
            return $this->error('Banner not found', 404);
        }

        return $this->success('Banner retrieved successfully.', $banner);
    }

    /**
     * Update the single banner.
     */
    public function update(UpdateBannerRequest $request)
    {
        if (! $this->isAdmin($request)) {
            return $this->error('Unauthorized access.', 403);
        }

        $banner = $this->bannerService->firstOrCreate();

        $this->bannerService->update(
            $banner,
            $request->validated(),
            $request->file('video_file'),
            $request->file('poster_image'),
            $request->boolean('clear_video'),
            $request->boolean('clear_poster')
        );

        return $this->success('Banner updated successfully.', $banner->fresh());
    }
}
