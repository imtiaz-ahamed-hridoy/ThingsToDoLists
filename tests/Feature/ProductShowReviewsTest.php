<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class ProductShowReviewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_show_includes_reviews_and_images(): void
    {
        Storage::fake('public');

        // Create product
        $product = Product::create([
            'product_api_id' => 'test-prod-1',
            'platform' => 'supplier',
            'provider' => 'supplier',
            'title' => 'Test Product With Reviews',
            'slug' => 'test-product-with-reviews',
            'status' => 'active',
            'published' => true,
            'default_currency' => 'USD',
            'from_price' => 50,
            'original_price' => 60,
            'full_description' => 'Product used for testing reviews integration.',
        ]);

        Location::create([
            'product_id' => $product->id,
            'city' => 'Liverpool',
            'country' => 'United Kingdom',
            'countryCode' => 'GB',
            'is_primary' => true,
        ]);

        // Create user and authenticate
        $user = User::create([
            'name' => 'Herve',
            'email' => 'herve@example.com',
            'password' => bcrypt('password'),
        ]);

        $file = UploadedFile::fake()->image('review1.jpg');

        $payload = [
            'product_id' => $product->id,
            'ratings' => 5,
            'comment' => 'Discovery of the legendary Anfield Road stadium. The tour is very well guided, the audio guide in French allows you to learn a lot about the history of the club and its stadium.',
            'upload_images' => [$file],
        ];

        Sanctum::actingAs($user);

        $storeResponse = $this->post('/api/v1/reviews', $payload);

        $storeResponse->assertStatus(200);
        $this->assertEquals('Review successfully stored', $storeResponse->json('message'));

        // Now fetch product show
        $response = $this->getJson('/api/v1/product/show/' . $product->id);

        $response->assertOk();

        $this->assertEquals(1, $response->json('data.review_count'));
        $this->assertTrue(in_array($response->json('data.review_rating'), [5, 5.0, '5', '5.0'], true));

        $reviews = $response->json('data.reviews');
        $this->assertIsArray($reviews);
        $this->assertCount(1, $reviews);

        $first = $reviews[0];
        $this->assertSame(5, (int) $first['ratings']);
        $this->assertStringContainsString('Anfield', $first['comment']);
        $this->assertArrayHasKey('review_images', $first);
        $this->assertIsArray($first['review_images']);
        $this->assertCount(1, $first['review_images']);
        $this->assertIsString($first['review_images'][0]);

        // Ensure user info is present
        $this->assertArrayHasKey('user', $first);
        $this->assertSame('Herve', $first['user']['name']);
    }
}
