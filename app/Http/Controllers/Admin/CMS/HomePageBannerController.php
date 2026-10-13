<?php

namespace App\Http\Controllers\Admin\CMS;

use App\Http\Controllers\Controller;
use App\Http\Requests\Banner\UpdateBannerRequest;
use App\Services\HomePageBannerService;

class HomePageBannerController extends Controller
{
    public function __construct(
        private HomePageBannerService $bannerService
    ) {}

    /**
     * Show the single banner edit form.
     * Auto-creates a default banner if none exists.
     */
    public function index()
    {
        $banner = $this->bannerService->firstOrCreate();
        return view('backend.layouts.cmsSettings.banners.edit', compact('banner'));
    }

    /**
     * Show the edit form for the single banner.
     */
    public function edit($id)
    {
        $banner = $this->bannerService->findOrFail($id);
        return view('backend.layouts.cmsSettings.banners.edit', compact('banner'));
    }

    /**
     * Update the single banner (video, image, title, status).
     */
    public function update(UpdateBannerRequest $request, $id)
    {
        $banner = $this->bannerService->findOrFail($id);

        $this->bannerService->update(
            $banner,
            $request->validated(),
            $request->file('video_file'),
            $request->file('poster_image'),
            $request->boolean('clear_video'),
            $request->boolean('clear_poster')
        );

        return redirect()->route('cmsSettings.banners.index')
            ->with('success', 'Home page banner updated successfully');
    }
}
