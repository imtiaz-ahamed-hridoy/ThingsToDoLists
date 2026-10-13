<?php

namespace Tests\Feature;

use App\Models\AvailabilityCheck;
use App\Models\AvailabilityCategoryPrice;
use App\Models\Location;
use App\Models\OptionPricingCategory;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupplierWizardAndCreatorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Vendor $vendor;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test Supplier',
            'email' => 'supplier@test.com',
            'password' => bcrypt('password'),
            'role' => 'Vendor',
        ]);

        $this->vendor = Vendor::create([
            'user_id' => $this->user->id,
            'name' => 'Test Vendor Co.',
        ]);

        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    public function test_supplier_dashboard_landing_routes_are_available(): void
    {
        $this->createDraftProduct(4);

        $this->getJson('/api/v1/supplier-dashboard/home', $this->authHeaders())->assertOk();
        $this->getJson('/api/v1/supplier-dashboard/products', $this->authHeaders())->assertOk();
        $this->getJson('/api/v1/supplier-dashboard/bookings', $this->authHeaders())->assertOk();
        $this->getJson('/api/v1/supplier-dashboard/reviews', $this->authHeaders())->assertOk();
        $this->getJson('/api/v1/supplier-dashboard/finance', $this->authHeaders())->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'payouts',
                    'invoices',
                    'payment_confirmations',
                    'tax_details',
                ],
            ]);
        $this->getJson('/api/v1/supplier-dashboard/create-products', $this->authHeaders())->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'welcome',
                    'categories',
                    'languages',
                ],
            ]);
    }

    public function test_supplier_dashboard_uses_admin_managed_supplier_guide(): void
    {
        SystemSetting::create([
            'supplier_guide_title' => 'Supplier Guide From Admin',
            'supplier_guide_description' => 'Managed by admin settings.',
            'supplier_guide_link_or_iframe' => '<iframe src="https://example.com/embed"></iframe>',
        ]);

        $response = $this->getJson('/api/v1/supplier-dashboard/home', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.supplier_guide.title', 'Supplier Guide From Admin')
            ->assertJsonPath('data.supplier_guide.description', 'Managed by admin settings.')
            ->assertJsonPath('data.supplier_guide.link_or_iframe', '<iframe src="https://example.com/embed"></iframe>')
            ->assertJsonPath('data.supplier_guide.content_type', 'iframe');
    }

    public function test_unified_supplier_save_draft_creates_updates_and_loads_dashboard_draft(): void
    {
        $createDraft = $this->postJson('/api/v1/supplier-dashboard/create-products/save-draft', [
            'current_screen' => 'create-products',
            'language' => 'en',
        ], $this->authHeaders());

        $createDraft->assertOk()
            ->assertJsonPath('data.is_draft', true)
            ->assertJsonPath('data.wizard_step', 1);

        $draftId = $createDraft->json('data.draft_product_id');

        $updateDraft = $this->postJson("/api/v1/supplier-dashboard/create-products/save-draft/{$draftId}", [
            'current_screen' => 'customer-facing-title',
            'product_category' => 'tour',
            'product_title' => 'Unified Draft Product',
            'product_reference-Code' => 'UNIFIED-001',
        ], $this->authHeaders());

        $updateDraft->assertOk()
            ->assertJsonPath('data.draft_product_id', $draftId)
            ->assertJsonPath('data.wizard_step', 2)
            ->assertJsonPath('data.current_screen', 'customer-facing-title');

        $resumeDraft = $this->getJson("/api/v1/supplier-dashboard/create-products/draft/{$draftId}", $this->authHeaders());

        $resumeDraft->assertOk()
            ->assertJsonFragment(['title' => 'Unified Draft Product'])
            ->assertJsonFragment(['wizard_step' => 2])
            ->assertJsonFragment(['product_title' => 'Unified Draft Product']);

        $this->assertDatabaseHas('products', [
            'id' => $draftId,
            'title' => 'Unified Draft Product',
            'is_draft' => true,
        ]);
    }

    public function test_unified_supplier_submit_publishes_from_one_payload(): void
    {
        Storage::fake('public');

        $photos = [];
        for ($i = 0; $i < 4; $i++) {
            $photos[] = UploadedFile::fake()->image("unified{$i}.jpg", 1280, 720)->size(2000);
        }

        $response = $this->postJson('/api/v1/supplier-dashboard/create-products/submit', [
            'language' => 'en',
            'product_category' => 'tour',
            'content_mode' => 'manual',
            'product_title' => 'Unified One Payload Tour',
            'product_reference-Code' => 'ONE-API-001',
            'product_short_description' => 'Short introduction for the unified supplier tour.',
            'product_full_description' => str_repeat('This is a full product description for the unified supplier submission flow. ', 10),
            'hightlight1' => 'Instant confirmation',
            'hightlight2' => 'Hotel pickup option',
            'customer_visits' => ['Dubai, United Arab Emirates'],
            'products_keywords' => 'desert, safari, evening',
            'included-description' => "Guide\nHotel transfer\nBottled water",
            'extra_pay_description' => 'Personal expenses',
            'guide_type' => 'tour_guide',
            'food_include_activity' => 'yes',
            'meals' => [
                [
                    'type' => 'Dinner',
                    'format' => 'Buffet',
                    'drinkIncluded' => true,
                ],
            ],
            'transportation_include_activity' => 'yes',
            'transportOptions' => ['Bus'],
            'travel_in_city' => 'no',
            'suitable_for' => 'Under 18s',
            'not_allowed' => 'Pets',
            'mandatory_items' => 'Passport',
            'custom_information_before_booking' => 'Bring your confirmation email.',
            'other_information_before_booking' => 'Wear comfortable shoes.',
            'imageUploadConfirmation' => true,
            'photos' => $photos,
            'max_group_size' => 12,
            'languages' => ['English', 'Arabic'],
            'Private_activity' => 'no',
            'customerSkip' => 'yes',
            'skip_line_type' => 'Skip the line to get tickets',
            'activity_wheelchair' => 'yes',
            'activity_describe' => 'no',
            'addressList' => ['Dubai Marina - Hotel Lobby'],
            'meeting_point' => 'Meet at the main hotel entrance.',
            'arriveTime' => '15 minutes before the activity',
            'places' => 'At the same place you met them',
            'online_reservation' => 'no',
            'prices' => 'Price-per-group',
            'default_cut_off_time' => '15_minutes',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.is_draft', false)
            ->assertJsonPath('data.status', 'in_review')
            ->assertJsonPath('data.wizard_step', 16);

        $productId = $response->json('data.product_id');
        $product = Product::with(['locations', 'prices', 'keywords', 'hotelKeywords'])->findOrFail($productId);

        $this->assertFalse($product->is_draft);
        $this->assertEquals('in_review', $product->status);
        $this->assertEquals('Unified One Payload Tour', $product->title);
        $this->assertEquals('tour_guide', $product->guide_type);
        $this->assertEquals('same_for_everyone', $product->pricing_type);
        $this->assertCount(4, $product->images);
        $this->assertCount(1, $product->locations);
        $this->assertCount(1, $product->prices);
        $this->assertCount(3, $product->keywords);
        $this->assertCount(3, $product->hotelKeywords);
        $this->assertNotNull($product->category_id);
        $this->assertNotNull($product->raw_response);
        $this->assertSame('supplier', $product->raw_response->provider);
    }

    public function test_unified_supplier_submit_persists_normalized_relation_tables(): void
    {
        Storage::fake('public');

        $photos = [];
        for ($i = 0; $i < 4; $i++) {
            $photos[] = UploadedFile::fake()->image("relations{$i}.jpg", 1280, 720)->size(2000);
        }

        $response = $this->postJson('/api/v1/supplier-dashboard/create-products/submit', [
            'language' => 'en',
            'product_category' => 'activity',
            'content_mode' => 'manual',
            'product_title' => 'Supplier Relation Matrix',
            'product_reference-Code' => 'SUP-REL-001',
            'product_short_description' => 'Short introduction for relation persistence.',
            'product_full_description' => str_repeat('This payload checks normalized supplier relation persistence across dedicated tables. ', 10),
            'hightlight1' => 'Structured persistence',
            'hightlight2' => 'Raw payload snapshot',
            'customer_visits' => ['Dubai, United Arab Emirates'],
            'products_keywords' => 'desert, safari, evening',
            'included-description' => "Guide\nHotel transfer\nBottled water",
            'extra_pay_description' => 'Personal expenses',
            'guide_type' => 'tour_guide',
            'food_include_activity' => 'yes',
            'meals' => [
                [
                    'type' => 'Dinner',
                    'format' => 'Buffet',
                    'drinkIncluded' => true,
                ],
            ],
            'transportation_include_activity' => 'yes',
            'transportOptions' => ['Bus'],
            'travel_in_city' => 'no',
            'suitable_for' => 'Under 18s',
            'not_allowed' => 'Pets',
            'mandatory_items' => 'Passport',
            'custom_information_before_booking' => 'Bring your confirmation email.',
            'other_information_before_booking' => 'Wear comfortable shoes.',
            'imageUploadConfirmation' => true,
            'photos' => $photos,
            'max_group_size' => 12,
            'languages' => ['English', 'Arabic'],
            'Private_activity' => 'no',
            'customerSkip' => 'yes',
            'skip_line_type' => 'Skip the line to get tickets',
            'activity_wheelchair' => 'yes',
            'activity_describe' => 'no',
            'addressList' => ['Dubai Marina - Hotel Lobby'],
            'meeting_point' => 'Meet at the main hotel entrance.',
            'arriveTime' => '15 minutes before the activity',
            'places' => 'At the same place you met them',
            'online_reservation' => 'no',
            'prices' => 'Price-per-person',
            'default_cut_off_time' => '15_minutes',
            'barcodeType' => 'CODE_128',
            'age_categories' => [
                [
                    'name' => 'Adult',
                    'code' => 'ADULT',
                    'amount' => 120,
                    'ageFrom' => 12,
                    'ageTo' => 99,
                ],
                [
                    'name' => 'Child',
                    'code' => 'CHILD',
                    'amount' => 80,
                    'ageFrom' => 5,
                    'ageTo' => 11,
                ],
                [
                    'name' => 'Concession',
                    'code' => 'CONCESSION',
                    'amount' => 95,
                    'ageFrom' => 60,
                    'ageTo' => 99,
                ],
            ],
            'booking_questions' => [
                [
                    'questionCode' => 'hotel_name',
                    'label' => 'Hotel Name',
                    'required' => true,
                    'dataType' => 'SHORT_TEXT',
                    'options' => [],
                ],
            ],
            'passenger_fields' => [
                [
                    'field' => 'FIRST_NAME',
                    'required' => true,
                ],
            ],
            'hotel_contact_fields' => [
                [
                    'field' => 'PHONE_NUMBER',
                    'required' => true,
                ],
            ],
            'cancellation_policies' => [
                [
                    'policy' => 'Free cancellation up to 24 hours before start.',
                    'policyType' => 'STANDARD',
                    'details' => [
                        [
                            'cutoffHours' => 24,
                            'charge' => 0,
                            'chargeType' => 'percentage',
                            'percentage' => 0,
                        ],
                    ],
                ],
            ],
            'videos' => [
                [
                    'sourceUrl' => 'https://cdn.example.com/videos/supplier-tour.mp4',
                    'thumbnailUrl' => 'https://cdn.example.com/videos/supplier-tour.jpg',
                    'previewUrl' => 'https://cdn.example.com/videos/supplier-tour-preview.jpg',
                    'providerName' => 'supplier',
                    'title' => 'Supplier Tour',
                    'duration' => 90,
                ],
            ],
            'availability_checks' => [
                [
                    'id' => 'supplier-slot-1',
                    'date' => '2026-05-01',
                    'time' => '2026-05-01T09:00:00',
                    'total' => 12,
                    'available' => 9,
                    'ticket_type_id' => 501,
                    'ticket_name' => 'Per Group',
                    'price' => [
                        'amount' => 150,
                        'currency' => 'USD',
                    ],
                ],
            ],
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.is_draft', false)
            ->assertJsonPath('data.status', 'in_review');

        $productId = $response->json('data.product_id');
        $product = Product::with([
            'locations',
            'prices',
            'productImages',
            'productVideos',
            'options.pricingCategories',
            'keywords',
            'hotelKeywords',
            'cancellationPolicies',
            'availabilities.categoryPrices',
        ])->findOrFail($productId);

        $adultRow = $product->prices->firstWhere('category_code', 'ADULT');
        $categories = $product->options->flatMap(fn ($option) => $option->pricingCategories);
        $adultCategory = $categories->firstWhere('category_code', 'ADULT');
        $childCategory = $categories->firstWhere('category_code', 'CHILD');
        $concessionCategory = $categories->firstWhere('category_code', 'CONCESSION');

        $this->assertNotNull($product->category_id);
        $this->assertSame('CODE_128', $product->barcode_type);
        $this->assertCount(1, $product->locations);
        $this->assertCount(3, $product->prices);
        $this->assertNotNull($adultRow);
        $this->assertSame('supplier', $adultRow->provider);
        $this->assertSame('120.00', $adultRow->selling_price);
        $this->assertSame('USD', $adultRow->currency);
        $this->assertNotNull($adultCategory);
        $this->assertNotNull($childCategory);
        $this->assertNotNull($concessionCategory);
        $this->assertSame('120.00', $adultCategory->selling_price);
        $this->assertSame('80.00', $childCategory->selling_price);
        $this->assertSame('95.00', $concessionCategory->selling_price);
        $this->assertCount(3, $product->keywords);
        $this->assertCount(3, $product->hotelKeywords);
        $this->assertCount(1, $product->bookingQuestions);
        $this->assertCount(1, $product->passengerFields);
        $this->assertCount(1, $product->hotelContactFields);
        $this->assertCount(1, $product->cancellationPolicies);
        $this->assertCount(1, $product->cancellationPolicies->first()->details);
        $this->assertCount(1, $product->videos);
        $this->assertCount(4, $product->productImages);
        $this->assertCount(1, $product->productVideos);
        $this->assertCount(1, $product->availabilities);
        $this->assertCount(3, $product->availabilities->first()->categoryPrices);
        $this->assertNotNull($product->raw_response);
        $this->assertSame('supplier', $product->raw_response->provider);
        $this->assertSame('Supplier Relation Matrix', $product->raw_response->raw_data['formdata']['product_title']);
        $this->assertSame('hotel_name', $product->bookingQuestions->first()['questionCode']);
        $this->assertSame('PHONE_NUMBER', $product->hotelContactFields->first()['field']);
        $this->assertDatabaseHas('prices', [
            'product_id' => $productId,
            'category_code' => 'ADULT',
            'selling_price' => 120,
            'currency' => 'USD',
        ]);
        $this->assertSame('FIRST_NAME', $product->passengerFields->first()['field']);
        $this->assertDatabaseHas('availability', [
            'product_id' => $productId,
            'provider' => 'supplier',
            'external_id' => 'supplier-slot-1',
        ]);
        $this->assertDatabaseHas('availability_category_prices', [
            'availability_id' => $product->availabilities->first()->id,
            'category_code' => 'ADULT',
            'selling_price' => 120,
            'currency' => 'USD',
        ]);
        $this->assertDatabaseHas('availability_category_prices', [
            'availability_id' => $product->availabilities->first()->id,
            'category_code' => 'CHILD',
            'selling_price' => 80,
            'currency' => 'USD',
        ]);
        $this->assertDatabaseHas('availability_category_prices', [
            'availability_id' => $product->availabilities->first()->id,
            'category_code' => 'CONCESSION',
            'selling_price' => 95,
            'currency' => 'USD',
        ]);
    }

    public function test_supplier_availability_check_reads_local_normalized_rows_and_uses_category_fallbacks(): void
    {
        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'title' => 'Supplier Availability Product',
            'slug' => 'supplier-availability-product',
            'provider' => 'supplier',
            'platform' => 'supplier',
            'status' => 'active',
            'published' => true,
            'default_currency' => 'USD',
        ]);

        $option = ProductOption::create([
            'product_id' => $product->id,
            'provider' => 'supplier',
            'external_id' => 'default',
            'name' => 'Standard',
            'currency' => 'USD',
            'is_default' => true,
            'sort_order' => 0,
        ]);

        OptionPricingCategory::create([
            'product_option_id' => $option->id,
            'provider' => 'supplier',
            'idempotency_key' => md5('adult'),
            'external_id' => 'adult',
            'category_code' => 'ADULT',
            'category_label' => 'Adult',
            'selling_price' => 120,
            'currency' => 'USD',
            'min_purchase_qty' => 1,
            'max_purchase_qty' => 10,
            'sort_order' => 0,
        ]);

        OptionPricingCategory::create([
            'product_option_id' => $option->id,
            'provider' => 'supplier',
            'idempotency_key' => md5('child'),
            'external_id' => 'child',
            'category_code' => 'CHILD',
            'category_label' => 'Child',
            'selling_price' => 80,
            'currency' => 'USD',
            'min_purchase_qty' => 1,
            'max_purchase_qty' => 10,
            'sort_order' => 1,
        ]);

        $slot = AvailabilityCheck::create([
            'product_id' => $product->id,
            'product_option_id' => $option->id,
            'idempotency_key' => md5('supplier-slot-local'),
            'provider' => 'supplier',
            'external_id' => 'supplier-slot-local',
            'available_date' => '2026-05-01',
            'start_time' => '09:00:00',
            'start_at' => '2026-05-01 09:00:00',
            'capacity_total' => 12,
            'capacity_booked' => 3,
            'capacity_reserved' => 0,
            'display_price' => 80,
            'currency' => 'USD',
            'sold_out' => false,
            'is_active' => true,
            'is_closed' => false,
        ]);

        AvailabilityCategoryPrice::create([
            'availability_id' => $slot->id,
            'category_external_id' => 'legacy',
            'category_code' => 'GENERAL',
            'category_label' => 'Per Group',
            'selling_price' => 150,
            'currency' => 'USD',
            'available_qty' => 9,
        ]);

        $response = $this->postJson('/api/v1/availability/check', [
            'product_id' => $product->id,
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-01',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.provider', 'supplier')
            ->assertJsonPath('data.dates.0.date', '2026-05-01')
            ->assertJsonPath('data.dates.0.time_slots.0.start_time', '09:00:00');

        $categories = collect($response->json('data.dates.0.time_slots.0.categories'));

        $this->assertCount(2, $categories);
        $this->assertSame(['ADULT', 'CHILD'], $categories->pluck('code')->all());
        $this->assertSame([120.0, 80.0], $categories->pluck('selling_price')->map(fn ($value) => (float) $value)->all());
    }

    public function test_supplier_submit_returns_validation_error_for_text_photo_placeholders(): void
    {
        $response = $this->postJson('/api/v1/supplier-dashboard/create-products/submit', [
            'formdata' => [
                ['key' => 'language', 'value' => 'en', 'type' => 'text'],
                ['key' => 'product_category', 'value' => 'tour', 'type' => 'text'],
                ['key' => 'content_mode', 'value' => 'manual', 'type' => 'text'],
                ['key' => 'product_title', 'value' => 'Unified One Payload Tour', 'type' => 'text'],
                ['key' => 'product_reference-Code', 'value' => 'ONE-API-001', 'type' => 'text'],
                ['key' => 'product_short_description', 'value' => 'Short introduction for the unified supplier tour.', 'type' => 'text'],
                ['key' => 'product_full_description', 'value' => str_repeat('This is a full product description for the unified supplier submission flow. ', 3), 'type' => 'text'],
                ['key' => 'hightlight1', 'value' => 'Instant confirmation', 'type' => 'text'],
                ['key' => 'customer_visits', 'value' => 'Dubai, United Arab Emirates', 'type' => 'text'],
                ['key' => 'products_keywords', 'value' => 'desert, safari, evening', 'type' => 'text'],
                ['key' => 'included-description', 'value' => "Guide\nHotel transfer\nBottled water", 'type' => 'text'],
                ['key' => 'guide_type', 'value' => 'tour_guide', 'type' => 'text'],
                ['key' => 'food_include_activity', 'value' => 'no', 'type' => 'text'],
                ['key' => 'transportation_include_activity', 'value' => 'no', 'type' => 'text'],
                ['key' => 'imageUploadConfirmation', 'value' => 'true', 'type' => 'text'],
                ['key' => 'max_group_size', 'value' => '12', 'type' => 'text'],
                ['key' => 'languages[]', 'value' => 'English', 'type' => 'text'],
                ['key' => 'Private_activity', 'value' => 'no', 'type' => 'text'],
                ['key' => 'customerSkip', 'value' => 'no', 'type' => 'text'],
                ['key' => 'activity_wheelchair', 'value' => 'no', 'type' => 'text'],
                ['key' => 'activity_describe', 'value' => 'no', 'type' => 'text'],
                ['key' => 'addressList', 'value' => 'Dubai Marina - Hotel Lobby', 'type' => 'text'],
                ['key' => 'meeting_point', 'value' => 'Meet at the main hotel entrance.', 'type' => 'text'],
                ['key' => 'arriveTime', 'value' => '15 minutes before the activity', 'type' => 'text'],
                ['key' => 'places', 'value' => 'At the same place you met them', 'type' => 'text'],
                ['key' => 'online_reservation', 'value' => 'no', 'type' => 'text'],
                ['key' => 'prices', 'value' => 'Price-per-group', 'type' => 'text'],
                ['key' => 'default_cut_off_time', 'value' => '15_minutes', 'type' => 'text'],
                ['key' => 'photos[]', 'src' => 'C:\\fakepath\\not-an-upload.jpg', 'type' => 'file'],
            ],
        ], $this->authHeaders());

        $response->assertStatus(422)
            ->assertJsonPath('errors.photos.0', 'At least 1 product photo is required before submission.');
    }

    public function test_supplier_formdata_payload_is_flattened_and_saved_for_draft_creation(): void
    {
        $response = $this->postJson('/api/v1/supplier-dashboard/create-products/save-draft', [
            'formdata' => [
                ['key' => 'language', 'value' => 'en', 'type' => 'text'],
                ['key' => 'product_category', 'value' => 'tour', 'type' => 'text'],
                ['key' => 'content_mode', 'value' => 'manual', 'type' => 'text'],
                ['key' => 'product_title', 'value' => 'Unified One Payload Tour', 'type' => 'text'],
                ['key' => 'product_reference-Code', 'value' => 'ONE-API-001', 'type' => 'text'],
                ['key' => 'product_short_description', 'value' => 'Short introduction for the unified supplier tour.', 'type' => 'text'],
                ['key' => 'product_full_description', 'value' => 'This is a full product description for the unified supplier submission flow. This is a full product description for the unified supplier submission flow. This is a full product description for the unified supplier submission flow.', 'type' => 'text'],
                ['key' => 'hightlight1', 'value' => 'Instant confirmation', 'type' => 'text'],
                ['key' => 'hightlight2', 'value' => 'Hotel pickup option', 'type' => 'text'],
                ['key' => 'customer_visits', 'value' => 'Dubai, United Arab Emirates', 'type' => 'text'],
                ['key' => 'products_keywords', 'value' => 'desert, safari, evening', 'type' => 'text'],
                ['key' => 'included-description', 'value' => "Guide\nHotel transfer\nBottled water", 'type' => 'text'],
                ['key' => 'extra_pay_description', 'value' => 'Personal expenses', 'type' => 'text'],
                ['key' => 'guide_type', 'value' => 'tour_guide', 'type' => 'text'],
                ['key' => 'food_include_activity', 'value' => 'yes', 'type' => 'text'],
                ['key' => 'transportation_include_activity', 'value' => 'yes', 'type' => 'text'],
                ['key' => 'transportOptions', 'value' => 'Bus', 'type' => 'text'],
                ['key' => 'travel_in_city', 'value' => 'no', 'type' => 'text'],
                ['key' => 'suitable_for', 'value' => 'Under 18s', 'type' => 'text'],
                ['key' => 'not_allowed', 'value' => 'Pets', 'type' => 'text'],
                ['key' => 'mandatory_items', 'value' => 'Passport', 'type' => 'text'],
                ['key' => 'custom_information_before_booking', 'value' => 'Bring your confirmation email.', 'type' => 'text'],
                ['key' => 'other_information_before_booking', 'value' => 'Wear comfortable shoes.', 'type' => 'text'],
                ['key' => 'imageUploadConfirmation', 'value' => 'true', 'type' => 'text'],
                ['key' => 'max_group_size', 'value' => '12', 'type' => 'text'],
                ['key' => 'languages[]', 'value' => 'English', 'type' => 'text'],
                ['key' => 'languages[]', 'value' => 'Arabic', 'type' => 'text'],
                ['key' => 'Private_activity', 'value' => 'no', 'type' => 'text'],
                ['key' => 'customerSkip', 'value' => 'yes', 'type' => 'text'],
                ['key' => 'skip_line_type', 'value' => 'Skip the line to get tickets', 'type' => 'text'],
                ['key' => 'activity_wheelchair', 'value' => 'yes', 'type' => 'text'],
                ['key' => 'activity_describe', 'value' => 'no', 'type' => 'text'],
                ['key' => 'addressList', 'value' => 'Dubai Marina - Hotel Lobby', 'type' => 'text'],
                ['key' => 'meeting_point', 'value' => 'Meet at the main hotel entrance.', 'type' => 'text'],
                ['key' => 'arriveTime', 'value' => '15 minutes before the activity', 'type' => 'text'],
                ['key' => 'places', 'value' => 'At the same place you met them', 'type' => 'text'],
                ['key' => 'online_reservation', 'value' => 'no', 'type' => 'text'],
                ['key' => 'prices', 'value' => 'Price-per-group', 'type' => 'text'],
                ['key' => 'default_cut_off_time', 'value' => '15_minutes', 'type' => 'text'],
                ['key' => 'photos[]', 'src' => [], 'type' => 'file'],
                ['key' => 'photos[]', 'src' => [], 'type' => 'file'],
                ['key' => 'photos[]', 'src' => [], 'type' => 'file'],
                ['key' => 'photos[]', 'src' => [], 'type' => 'file'],
            ],
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.is_draft', true);

        $draftId = $response->json('data.draft_product_id');
        $product = Product::with(['locations', 'prices', 'keywords', 'hotelKeywords'])->findOrFail($draftId);

        $this->assertSame('Unified One Payload Tour', $product->title);
        $this->assertSame('ONE-API-001', $product->reference_code);
        $this->assertSame('tour_guide', $product->guide_type);
        $this->assertSame('same_for_everyone', $product->pricing_type);
        $this->assertSame('manual', $product->reservation_system);
        $this->assertCount(1, $product->locations);
        $this->assertCount(1, $product->prices);
        $this->assertCount(3, $product->keywords);
        $this->assertCount(3, $product->hotelKeywords);
        $this->assertSame(['English', 'Arabic'], $product->options_configuration['languages']);
        $this->assertSame(12, $product->options_configuration['max_group_size']);
        $this->assertTrue($product->copyright_acknowledged);
        $this->assertNotNull($product->raw_response);
        $this->assertSame('supplier', $product->raw_response->provider);
        $this->assertSame('Unified One Payload Tour', $product->raw_response->raw_data['formdata']['product_title']);
        $this->assertCount(43, $product->raw_response->raw_data['formdata']['formdata_entries']);
    }

    public function test_old_multistep_supplier_create_routes_are_not_available(): void
    {
        $draft = $this->createDraftProduct(3);

        $this->postJson('/api/v1/supplier/products/create/step1', [
            'language' => 'en',
            'product_category' => 'tour',
        ], $this->authHeaders())->assertNotFound();

        $this->getJson('/api/v1/supplier/products/create/categories', $this->authHeaders())->assertNotFound();
        $this->postJson("/api/v1/supplier/products/create/{$draft->id}/save-exit", [], $this->authHeaders())->assertNotFound();
        $this->getJson("/api/v1/supplier/products/create/{$draft->id}/resume", $this->authHeaders())->assertNotFound();

        $this->postJson('/api/v1/supplier-dashboard/create/customer-facing-title', [
            'draft_product_id' => $draft->id,
            'product_title' => 'Removed Route',
        ], $this->authHeaders())->assertNotFound();

        $this->postJson('/api/v1/supplier-dashboard/create/stepper/submit', [
            'draft_product_id' => $draft->id,
        ], $this->authHeaders())->assertNotFound();
    }

    public function test_duplicate_supplier_alias_routes_are_not_available(): void
    {
        $this->getJson('/api/v1/supplier/dashboard', $this->authHeaders())->assertNotFound();
        $this->getJson('/api/v1/supplier/bookings', $this->authHeaders())->assertNotFound();
        $this->getJson('/api/v1/supplier/products', $this->authHeaders())->assertNotFound();
        $this->getJson('/api/v1/supplier/reviews', $this->authHeaders())->assertNotFound();
        $this->postJson('/api/v1/supplier/products/create/draft', [
            'language' => 'en',
            'product_category' => 'tour',
        ], $this->authHeaders())->assertNotFound();
    }

    public function test_creator_community_application(): void
    {
        $creatorUser = User::create([
            'name' => 'Test Creator',
            'email' => 'creator@test.com',
            'password' => bcrypt('password'),
            'role' => 'ContentCreator',
        ]);

        $creatorToken = $creatorUser->createToken('test')->plainTextToken;

        $response = $this->postJson('/api/v1/creator-portal/global-application', [
            'name' => 'John Creator',
            'email' => 'john@creator.com',
            'instagram_handle' => 'johncreator',
            'instagram_followers' => 50000,
            'tiktok_handle' => 'johncreator',
            'youtube_handle' => 'johncreator',
            'has_base_location' => true,
            'city' => 'Berlin',
            'country' => 'Germany',
            'discovered_via' => 'referred_by_friend',
            'referred_by' => 'Travel Creator Friend',
        ], ['Authorization' => 'Bearer ' . $creatorToken]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'submitted');
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    private function createDraftProduct(int $wizardStep = 1): Product
    {
        return Product::create([
            'vendor_id' => $this->vendor->id,
            'title' => '',
            'slug' => 'draft-' . \Illuminate\Support\Str::uuid(),
            'language' => 'en',
            'product_category' => 'tour',
            'provider' => 'manual',
            'platform' => 'manual',
            'pickup_service' => 'NOT_AVAILABLE',
            'private_tour' => 'NOT_AVAILABLE',
            'status' => 'draft',
            'is_draft' => true,
            'wizard_step' => $wizardStep,
        ]);
    }
}
