<?php

use App\Models\Currency;
use App\Models\HomePageSection;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    // Create some products
    $this->product1 = Product::create([
        'product_api_id' => 'test-prod-1',
        'platform' => 'supplier',
        'provider' => 'supplier',
        'title' => 'Surf Lesson',
        'slug' => 'surf-lesson',
        'status' => 'active',
        'published' => true,
        'from_price' => 99.99,
        'default_currency' => 'USD',
        'reviewRating' => 4.5,
        'reviewCount' => 120,
        'duration' => '3 hours',
    ]);

    $this->product2 = Product::create([
        'product_api_id' => 'test-prod-2',
        'platform' => 'supplier',
        'provider' => 'supplier',
        'title' => 'Snorkeling Tour',
        'slug' => 'snorkeling-tour',
        'status' => 'active',
        'published' => true,
        'from_price' => 149.99,
        'default_currency' => 'USD',
        'reviewRating' => 4.8,
        'reviewCount' => 85,
        'duration' => '4 hours',
    ]);

    $this->product3 = Product::create([
        'product_api_id' => 'test-prod-3',
        'platform' => 'supplier',
        'provider' => 'supplier',
        'title' => 'Inactive Product',
        'slug' => 'inactive-product',
        'status' => 'inactive',
        'published' => false,
    ]);

    // Create active section
    $this->activeSection = HomePageSection::create([
        'title' => 'Featured Adventures',
        'short_description' => 'Best adventures in town',
        'image' => null,
        'product_ids' => [$this->product1->id, $this->product2->id],
        'sort_order' => 1,
        'status' => true,
    ]);

    // Create inactive section (should NOT appear in index)
    $this->inactiveSection = HomePageSection::create([
        'title' => 'Hidden Section',
        'short_description' => 'Should not be visible',
        'image' => null,
        'product_ids' => [],
        'sort_order' => 2,
        'status' => false,
    ]);

    // Create second active section with no products
    $this->emptySection = HomePageSection::create([
        'title' => 'Empty Section',
        'short_description' => 'No products here',
        'image' => null,
        'product_ids' => [],
        'sort_order' => 3,
        'status' => true,
    ]);

    $this->apiBase = '/api/v1';
});

it('returns all active sections with their products', function () {
    $response = $this->getJson("{$this->apiBase}/home-page-sections");

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Home page sections fetched successfully.')
        ->assertJsonPath('status', 200);

    // Should only return active sections (2 active: Featured Adventures + Empty Section)
    $this->assertCount(2, $response->json('data'));

    // First section should be Featured Adventures (sort_order 1)
    $firstSection = $response->json('data.0');
    expect($firstSection['title'])->toBe('Featured Adventures');
    expect($firstSection['short_description'])->toBe('Best adventures in town');
    expect($firstSection['sort_order'])->toBe(1);
    expect($firstSection['product_count'])->toBe(2);

    // Should have product details
    $products = $firstSection['products'];
    expect($products)->toHaveCount(2);
    expect($products[0]['title'])->toBe('Surf Lesson');
    expect($products[0]['from_price'])->toEqual(99.99);
    expect($products[0])->not->toHaveKey('default_currency');
    expect($products[0]['currency'])->toBe('USD');
    expect($products[0]['is_wishlisted'])->toBeFalse();
    expect($products[0]['reviewRating'])->toEqual(4.5);
    expect($products[1]['title'])->toBe('Snorkeling Tour');

    // Second section should be Empty Section (sort_order 3)
    $secondSection = $response->json('data.1');
    expect($secondSection['title'])->toBe('Empty Section');
    expect($secondSection['product_count'])->toBe(0);
    expect($secondSection['products'])->toBe([]);
});

it('does not return inactive sections', function () {
    $response = $this->getJson("{$this->apiBase}/home-page-sections");

    $titles = collect($response->json('data'))->pluck('title')->all();
    expect($titles)->not->toContain('Hidden Section');
});

it('returns sections ordered by sort_order', function () {
    $response = $this->getJson("{$this->apiBase}/home-page-sections");

    $orders = collect($response->json('data'))->pluck('sort_order')->all();
    expect($orders)->toBe([1, 3]); // sort_order 1 then 3 (2 is inactive)
});

it('returns a single section by id', function () {
    $response = $this->getJson("{$this->apiBase}/home-page-sections/{$this->activeSection->id}");

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Home page section fetched successfully.')
        ->assertJsonPath('status', 200)
        ->assertJsonPath('data.title', 'Featured Adventures')
        ->assertJsonPath('data.product_count', 2);
});

it('returns 404 for inactive section via show endpoint', function () {
    $response = $this->getJson("{$this->apiBase}/home-page-sections/{$this->inactiveSection->id}");

    $response->assertStatus(404);
});

it('returns 404 for non-existent section', function () {
    $response = $this->getJson("{$this->apiBase}/home-page-sections/99999");

    $response->assertStatus(404);
});

it('returns empty array when no active sections exist', function () {
    HomePageSection::query()->update(['status' => false]);

    $response = $this->getJson("{$this->apiBase}/home-page-sections");

    $response->assertStatus(200)
        ->assertJsonPath('data', []);
});

it('includes correct product fields in response', function () {
    $response = $this->getJson("{$this->apiBase}/home-page-sections");

    $product = $response->json('data.0.products.0');

    expect($product)->toHaveKey('id');
    expect($product)->toHaveKey('title');
    expect($product)->toHaveKey('slug');
    expect($product)->toHaveKey('thumbnail');
    expect($product)->toHaveKey('image');
    expect($product)->toHaveKey('from_price');
    expect($product)->not->toHaveKey('default_currency');
    expect($product)->toHaveKey('currency');
    expect($product)->toHaveKey('is_wishlisted');
    expect($product)->toHaveKey('reviewRating');
    expect($product)->toHaveKey('reviewCount');
    expect($product)->toHaveKey('duration');
    expect($product)->toHaveKey('location');
});

it('applies x-currency after reading homepage sections from cache', function () {
    Currency::create(['code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1]);
    Currency::create(['code' => 'EUR', 'symbol' => 'EUR', 'exchange_rate' => 0.8]);

    $this->getJson("{$this->apiBase}/home-page-sections")
        ->assertOk()
        ->assertJsonPath('data.0.products.0.currency', 'USD')
        ->assertJsonPath('data.0.products.0.from_price', 99.99);

    expect(Cache::has('homepage:sections:active'))->toBeTrue();

    $this->withHeader('X-Currency', 'EUR')
        ->getJson("{$this->apiBase}/home-page-sections")
        ->assertOk()
        ->assertJsonPath('data.0.products.0.currency', 'EUR')
        ->assertJsonPath('data.0.products.0.from_price', 79.99);
});

it('applies x-guest-wishlist after reading homepage sections from cache', function () {
    $guestId = 'guest-homepage-test';

    Wishlist::create([
        'guest_id' => $guestId,
        'product_id' => $this->product2->id,
    ]);

    $this->getJson("{$this->apiBase}/home-page-sections")
        ->assertOk()
        ->assertJsonPath('data.0.products.0.is_wishlisted', false)
        ->assertJsonPath('data.0.products.1.is_wishlisted', false);

    expect(Cache::has('homepage:sections:active'))->toBeTrue();

    $this->withHeader('X-Guest-Wishlist', $guestId)
        ->getJson("{$this->apiBase}/home-page-sections")
        ->assertOk()
        ->assertJsonPath('data.0.products.0.is_wishlisted', false)
        ->assertJsonPath('data.0.products.1.is_wishlisted', true);
});
