<?php

namespace Tests\Feature;

use App\Models\HomePageBanner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminHomePageBannersTest extends TestCase
{


    public function test_admin_can_view_banner_settings()
    {
        // Auto-creates banner on first visit
        $response = $this->actingAs($this->admin())->get('/admin/cms-settings/banners');
        $response->assertOk();

        // Should see the edit form with banner data
        $this->assertDatabaseHas('home_page_banners', [
            'title' => 'Home Page Banner',
        ]);
    }

    public function test_admin_can_update_banner_with_video_url()
    {
        // First visit creates the banner
        $this->actingAs($this->admin())->get('/admin/cms-settings/banners');
        $banner = HomePageBanner::first();

        $response = $this->actingAs($this->admin())->put('/admin/cms-settings/banners/' . $banner->id, [
            'title' => 'Updated Banner',
            'video_url' => 'https://assets.mixkit.co/videos/2213/2213-720.mp4',
            'status' => 1,
        ]);

        $response->assertRedirect(route('cmsSettings.banners.index'));

        $this->assertDatabaseHas('home_page_banners', [
            'id' => $banner->id,
            'title' => 'Updated Banner',
            'video_url' => 'https://assets.mixkit.co/videos/2213/2213-720.mp4',
        ]);
    }

    public function test_admin_can_update_banner_with_uploaded_video_and_poster()
    {
        Storage::fake('public');

        // First visit creates the banner
        $this->actingAs($this->admin())->get('/admin/cms-settings/banners');
        $banner = HomePageBanner::first();

        $video = UploadedFile::fake()->create('sample.mp4', 1024);
        $poster = UploadedFile::fake()->image('poster.jpg');

        $response = $this->actingAs($this->admin())->put('/admin/cms-settings/banners/' . $banner->id, [
            'title' => 'Upload Banner',
            'video_file' => $video,
            'poster_image' => $poster,
            'status' => 1,
        ]);

        $response->assertRedirect(route('cmsSettings.banners.index'));

        $banner->refresh();
        $this->assertNotNull($banner->getRawOriginal('video_path'));
        $this->assertNotNull($banner->getRawOriginal('poster_image'));
        Storage::disk('public')->assertExists($banner->getRawOriginal('video_path'));
        Storage::disk('public')->assertExists($banner->getRawOriginal('poster_image'));
    }

    public function test_admin_can_clear_video_and_poster()
    {
        Storage::fake('public');

        // Create banner with files
        $banner = HomePageBanner::create([
            'title' => 'Test',
            'video_path' => 'cms/home_page/banners/test.mp4',
            'poster_image' => 'cms/home_page/banners/posters/test.jpg',
            'status' => true,
            'sort_order' => 0,
        ]);

        // Create fake files so storage exists
        Storage::disk('public')->put('cms/home_page/banners/test.mp4', 'test');
        Storage::disk('public')->put('cms/home_page/banners/posters/test.jpg', 'test');

        $response = $this->actingAs($this->admin())->put('/admin/cms-settings/banners/' . $banner->id, [
            'title' => 'Cleared',
            'clear_video' => 1,
            'clear_poster' => 1,
            'status' => 1,
        ]);

        $response->assertRedirect(route('cmsSettings.banners.index'));

        $banner->refresh();
        $this->assertNull($banner->getRawOriginal('video_path'));
        $this->assertNull($banner->getRawOriginal('poster_image'));
        Storage::disk('public')->assertMissing('cms/home_page/banners/test.mp4');
        Storage::disk('public')->assertMissing('cms/home_page/banners/posters/test.jpg');
    }

}
