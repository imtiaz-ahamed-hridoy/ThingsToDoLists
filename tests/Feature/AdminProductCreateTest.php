<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProductCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_product_create_form_uses_current_schema(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/product/create');

        $response->assertOk()
            ->assertSee('product-editor-page', false)
            ->assertSee('Form sections')
            ->assertSee('Create Product')
            ->assertSee('Barcode Type')
            ->assertSee('Product Images')
            ->assertSee('name="vendor[currency]"', false)
            ->assertSee('Add activity tags, highlights, inclusions, and exclusions under this card.')
            ->assertSee('name="activityAttributes[]"', false)
            ->assertSee('name="highlights[]"', false)
            ->assertSee('name="inclusions[]"', false)
            ->assertSee('name="exclusions[]"', false)
            ->assertDontSee('activityAttributesJson', false)
            ->assertDontSee('use_existing_barcode', false)
            ->assertDontSee('Select Existing Barcode Type');
    }

    public function test_admin_product_edit_form_uses_modern_editor_shell(): void
    {
        $product = Product::create([
            'provider' => 'supplier',
            'platform' => 'supplier',
            'language' => 'en',
            'title' => 'Editable Nile Cruise',
            'slug' => 'editable-nile-cruise',
            'status' => 'active',
            'published' => true,
            'activityAttributes' => ['Family Friendly'],
            'highlights' => ['Nile views'],
            'inclusions' => ['Guide'],
            'exclusions' => ['Tips'],
        ]);

        $response = $this->actingAs($this->admin())->get(route('product.edit', $product));

        $response->assertOk()
            ->assertSee('product-editor-page', false)
            ->assertSee('Form sections')
            ->assertSee('Save Changes')
            ->assertSee('Editable Nile Cruise')
            ->assertSee('href="#section-pricing"', false)
            ->assertSee('name="activityAttributes[]"', false)
            ->assertDontSee('activityAttributesJson', false);
    }

    public function test_admin_can_store_product_using_current_product_migrations(): void
    {
        Storage::fake('public');

        $rates = [
            'passengers' => [
                [
                    'type' => 'ADULT',
                    'price' => ['amount' => 49, 'currency' => 'USD'],
                    'ageRange' => ['min' => 18, 'max' => 99],
                    'tieredPrices' => [
                        ['minPassengersRequired' => 4, 'amount' => 44, 'currency' => 'USD'],
                    ],
                ],
            ],
            'pricesByDateRange' => [],
        ];

        $payload = [
            'use_existing_category' => '0',
            'category' => [
                'name' => 'Cruises',
                'slug' => 'cruises',
                'type' => 'things_to_do',
            ],
            'use_existing_vendor' => '0',
            'vendor' => [
                'name' => 'Manual Supplier',
                'currency' => 'USD',
                'rating' => '4.5',
                'reviews' => '12',
                'link' => 'https://example.test',
            ],
            'provider' => 'supplier',
            'platform' => 'supplier',
            'barcode_type' => 'QR_CODE',
            'language' => 'en',
            'title' => 'Nile River Cruise',
            'description' => 'Manual product description',
            'short_introduction' => 'A short intro',
            'published' => '1',
            'status' => 'active',
            'slug' => 'nile-river-cruise',
            'reviewRating' => '4.7',
            'reviewCount' => '35',
            'activityType' => 'TOUR',
            'bookingType' => 'SHARED',
            'allowCustomizedBookings' => '0',
            'passportRequired' => '0',
            'is_instant_confirmation' => '1',
            'is_cancellable' => '1',
            'online_reservation' => '1',
            'location' => [
                'city' => 'Cairo',
                'countryCode' => 'EG',
                'country' => 'Egypt',
                'coordinates' => json_encode(['latitude' => '30.0444', 'longitude' => '31.2357']),
            ],
            'price' => [
                'defaultCurrency' => 'USD',
                'isPriceConverted' => '0',
                'amount' => '49',
                'rates' => json_encode($rates),
            ],
            'passenger_fields' => json_encode([
                ['field' => 'first_name', 'required' => true, 'requiredBeforeDeparture' => false],
            ]),
            'hotel_contact_fields' => json_encode([
                ['field' => 'hotel_name', 'required' => false, 'requiredBeforeDeparture' => false],
            ]),
            'keywords' => json_encode(['cruise']),
            'activityAttributes' => [' Family Friendly ', ''],
            'highlights' => [' Nile views ', ''],
            'inclusions' => [' Guide ', ''],
            'exclusions' => ['', 'Tips'],
            'itinerary' => json_encode([
                ['title' => ' Arrival ', 'description' => ' Meet the guide ', 'location' => ' Cairo '],
                ['title' => '', 'description' => '', 'location' => ''],
            ]),
            'videos_data' => json_encode([]),
            'images' => [
                [
                    'original' => UploadedFile::fake()->image('nile.jpg', 20, 20),
                ],
            ],
        ];

        $response = $this->actingAs($this->admin())->post('/admin/product/store', $payload);

        $response->assertRedirect(route('product.index'));

        $product = Product::query()
            ->with(['productImages', 'options.pricingCategories.tieredPrices', 'prices', 'keywords'])
            ->where('slug', 'nile-river-cruise')
            ->firstOrFail();

        $this->assertSame('supplier', $product->provider);
        $this->assertSame('QR_CODE', $product->barcode_type);
        $this->assertSame('active', $product->status);
        $this->assertTrue($product->published);
        $this->assertFalse($product->is_draft);
        $this->assertSame('49.00', (string) $product->from_price);
        $this->assertSame('USD', $product->default_currency);
        $this->assertSame('first_name', $product->passenger_fields_data[0]['field']);
        $this->assertSame('hotel_name', $product->contact_fields_data[0]['field']);
        $this->assertSame(['Family Friendly'], $product->activityAttributes);
        $this->assertSame(['Nile views'], $product->highlights);
        $this->assertSame(['Guide'], $product->inclusions);
        $this->assertSame(['Tips'], $product->exclusions);
        $this->assertSame([
            ['title' => 'Arrival', 'description' => 'Meet the guide', 'location' => 'Cairo'],
        ], $product->itinerary);
        $this->assertCount(1, $product->productImages);
        $this->assertCount(1, $product->options);
        $this->assertCount(1, $product->options->first()->pricingCategories);
        $this->assertCount(1, $product->options->first()->pricingCategories->first()->tieredPrices);
        $this->assertCount(1, $product->prices);
        $this->assertSame('cruise', $product->keywords->first()->name);
    }

    public function test_admin_store_accepts_supplier_bokun_and_globaltix_provider_modes(): void
    {
        Storage::fake('public');

        foreach (['supplier', 'bokun', 'globaltix'] as $provider) {
            $slug = $provider . '-migration-aligned-product';

            $response = $this->actingAs($this->admin())->post('/admin/product/store', [
                'use_existing_category' => '0',
                'category' => [
                    'name' => ucfirst($provider) . ' Tickets',
                    'slug' => $slug . '-category',
                    'type' => 'things_to_do',
                ],
                'use_existing_vendor' => '0',
                'vendor' => [
                    'name' => ucfirst($provider) . ' Vendor',
                    'currency' => 'USD',
                ],
                'provider' => $provider,
                'platform' => $provider,
                'product_api_id' => $provider . '-api-1',
                'reference_code' => $provider . '-ref-1',
                'barcode_type' => 'QR_CODE',
                'language' => 'en',
                'title' => ucfirst($provider) . ' Migration Aligned Product',
                'description' => 'Provider alignment smoke test',
                'published' => '1',
                'status' => 'active',
                'slug' => $slug,
                'activityType' => 'TOUR',
                'bookingType' => 'SHARED',
                'allowCustomizedBookings' => '0',
                'passportRequired' => '0',
                'is_instant_confirmation' => '1',
                'is_cancellable' => '1',
                'online_reservation' => '1',
                'location' => [
                    'city' => 'Cairo',
                    'countryCode' => 'EG',
                    'country' => 'Egypt',
                    'coordinates' => json_encode(['latitude' => '30.0444', 'longitude' => '31.2357']),
                ],
                'price' => [
                    'defaultCurrency' => 'USD',
                    'isPriceConverted' => '0',
                    'amount' => '25',
                    'rates' => json_encode([
                        'passengers' => [
                            [
                                'type' => 'ADULT',
                                'price' => ['amount' => 25, 'currency' => 'USD'],
                                'ageRange' => ['min' => 18, 'max' => 99],
                            ],
                        ],
                        'pricesByDateRange' => [],
                    ]),
                ],
                'passenger_fields' => json_encode([]),
                'hotel_contact_fields' => json_encode([]),
                'keywords' => json_encode([$provider]),
                'videos_data' => json_encode([]),
                'images' => [
                    [
                        'original' => UploadedFile::fake()->image($provider . '.jpg', 20, 20),
                    ],
                ],
            ]);

            $response->assertRedirect(route('product.index'));

            $product = Product::query()
                ->with(['productImages', 'options.pricingCategories', 'prices'])
                ->where('slug', $slug)
                ->firstOrFail();

            $this->assertSame($provider, $product->provider);
            $this->assertSame($provider, $product->platform);
            $this->assertSame($provider . '-api-1', $product->product_api_id);
            $this->assertCount(1, $product->productImages);
            $this->assertCount(1, $product->options);
            $this->assertCount(1, $product->options->first()->pricingCategories);
            $this->assertCount(1, $product->prices);
        }
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
