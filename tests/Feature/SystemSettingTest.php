<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_system_settings(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'Admin']);

        $response = $this->actingAs($admin)->patch(route('system.update'), [
            'title' => 'New Title',
            'email' => 'admin@example.com',
            'system_name' => 'New System Name',
            'facebook' => 'https://facebook.com/test',
            'twitter' => 'https://twitter.com/test',
            'linkedin' => 'https://linkedin.com/test',
            'supplier_guide_title' => 'Supplier Guide From Admin',
            'supplier_guide_description' => 'Admin managed supplier guide description.',
            'supplier_guide_link_or_iframe' => 'https://example.com/supplier-guide',
            'supplier_guide_media' => UploadedFile::fake()->image('supplier-guide.jpg'),
        ]);

        $response->assertStatus(302);

        $setting = SystemSetting::first();

        $this->assertNotNull($setting);
        $this->assertSame('New Title', $setting->title);
        $this->assertSame('https://facebook.com/test', $setting->facebook);
        $this->assertSame('Supplier Guide From Admin', $setting->supplier_guide_title);
        $this->assertSame('Admin managed supplier guide description.', $setting->supplier_guide_description);
        $this->assertSame('https://example.com/supplier-guide', $setting->supplier_guide_link_or_iframe);
        $this->assertSame('image', $setting->supplier_guide_media_type);
        $this->assertNotEmpty($setting->getRawOriginal('supplier_guide_media'));
    }

    public function test_api_returns_updated_system_settings(): void
    {
        SystemSetting::create([
            'title' => 'API Title',
            'facebook' => 'https://facebook.com/api-test',
            'supplier_guide_title' => 'API Supplier Guide',
            'supplier_guide_link_or_iframe' => 'https://example.com/embed',
        ]);

        $response = $this->getJson('/api/v1/system-setting');

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'API Title')
            ->assertJsonPath('data.facebook', 'https://facebook.com/api-test')
            ->assertJsonPath('data.supplier_guide_title', 'API Supplier Guide')
            ->assertJsonPath('data.supplier_guide_link_or_iframe', 'https://example.com/embed');
    }
}
