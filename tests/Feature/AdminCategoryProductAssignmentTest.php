<?php

namespace Tests\Feature;

use App\Models\Categories;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCategoryProductAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_category_and_assign_products(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $firstProduct = $this->product('first-product');
        $secondProduct = $this->product('second-product');

        $response = $this->actingAs($admin)->post('/admin/category/store', [
            'name' => 'Adventure Tours',
            'slug' => 'Adventure Tours',
            'type' => 'activity',
            'status' => '1',
            'show_in_search' => '1',
            'search_label' => 'Adventure Experiences',
            'search_subtitle' => 'Curated adventure activities',
            'search_icon' => 'compass',
            'search_sort_order' => 7,
            'product_ids' => [$firstProduct->id, $secondProduct->id],
        ]);

        $response->assertRedirect(route('category.index'));

        $category = Categories::where('slug', 'adventure-tours')->firstOrFail();

        $this->assertTrue($category->show_in_search);
        $this->assertSame('Adventure Experiences', $category->search_label);
        $this->assertSame('Curated adventure activities', $category->search_subtitle);
        $this->assertSame('compass', $category->search_icon);
        $this->assertSame(7, $category->search_sort_order);
        $this->assertSame($category->id, $firstProduct->fresh()->category_id);
        $this->assertSame($category->id, $secondProduct->fresh()->category_id);
    }

    public function test_admin_can_update_category_product_assignments(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $category = Categories::create([
            'name' => 'City Tours',
            'slug' => 'city-tours',
            'type' => 'activity',
            'status' => 1,
        ]);

        $firstProduct = $this->product('assigned-product', $category->id);
        $secondProduct = $this->product('removed-product', $category->id);

        $response = $this->actingAs($admin)->put('/admin/category/update/' . $category->id, [
            'name' => 'City Tours Updated',
            'slug' => 'City Tours Updated',
            'type' => 'activity',
            'status' => '1',
            'product_ids' => [$firstProduct->id],
        ]);

        $response->assertRedirect(route('category.index'));

        $category->refresh();

        $this->assertSame('city-tours-updated', $category->slug);
        $this->assertSame($category->id, $firstProduct->fresh()->category_id);
        $this->assertNull($secondProduct->fresh()->category_id);
    }

    public function test_admin_can_assign_products_by_comma_separated_product_api_ids(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $firstProduct = $this->product('api-product-one');
        $secondProduct = $this->product('api-product-two');

        $response = $this->actingAs($admin)->post('/admin/category/store', [
            'name' => 'API ID Category',
            'slug' => 'API ID Category',
            'type' => 'activity',
            'status' => '1',
            'product_api_ids' => 'api-product-one, api-product-two',
        ]);

        $response->assertRedirect(route('category.index'));

        $category = Categories::where('slug', 'api-id-category')->firstOrFail();

        $this->assertSame($category->id, $firstProduct->fresh()->category_id);
        $this->assertSame($category->id, $secondProduct->fresh()->category_id);
    }

    public function test_unknown_product_api_ids_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $response = $this->actingAs($admin)->from('/admin/category/create')->post('/admin/category/store', [
            'name' => 'Missing API Category',
            'slug' => 'Missing API Category',
            'type' => 'activity',
            'status' => '1',
            'product_api_ids' => 'missing-api-id',
        ]);

        $response
            ->assertRedirect('/admin/category/create')
            ->assertSessionHasErrors('product_api_ids');
    }

    private function product(string $slug, ?int $categoryId = null): Product
    {
        return Product::create([
            'product_api_id' => $slug,
            'platform' => 'supplier',
            'provider' => 'supplier',
            'category_id' => $categoryId,
            'title' => str($slug)->replace('-', ' ')->title()->toString(),
            'slug' => $slug,
            'status' => 'active',
            'published' => true,
        ]);
    }
}
