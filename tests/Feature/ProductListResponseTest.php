<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Categories;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductListResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_list_returns_database_backed_bokun_and_globaltix_card_data(): void
    {
        $bokunProduct = Product::create([
            'product_api_id' => '149638',
            'platform' => 'bokun',
            'provider' => 'bokun',
            'title' => '3-Day Titicaca Lake Tour (534148)',
            'slug' => '3-day-titicaca-lake-tour-534148',
            'status' => 'active',
            'published' => true,
            'reviewRating' => 0,
            'reviewCount' => 0,
            'default_currency' => 'USD',
            'original_price' => 338.00,
            'from_price' => 338.00,
            'thumbnail' => 'https://example.com/bokun/original.jpg',
            'image_gallery' => [[
                'id' => 1,
                'original' => 'https://example.com/bokun/original.jpg',
                'large' => 'https://example.com/bokun/large.jpg',
                'preview' => 'https://example.com/bokun/preview.jpg',
                'sort_order' => 0,
            ]],
            'raw_response' => [
                'data' => [
                    'activity' => [
                        'title' => '3-Day Titicaca Lake Tour (534148)',
                        'locationCode' => [
                            'country' => 'PE',
                            'location' => 'Puno',
                            'name' => 'Puno',
                        ],
                        'googlePlace' => [
                            'country' => 'Peru',
                            'countryCode' => 'PE',
                            'city' => 'Puno',
                            'geoLocationCenter' => [
                                'lat' => -15.8402218,
                                'lng' => -70.0218805,
                            ],
                        ],
                        'nextDefaultPriceMoney' => [
                            'amount' => 338,
                            'currency' => 'USD',
                        ],
                        'nextDefaultPriceAsText' => 'USD 338.00',
                        'reviewRating' => 0,
                        'reviewCount' => 0,
                    ],
                ],
            ],
        ]);

        Location::create([
            'product_id' => $bokunProduct->id,
            'city' => 'Puno',
            'country' => 'Peru',
            'countryCode' => 'PE',
            'coordinates' => [
                'latitude' => -15.8402218,
                'longitude' => -70.0218805,
            ],
        ]);

        $globaltixProduct = Product::create([
            'product_api_id' => '36609',
            'platform' => 'globaltix',
            'provider' => 'globaltix',
            'title' => '1 Day Dampier Peninsula Adventure',
            'slug' => '1-day-dampier-peninsula-adventure',
            'status' => 'active',
            'published' => true,
            'reviewRating' => 0,
            'reviewCount' => 0,
            'default_currency' => 'AUD',
            'original_price' => 179.00,
            'from_price' => 170.41,
            'thumbnail' => 'https://example.com/globaltix/original.jpg',
            'is_instant_confirmation' => true,
            'image_gallery' => [[
                'id' => 1,
                'original' => 'https://example.com/globaltix/original.jpg',
                'large' => 'https://example.com/globaltix/large.jpg',
                'preview' => 'https://example.com/globaltix/preview.jpg',
                'sort_order' => 0,
            ]],
            'raw_response' => [
                'details' => [
                    'data' => [
                        'name' => '1 Day Dampier Peninsula Adventure',
                        'originalPrice' => 179,
                        'fromPrice' => 170.41,
                        'currency' => 'AUD',
                        'country' => 'Australia',
                        'city' => 'Darwin',
                        'latitude' => -17.95491,
                        'longitude' => 122.2387,
                        'isInstantConfirmation' => true,
                    ],
                ],
            ],
        ]);

        Location::create([
            'product_id' => $globaltixProduct->id,
            'city' => 'Darwin',
            'country' => 'Australia',
            'countryCode' => 'AU',
            'coordinates' => [
                'latitude' => -17.95491,
                'longitude' => 122.2387,
            ],
        ]);

        $response = $this->getJson('/api/v1/product/list');

        $response->assertOk()
            ->assertJsonPath('message', 'Data fetched successfully')
            ->assertJsonCount(2, 'data');

        $products = collect($response->json('data'));

        $bokun = $products->firstWhere('provider', 'bokun');
        $globaltix = $products->firstWhere('provider', 'globaltix');

        $this->assertNotNull($bokun);
        $this->assertNotNull($globaltix);

        $this->assertSame('149638', $bokun['product_api_id']);
        $this->assertSame('3-Day Titicaca Lake Tour (534148)', $bokun['title']);
        $this->assertSame('https://example.com/bokun/original.jpg', $bokun['thumbnail']);
        $this->assertSame('Puno', $bokun['city']);
        $this->assertSame('Peru', $bokun['country']);
        $this->assertEquals(338.0, $bokun['from_price']);
        $this->assertEquals(338.0, $bokun['original_price']);
        $this->assertEquals(338.0, $bokun['fromPrice']);
        $this->assertEquals(338.0, $bokun['originalPrice']);
        $this->assertSame('USD', $bokun['currency']);
        $this->assertSame([], $bokun['categories_preview']);

        $this->assertSame('36609', $globaltix['product_api_id']);
        $this->assertSame('1 Day Dampier Peninsula Adventure', $globaltix['title']);
        $this->assertSame('https://example.com/globaltix/original.jpg', $globaltix['thumbnail']);
        $this->assertEquals(179.0, $globaltix['original_price']);
        $this->assertEquals(170.41, $globaltix['from_price']);
        $this->assertEquals(179.0, $globaltix['originalPrice']);
        $this->assertEquals(170.41, $globaltix['fromPrice']);
        $this->assertSame('AUD', $globaltix['currency']);
        $this->assertSame('Australia', $globaltix['country']);
        $this->assertSame('Darwin', $globaltix['city']);
        $this->assertTrue($globaltix['instant_confirmation']);
    }

    public function test_product_show_uses_normalized_media_relations_when_legacy_gallery_fields_are_empty(): void
    {
        $product = Product::create([
            'product_api_id' => 'supplier-show-1',
            'platform' => 'supplier',
            'provider' => 'supplier',
            'title' => 'Supplier Show Product',
            'slug' => 'supplier-show-product',
            'status' => 'active',
            'published' => true,
            'default_currency' => 'USD',
            'from_price' => 120,
            'original_price' => 140,
            'full_description' => 'Normalized media detail response.',
        ]);

        Location::create([
            'product_id' => $product->id,
            'city' => 'Dubai',
            'country' => 'United Arab Emirates',
            'countryCode' => 'AE',
            'is_primary' => true,
        ]);

        ProductImage::create([
            'product_id' => $product->id,
            'provider' => 'supplier',
            'original_url' => 'https://example.com/supplier/original.jpg',
            'large_url' => 'https://example.com/supplier/large.jpg',
            'preview_url' => 'https://example.com/supplier/preview.jpg',
            'alt_text' => 'Supplier preview image',
            'is_primary' => true,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        ProductVideo::create([
            'product_id' => $product->id,
            'provider' => 'supplier',
            'source_url' => 'https://example.com/supplier/video.mp4',
            'thumbnail_url' => 'https://example.com/supplier/video.jpg',
            'preview_url' => 'https://example.com/supplier/video-preview.jpg',
            'provider_name' => 'supplier',
            'title' => 'Supplier video',
            'duration' => 95,
            'is_primary' => true,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $response = $this->getJson('/api/v1/product/show/' . $product->id);

        $response->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.thumbnail', 'https://example.com/supplier/preview.jpg')
            ->assertJsonPath('data.media.photos.0.original', 'https://example.com/supplier/original.jpg')
            ->assertJsonPath('data.media.videos.0.source_url', 'https://example.com/supplier/video.mp4')
            ->assertJsonPath('data.location.city', 'Dubai');
    }

    public function test_category_wise_products_returns_categories_and_selected_category_products(): void
    {
        $tours = Categories::create([
            'name' => 'Tours',
            'slug' => 'tours',
            'type' => 'experience',
            'status' => 1,
        ]);

        Categories::create([
            'name' => 'Hidden',
            'slug' => 'hidden',
            'type' => 'experience',
            'status' => 0,
        ]);

        $selectedProduct = Product::create([
            'product_api_id' => 'cat-1',
            'platform' => 'supplier',
            'provider' => 'supplier',
            'category_id' => $tours->id,
            'title' => 'Category Product',
            'slug' => 'category-product',
            'status' => 'active',
            'published' => true,
            'default_currency' => 'USD',
            'from_price' => 75,
            'original_price' => 90,
        ]);

        Product::create([
            'product_api_id' => 'cat-2',
            'platform' => 'supplier',
            'provider' => 'supplier',
            'title' => 'Other Product',
            'slug' => 'other-product',
            'status' => 'active',
            'published' => true,
            'default_currency' => 'USD',
            'from_price' => 40,
        ]);

        Product::create([
            'product_api_id' => 'cat-3',
            'platform' => 'supplier',
            'provider' => 'supplier',
            'category_id' => $tours->id,
            'title' => 'Draft Product',
            'slug' => 'draft-product',
            'status' => 'draft',
            'published' => false,
        ]);

        Location::create([
            'product_id' => $selectedProduct->id,
            'city' => 'Dhaka',
            'country' => 'Bangladesh',
            'countryCode' => 'BD',
            'is_primary' => true,
        ]);

        $response = $this->getJson('/api/v1/product/category-wise?category_slug=tours');

        $response->assertOk()
            ->assertJsonPath('message', 'Category wise products fetched successfully')
            ->assertJsonPath('data.selected_category.id', $tours->id)
            ->assertJsonPath('data.selected_category.products_count', 1)
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonCount(1, 'data.categories')
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.id', $selectedProduct->id)
            ->assertJsonPath('data.products.0.category.slug', 'tours');
    }

    public function test_globaltix_product_with_null_from_price_shows_null_from_price_and_official_original_price(): void
    {
        $product = Product::create([
            'product_api_id' => 'gt-null-from-price',
            'platform' => 'globaltix',
            'provider' => 'globaltix',
            'title' => 'GlobalTix Null FromPrice Tour',
            'slug' => 'globaltix-null-fromprice-tour',
            'status' => 'active',
            'published' => true,
            'default_currency' => 'EUR',
            'from_price' => null,
            'original_price' => 85.50,
        ]);

        // Test /api/v1/product/list
        $listResponse = $this->getJson('/api/v1/product/list');
        $listResponse->assertOk();
        $item = collect($listResponse->json('data'))->firstWhere('id', $product->id);
        $this->assertNotNull($item);
        $this->assertNull($item['from_price']);
        $this->assertNull($item['fromPrice']);
        $this->assertEquals(85.50, $item['original_price']);
        $this->assertEquals(85.50, $item['originalPrice']);

        // Test /api/v1/product/show/{id}
        $showResponse = $this->getJson('/api/v1/product/show/' . $product->id);
        $showResponse->assertOk();
        $this->assertNull($showResponse->json('data.from_price'));
        $this->assertNull($showResponse->json('data.fromPrice'));
        $this->assertEquals(85.50, $showResponse->json('data.original_price'));
        $this->assertEquals(85.50, $showResponse->json('data.originalPrice'));
    }
}
