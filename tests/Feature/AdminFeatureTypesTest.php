<?php

namespace Tests\Feature;

use App\Models\FeatureType;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFeatureTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_feature_types_index()
    {
        $response = $this->actingAs($this->admin())->get('/admin/cms-settings/feature-types');
        $response->assertOk()->assertSee('Feature Types');
    }

    public function test_admin_can_create_feature_type_and_assign_products()
    {
        $p1 = Product::create([
            'provider' => 'supplier',
            'slug' => 'p1',
            'title' => 'Product One',
            'status' => 'active',
            'published' => true,
        ]);

        $p2 = Product::create([
            'provider' => 'supplier',
            'slug' => 'p2',
            'title' => 'Product Two',
            'status' => 'active',
            'published' => true,
        ]);

        $response = $this->actingAs($this->admin())->post('/admin/cms-settings/feature-types/store', [
            'name' => 'Top Picks',
            'slug' => 'top-picks',
            'product_ids' => [$p1->id, $p2->id],
            'status' => 1,
        ]);

        $response->assertRedirect(route('cmsSettings.featureTypes.index'));

        $this->assertDatabaseHas('feature_types', ['name' => 'Top Picks', 'slug' => 'top-picks']);

        $type = FeatureType::where('slug', 'top-picks')->first();
        $this->assertNotNull($type);
        $this->assertCount(2, $type->products);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin User',
            'email' => uniqid('admin_', true) . '@example.test',
            'password' => 'password',
            'role' => 'Admin',
            'is_agree' => true,
        ]);
    }
}
