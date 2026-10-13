<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Location;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_store_accepts_product_id_and_returns_product_summary(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = Product::create([
            'provider' => 'supplier',
            'platform' => 'supplier',
            'product_api_id' => 'local-review-product',
            'title' => 'Dhaka River Cruise',
            'slug' => 'dhaka-river-cruise',
            'short_introduction' => 'Fast city tour with river views.',
            'thumbnail' => 'https://example.com/review-product.jpg',
            'status' => 'active',
            'published' => true,
            'reviewCount' => 0,
        ]);

        Location::create([
            'product_id' => $product->id,
            'city' => 'Dhaka',
            'country' => 'Bangladesh',
            'countryCode' => 'BD',
            'is_primary' => true,
        ]);

        Review::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'ratings' => 4,
            'comment' => 'Good trip.',
        ]);

        $response = $this->postJson('/api/v1/reviews', [
            'users_id' => $user->id,
            'product_id' => $product->id,
            'ratings' => 5,
            'comment' => 'Excellent experience!',
            'company' => 'My Travel Co',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Review successfully stored')
            ->assertJsonPath('status', 200)
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.product_id', $product->id)
            ->assertJsonPath('data.ratings', 5)
            ->assertJsonPath('data.comment', 'Excellent experience!')
            ->assertJsonPath('data.product.id', $product->id)
            ->assertJsonPath('data.product.short_summary', 'Fast city tour with river views.')
            ->assertJsonPath('data.product.thumbnail', 'https://example.com/review-product.jpg')
            ->assertJsonPath('data.product.total_review_count', 2)
            ->assertJsonPath('data.product.city', 'Dhaka')
            ->assertJsonPath('data.product.country', 'Bangladesh');

        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'ratings' => 5,
            'comment' => 'Excellent experience!',
        ]);

        $this->assertSame(2, $product->fresh()->reviewCount);
    }

    public function test_review_store_uses_product_table_activity_media_and_infers_city_country(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        City::create([
            'city_name' => 'Paris',
            'slug' => 'paris',
            'country_name' => 'France',
            'country_short_code' => 'FR',
        ]);

        $product = Product::create([
            'provider' => 'supplier',
            'platform' => 'supplier',
            'product_api_id' => 'local-review-product-table-fields',
            'title' => 'Paris City Tour',
            'slug' => 'paris-city-tour-product-table-fields',
            'short_introduction' => 'Discover the best of Paris on this guided city tour.',
            'image_gallery' => [[
                'original' => 'products/paris/original.jpg',
                'large' => 'products/paris/large.jpg',
                'preview' => 'products/paris/preview.jpg',
                'sort_order' => 0,
            ]],
            'status' => 'draft',
            'published' => false,
            'reviewCount' => 0,
        ]);

        $response = $this->postJson('/api/v1/reviews', [
            'users_id' => $user->id,
            'product_id' => $product->id,
            'ratings' => 5,
            'comment' => 'Excellent experience!',
            'company' => 'My Travel Co',
        ]);

        $expectedThumbnail = rtrim((string) config('app.url'), '/') . '/storage/products/paris/preview.jpg';

        $response->assertOk()
            ->assertJsonPath('data.status', 'Active')
            ->assertJsonPath('data.product.short_summary', 'Discover the best of Paris on this guided city tour.')
            ->assertJsonPath('data.product.thumbnail', $expectedThumbnail)
            ->assertJsonPath('data.product.city', 'Paris')
            ->assertJsonPath('data.product.country', 'France');

        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => 'Active',
        ]);
    }

    public function test_review_update_can_repair_legacy_review_without_owner(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        City::create([
            'city_name' => 'Paris',
            'slug' => 'paris',
            'country_name' => 'France',
            'country_short_code' => 'FR',
        ]);

        $product = Product::create([
            'provider' => 'supplier',
            'platform' => 'supplier',
            'product_api_id' => 'legacy-review-product',
            'title' => 'Legacy Paris Review Product',
            'slug' => 'legacy-paris-review-product',
            'short_introduction' => 'Legacy review product summary.',
            'image_gallery' => [[
                'original' => 'products/paris/original.jpg',
                'preview' => 'products/paris/preview.jpg',
                'sort_order' => 0,
            ]],
            'status' => 'active',
            'published' => true,
            'reviewCount' => 0,
        ]);

        $review = Review::query()->create([
            'ratings' => 3,
            'comment' => 'Old review.',
            'status' => 'Active',
        ]);

        $response = $this->postJson('/api/v1/reviews/' . $review->id, [
            'users_id' => $user->id,
            'product_id' => $product->id,
            'ratings' => 5,
            'comment' => 'Updated review.',
            'company' => 'My Travel Co',
        ]);

        $expectedThumbnail = rtrim((string) config('app.url'), '/') . '/storage/products/paris/preview.jpg';

        $response->assertOk()
            ->assertJsonPath('message', 'Review updated successfully!')
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.product_id', $product->id)
            ->assertJsonPath('data.status', 'Active')
            ->assertJsonPath('data.product.thumbnail', $expectedThumbnail)
            ->assertJsonPath('data.product.city', 'Paris')
            ->assertJsonPath('data.product.country', 'France');

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'ratings' => 5,
            'comment' => 'Updated review.',
            'status' => 'Active',
        ]);
    }
}
