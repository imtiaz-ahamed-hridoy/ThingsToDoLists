<?php

namespace Tests\Feature;

use App\Models\BookingInfo;
use App\Models\Product;
use App\Models\User;
use App\Services\Bokun\BokunService;
use App\Services\GlobalTix\GlobalTixBookingService;
use App\Services\Payments\StripeCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBookingCreationTest extends TestCase
{
    use RefreshDatabase;

    // ==================== HELPERS ====================

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'Admin',
            'is_agree' => true,
        ]);
    }

    private function nonAdmin(): User
    {
        return User::factory()->create([
            'role' => 'User',
            'is_agree' => true,
        ]);
    }

    private function createProduct(string $provider = 'bokun', string $apiId = 'api-123', float $price = 99.99): Product
    {
        return Product::create([
            'product_api_id' => $apiId,
            'provider' => $provider,
            'platform' => $provider,
            'title' => 'Test ' . ucfirst($provider) . ' Activity',
            'slug' => 'test-' . $provider . '-activity-' . uniqid(),
            'short_introduction' => 'A great test activity',
            'status' => 'active',
            'published' => true,
            'from_price' => $price,
            'default_currency' => 'EUR',
            'language' => 'en',
            'vendor_id' => null,
        ]);
    }

    // ==================== SEARCH PRODUCTS ====================

    public function test_admin_can_search_products(): void
    {
        $admin = $this->admin();
        $product = $this->createProduct('bokun', 'bokun-101', 150.00);

        $response = $this->actingAs($admin)->json('GET', '/admin/bookings/search-products', [
            'q' => 'Bokun',
            'provider' => 'bokun',
            'limit' => 10,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Products retrieved successfully.')
            ->assertJsonFragment(['id' => $product->id])
            ->assertJsonFragment(['title' => $product->title])
            ->assertJsonFragment(['product_api_id' => 'bokun-101']);
    }

    public function test_search_products_returns_empty_for_no_match(): void
    {
        $admin = $this->admin();
        $this->createProduct('globaltix', 'gt-999', 75.00);

        $response = $this->actingAs($admin)->json('GET', '/admin/bookings/search-products', [
            'q' => 'NonexistentProductXYZ',
            'provider' => 'all',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Products retrieved successfully.')
            ->assertJsonCount(0, 'data');
    }

    public function test_search_products_filters_by_provider(): void
    {
        $admin = $this->admin();
        $bokunProduct = $this->createProduct('bokun', 'bokun-f1', 100.00);
        $globaltixProduct = $this->createProduct('globaltix', 'gt-f1', 80.00);

        $response = $this->actingAs($admin)->json('GET', '/admin/bookings/search-products', [
            'q' => 'Test',
            'provider' => 'bokun',
        ]);

        $response->assertOk();
        $productIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($bokunProduct->id, $productIds);
        $this->assertNotContains($globaltixProduct->id, $productIds);
    }

    public function test_search_products_requires_minimum_query_length(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->json('GET', '/admin/bookings/search-products', [
            'q' => 'A',  // too short (min:2)
        ]);

        $response->assertStatus(422);
    }

    public function test_search_products_rejects_non_admin(): void
    {
        $user = $this->nonAdmin();

        $response = $this->actingAs($user)->json('GET', '/admin/bookings/search-products', [
            'q' => 'Test',
        ]);

        $response->assertRedirect();
    }

    public function test_search_products_rejects_guest(): void
    {
        $response = $this->json('GET', '/admin/bookings/search-products', [
            'q' => 'Test',
        ]);

        $response->assertRedirect();
    }

    // ==================== TICKET TYPES ====================

    public function test_admin_can_get_ticket_types_for_globaltix_product(): void
    {
        $admin = $this->admin();
        $product = $this->createProduct('globaltix', 'gt-tt-1', 80.00);

        $option = \App\Models\ProductOption::create([
            'product_id' => $product->id,
            'provider' => 'globaltix',
            'external_id' => 'opt-1',
            'name' => 'Standard Ticket',
            'is_default' => true,
        ]);

        $category = \App\Models\OptionPricingCategory::create([
            'product_option_id' => $option->id,
            'provider' => 'globaltix',
            'external_id' => '256022',
            'category_label' => 'Adult',
            'selling_price' => 80.00,
            'currency' => 'EUR',
            'is_default' => true,
            'sort_order' => 1,
            'age_from' => 18,
            'age_to' => 99,
        ]);

        \App\Models\OptionPricingCategory::create([
            'product_option_id' => $option->id,
            'provider' => 'globaltix',
            'external_id' => '256023',
            'category_label' => 'Child',
            'selling_price' => 40.00,
            'currency' => 'EUR',
            'is_default' => false,
            'sort_order' => 2,
            'age_from' => 4,
            'age_to' => 17,
        ]);

        $response = $this->actingAs($admin)->json('GET', '/admin/bookings/ticket-types/' . $product->id);

        $response->assertOk()
            ->assertJsonPath('message', 'Ticket types retrieved successfully.')
            ->assertJsonPath('data.product_id', $product->id)
            ->assertJsonPath('data.provider', 'globaltix')
            ->assertJsonCount(2, 'data.ticket_types');

        $ticketTypes = $response->json('data.ticket_types');
        $this->assertSame('Adult', $ticketTypes[0]['label']);
        $this->assertSame('Child', $ticketTypes[1]['label']);
        $this->assertTrue($ticketTypes[0]['is_default']);
        $this->assertSame(80.00, (float) $ticketTypes[0]['price']);
    }

    public function test_get_ticket_types_returns_empty_for_product_without_categories(): void
    {
        $admin = $this->admin();
        $product = $this->createProduct('globaltix', 'gt-no-cats', 50.00);

        $response = $this->actingAs($admin)->json('GET', '/admin/bookings/ticket-types/' . $product->id);

        $response->assertOk()
            ->assertJsonCount(0, 'data.ticket_types');
    }

    public function test_get_ticket_types_falls_back_to_direct_external_id_lookup(): void
    {
        $admin = $this->admin();
        // This product has categories linked DIRECTLY by external_id matching product_api_id,
        // without any ProductOption records (the fallback path in getTicketTypes()).
        $product = $this->createProduct('globaltix', 'gt-api-123', 65.00);

        // Create categories WITHOUT a ProductOption — external_id matches product's product_api_id
        \App\Models\OptionPricingCategory::create([
            'product_option_id' => null,
            'provider' => 'globaltix',
            'external_id' => 'gt-api-123',  // matches $product->product_api_id
            'category_label' => 'Standard Adult',
            'selling_price' => 65.00,
            'currency' => 'EUR',
            'is_default' => true,
            'sort_order' => 1,
            'age_from' => 18,
            'age_to' => 99,
        ]);

        \App\Models\OptionPricingCategory::create([
            'product_option_id' => null,
            'provider' => 'globaltix',
            'external_id' => 'gt-api-123',  // matches $product->product_api_id
            'category_label' => 'Standard Child',
            'selling_price' => 32.50,
            'currency' => 'EUR',
            'is_default' => false,
            'sort_order' => 2,
            'age_from' => 4,
            'age_to' => 17,
        ]);

        // Create an unrelated category with a different external_id — should NOT be returned
        \App\Models\OptionPricingCategory::create([
            'product_option_id' => null,
            'provider' => 'globaltix',
            'external_id' => 'other-product-id',
            'category_label' => 'VIP Pass',
            'selling_price' => 200.00,
            'currency' => 'EUR',
            'is_default' => false,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($admin)->json('GET', '/admin/bookings/ticket-types/' . $product->id);

        $response->assertOk()
            ->assertJsonPath('message', 'Ticket types retrieved successfully.')
            ->assertJsonPath('data.product_id', $product->id)
            ->assertJsonPath('data.provider', 'globaltix')
            ->assertJsonCount(2, 'data.ticket_types');  // only the 2 matching, not the unrelated one

        $ticketTypes = $response->json('data.ticket_types');
        $this->assertSame('Standard Adult', $ticketTypes[0]['label']);
        $this->assertSame('Standard Child', $ticketTypes[1]['label']);
        $this->assertTrue($ticketTypes[0]['is_default']);
        $this->assertFalse($ticketTypes[1]['is_default']);
        $this->assertSame(65.00, (float) $ticketTypes[0]['price']);
        $this->assertSame(32.50, (float) $ticketTypes[1]['price']);

        // Verify the unrelated category is NOT included
        $labels = array_column($ticketTypes, 'label');
        $this->assertNotContains('VIP Pass', $labels);
    }

    public function test_get_ticket_types_returns_404_for_non_existent_product(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->json('GET', '/admin/bookings/ticket-types/99999');

        $response->assertStatus(404);
    }

    public function test_get_ticket_types_rejects_non_admin(): void
    {
        $product = $this->createProduct('globaltix', 'gt-auth-1', 60.00);
        $user = $this->nonAdmin();

        $response = $this->actingAs($user)->json('GET', '/admin/bookings/ticket-types/' . $product->id);

        $response->assertRedirect();
    }

    // ==================== CREATE BOOKING (Skip Provider) ====================

    public function test_admin_can_create_booking_without_provider_api(): void
    {
        $admin = $this->admin();
        $product = $this->createProduct('bokun', 'bokun-c1', 120.00);

        $response = $this->actingAs($admin)->postJson('/admin/bookings/create-booking', [
            'provider' => 'bokun',
            'product_id' => $product->id,
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'customer_phone' => '+1234567890',
            'quantity' => 2,
            'visit_date' => now()->addDays(7)->format('Y-m-d'),
            'visit_time' => '10:00',
            'skip_provider' => true,
            'admin_note' => 'Test admin booking',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Booking created successfully.')
            ->assertJsonStructure(['data' => ['booking', 'reference']]);

        $reference = $response->json('data.reference');
        $this->assertStringStartsWith('ADMIN-', $reference);

        $this->assertDatabaseHas('booking_info', [
            'reference' => $reference,
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'customer_phone' => '+1234567890',
            'product_id' => $product->id,
            'status' => 'RESERVED',
            'provider_type' => 'bokun',
            'quantity' => 2,
            'admin_note' => 'Test admin booking',
        ]);
    }

    public function test_admin_can_create_booking_with_paid_status_override(): void
    {
        $admin = $this->admin();
        $product = $this->createProduct('globaltix', 'gt-c2', 200.00);

        $response = $this->actingAs($admin)->postJson('/admin/bookings/create-booking', [
            'provider' => 'globaltix',
            'product_id' => $product->id,
            'customer_name' => 'Jane Smith',
            'customer_email' => 'jane@example.com',
            'quantity' => 1,
            'visit_date' => now()->addDays(3)->format('Y-m-d'),
            'status_override' => 'PAID',
            'skip_provider' => true,
        ]);

        $response->assertOk();

        $reference = $response->json('data.reference');
        $this->assertDatabaseHas('booking_info', [
            'reference' => $reference,
            'status' => 'PAID',
            'customer_name' => 'Jane Smith',
        ]);

        // paid_at should be set when status override is PAID
        $booking = BookingInfo::where('reference', $reference)->first();
        $this->assertNotNull($booking->paid_at);
    }

    public function test_admin_can_create_booking_with_confirmed_status(): void
    {
        $admin = $this->admin();
        $product = $this->createProduct('bokun', 'bokun-c3', 99.50);

        $response = $this->actingAs($admin)->postJson('/admin/bookings/create-booking', [
            'provider' => 'bokun',
            'product_id' => $product->id,
            'customer_name' => 'Confirmed Customer',
            'customer_email' => 'confirmed@example.com',
            'quantity' => 3,
            'visit_date' => now()->addDays(14)->format('Y-m-d'),
            'status_override' => 'CONFIRMED',
            'skip_provider' => true,
        ]);

        $response->assertOk();

        $reference = $response->json('data.reference');
        $this->assertDatabaseHas('booking_info', [
            'reference' => $reference,
            'status' => 'CONFIRMED',
        ]);

        // confirmed_at should be set when status override is CONFIRMED
        $booking = BookingInfo::where('reference', $reference)->first();
        $this->assertNotNull($booking->confirmed_at);
    }

    // ==================== CREATE BOOKING (With Mocked Provider) ====================

    public function test_create_booking_with_mocked_bokun_provider(): void
    {
        $admin = $this->admin();
        $product = $this->createProduct('bokun', 'bokun-m1', 150.00);

        $bokunMock = $this->createMock(BokunService::class);
        $bokunMock->expects($this->once())
            ->method('submitDirectCheckout')
            ->willReturn([
                'bookingHash' => 'ref_bokun_admin_1',
                'booking' => [
                    'confirmationCode' => 'CONF-BOKUN-ADMIN-1',
                    'totalDue' => 150.00,
                    'totalPrice' => 150.00,
                    'currency' => 'EUR',
                    'productBookings' => [
                        [
                            'bookingId' => 128004767,
                            'productConfirmationCode' => 'BOKUN-T128004767',
                            'productTitle' => 'Test Bokun Activity',
                            'totalPrice' => 150.00,
                            'quantity' => 1,
                            'date' => now()->addDays(7)->format('Y-m-d'),
                        ],
                    ],
                ],
            ]);
        $this->app->instance(BokunService::class, $bokunMock);

        $response = $this->actingAs($admin)->postJson('/admin/bookings/create-booking', [
            'provider' => 'bokun',
            'product_id' => $product->id,
            'customer_name' => 'Bokun Customer',
            'customer_email' => 'bokun@example.com',
            'customer_phone' => '+1987654321',
            'quantity' => 1,
            'visit_date' => now()->addDays(7)->format('Y-m-d'),
            'visit_time' => '09:30',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Booking created successfully.');

        // After Bokun response, reference and confirmation_code get updated
        $booking = BookingInfo::where('reference', 'ref_bokun_admin_1')->first();
        $this->assertNotNull($booking);
        $this->assertSame('CONF-BOKUN-ADMIN-1', $booking->confirmation_code);
        $this->assertSame('BOKUN-T128004767', $booking->product_confirmation_code);
        $this->assertSame('bokun', $booking->provider_type);
    }

    public function test_create_booking_with_mocked_globaltix_provider(): void
    {
        $admin = $this->admin();
        $product = $this->createProduct('globaltix', 'gt-m1', 85.00);

        $globaltixMock = $this->createMock(GlobalTixBookingService::class);
        $globaltixMock->expects($this->once())
            ->method('reserveBooking')
            ->willReturn([
                'success' => true,
                'data' => [
                    'referenceNumber' => 'GT-ADMIN-RES-1',
                    'bookingTime' => now()->toIso8601String(),
                    'amount' => 85.00,
                    'customer' => 'GlobalTix Customer',
                    'email' => 'globaltix@example.com',
                    'status' => 'RESERVED',
                ],
                'error' => null,
            ]);
        $this->app->instance(GlobalTixBookingService::class, $globaltixMock);

        $response = $this->actingAs($admin)->postJson('/admin/bookings/create-booking', [
            'provider' => 'globaltix',
            'product_id' => $product->id,
            'customer_name' => 'GlobalTix Customer',
            'customer_email' => 'globaltix@example.com',
            'customer_phone' => '+1122334455',
            'quantity' => 2,
            'visit_date' => now()->addDays(5)->format('Y-m-d'),
            'visit_time' => '14:00',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Booking created successfully.');

        // After GlobalTix response, confirmation_code should be updated
        $booking = BookingInfo::where('reference', 'GT-ADMIN-RES-1')->first();
        $this->assertNotNull($booking);
        $this->assertSame('GT-ADMIN-RES-1', $booking->confirmation_code);
        $this->assertSame('globaltix', $booking->provider_type);
        $this->assertSame(2, $booking->quantity);
    }

    public function test_create_booking_handles_provider_failure_gracefully(): void
    {
        $admin = $this->admin();
        $product = $this->createProduct('globaltix', 'gt-fail-1', 60.00);

        $globaltixMock = $this->createMock(GlobalTixBookingService::class);
        $globaltixMock->expects($this->once())
            ->method('reserveBooking')
            ->willReturn([
                'success' => false,
                'error' => [
                    'message' => 'No availability for selected date',
                ],
            ]);
        $this->app->instance(GlobalTixBookingService::class, $globaltixMock);

        $response = $this->actingAs($admin)->postJson('/admin/bookings/create-booking', [
            'provider' => 'globaltix',
            'product_id' => $product->id,
            'customer_name' => 'Fail Customer',
            'customer_email' => 'fail@example.com',
            'quantity' => 1,
            'visit_date' => now()->addDays(1)->format('Y-m-d'),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.needs_attention', true)
            ->assertJsonPath('data.provider_error', 'GlobalTix Error: No availability for selected date');

        // Booking should still be created locally but marked as failed
        $reference = $response->json('data.reference');
        $this->assertDatabaseHas('booking_info', [
            'reference' => $reference,
            'status' => 'FAILED',
            'admin_status' => 'needs_attention',
            'failure_stage' => 'globaltix_admin_create',
        ]);
    }

    // ==================== VALIDATION ====================

    public function test_create_booking_requires_provider(): void
    {
        $admin = $this->admin();
        $product = $this->createProduct();

        $response = $this->actingAs($admin)->postJson('/admin/bookings/create-booking', [
            'product_id' => $product->id,
            'customer_name' => 'Test',
            'customer_email' => 'test@test.com',
            'quantity' => 1,
            'visit_date' => now()->addDays(1)->format('Y-m-d'),
            'skip_provider' => true,
        ]);

        $response->assertStatus(422);
    }

    public function test_create_booking_requires_valid_customer_name_and_email(): void
    {
        $admin = $this->admin();
        $product = $this->createProduct();

        $response = $this->actingAs($admin)->postJson('/admin/bookings/create-booking', [
            'provider' => 'bokun',
            'product_id' => $product->id,
            'customer_name' => '',
            'customer_email' => 'not-an-email',
            'quantity' => 1,
            'visit_date' => now()->addDays(1)->format('Y-m-d'),
            'skip_provider' => true,
        ]);

        $response->assertStatus(422);
    }

    public function test_create_booking_requires_valid_date_format(): void
    {
        $admin = $this->admin();
        $product = $this->createProduct();

        $response = $this->actingAs($admin)->postJson('/admin/bookings/create-booking', [
            'provider' => 'globaltix',
            'product_id' => $product->id,
            'customer_name' => 'Date Test',
            'customer_email' => 'date@test.com',
            'quantity' => 1,
            'visit_date' => 'invalid-date',
            'skip_provider' => true,
        ]);

        $response->assertStatus(422);
    }

    public function test_create_booking_requires_product_id(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson('/admin/bookings/create-booking', [
            'provider' => 'bokun',
            'customer_name' => 'No Product',
            'customer_email' => 'noproduct@test.com',
            'quantity' => 1,
            'visit_date' => now()->addDays(1)->format('Y-m-d'),
            'skip_provider' => true,
        ]);

        $response->assertStatus(422);
    }

    public function test_create_booking_requires_valid_product_id(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson('/admin/bookings/create-booking', [
            'provider' => 'bokun',
            'product_id' => 99999,
            'customer_name' => 'Bad Product',
            'customer_email' => 'badproduct@test.com',
            'quantity' => 1,
            'visit_date' => now()->addDays(1)->format('Y-m-d'),
            'skip_provider' => true,
        ]);

        $response->assertStatus(422);
    }

    public function test_create_booking_requires_valid_provider_value(): void
    {
        $admin = $this->admin();
        $product = $this->createProduct();

        $response = $this->actingAs($admin)->postJson('/admin/bookings/create-booking', [
            'provider' => 'supplier',
            'product_id' => $product->id,
            'customer_name' => 'Bad Provider',
            'customer_email' => 'badprovider@test.com',
            'quantity' => 1,
            'visit_date' => now()->addDays(1)->format('Y-m-d'),
            'skip_provider' => true,
        ]);

        $response->assertStatus(422);
    }

    public function test_create_booking_requires_quantity_min_1(): void
    {
        $admin = $this->admin();
        $product = $this->createProduct();

        $response = $this->actingAs($admin)->postJson('/admin/bookings/create-booking', [
            'provider' => 'bokun',
            'product_id' => $product->id,
            'customer_name' => 'Zero Qty',
            'customer_email' => 'zeroqty@test.com',
            'quantity' => 0,
            'visit_date' => now()->addDays(1)->format('Y-m-d'),
            'skip_provider' => true,
        ]);

        $response->assertStatus(422);
    }

    public function test_create_booking_requires_quantity_max_100(): void
    {
        $admin = $this->admin();
        $product = $this->createProduct();

        $response = $this->actingAs($admin)->postJson('/admin/bookings/create-booking', [
            'provider' => 'globaltix',
            'product_id' => $product->id,
            'customer_name' => 'High Qty',
            'customer_email' => 'highqty@test.com',
            'quantity' => 101,
            'visit_date' => now()->addDays(1)->format('Y-m-d'),
            'skip_provider' => true,
        ]);

        $response->assertStatus(422);
    }

    public function test_create_booking_requires_visit_date(): void
    {
        $admin = $this->admin();
        $product = $this->createProduct();

        $response = $this->actingAs($admin)->postJson('/admin/bookings/create-booking', [
            'provider' => 'bokun',
            'product_id' => $product->id,
            'customer_name' => 'No Date',
            'customer_email' => 'nodate@test.com',
            'quantity' => 1,
            'skip_provider' => true,
        ]);

        $response->assertStatus(422);
    }

    public function test_create_booking_requires_valid_status_override(): void
    {
        $admin = $this->admin();
        $product = $this->createProduct();

        $response = $this->actingAs($admin)->postJson('/admin/bookings/create-booking', [
            'provider' => 'bokun',
            'product_id' => $product->id,
            'customer_name' => 'Bad Override',
            'customer_email' => 'badoverride@test.com',
            'quantity' => 1,
            'visit_date' => now()->addDays(1)->format('Y-m-d'),
            'status_override' => 'FAILED',
            'skip_provider' => true,
        ]);

        $response->assertStatus(422);
    }

    public function test_create_booking_requires_customer_name_max_length(): void
    {
        $admin = $this->admin();
        $product = $this->createProduct();

        $response = $this->actingAs($admin)->postJson('/admin/bookings/create-booking', [
            'provider' => 'bokun',
            'product_id' => $product->id,
            'customer_name' => str_repeat('A', 256),
            'customer_email' => 'longname@test.com',
            'quantity' => 1,
            'visit_date' => now()->addDays(1)->format('Y-m-d'),
            'skip_provider' => true,
        ]);

        $response->assertStatus(422);
    }

    public function test_create_booking_requires_admin_note_max_length(): void
    {
        $admin = $this->admin();
        $product = $this->createProduct();

        $response = $this->actingAs($admin)->postJson('/admin/bookings/create-booking', [
            'provider' => 'globaltix',
            'product_id' => $product->id,
            'customer_name' => 'Long Note',
            'customer_email' => 'longnote@test.com',
            'quantity' => 1,
            'visit_date' => now()->addDays(1)->format('Y-m-d'),
            'admin_note' => str_repeat('X', 2001),
            'skip_provider' => true,
        ]);

        $response->assertStatus(422);
    }

    public function test_create_booking_rejects_guest(): void
    {
        $product = $this->createProduct();

        $response = $this->postJson('/admin/bookings/create-booking', [
            'provider' => 'bokun',
            'product_id' => $product->id,
            'customer_name' => 'Guest User',
            'customer_email' => 'guest@test.com',
            'quantity' => 1,
            'visit_date' => now()->addDays(1)->format('Y-m-d'),
            'skip_provider' => true,
        ]);

        $response->assertRedirect();
    }

    // ==================== GENERATE PAYMENT LINK ====================

    public function test_admin_can_generate_payment_link(): void
    {
        $admin = $this->admin();
        $product = $this->createProduct('bokun', 'bokun-p1', 250.00);

        // Create a booking first
        BookingInfo::create([
            'reference' => 'ADMIN-PAY-001',
            'booking_reference' => 'ADMIN-PAY-001',
            'confirmation_code' => 'CONF-PAY-001',
            'provider_type' => 'bokun',
            'product_id' => $product->id,
            'customer_name' => 'Payment Customer',
            'customer_email' => 'payment@example.com',
            'status' => 'RESERVED',
            'total_price' => 250.00,
            'currency' => 'EUR',
            'quantity' => 1,
        ]);

        $stripeMock = $this->createMock(StripeCheckoutService::class);
        $stripeMock->expects($this->once())
            ->method('createCheckoutSession')
            ->willReturn([
                'id' => 'cs_test_admin_pay_1',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_admin_pay_1',
            ]);
        $this->app->instance(StripeCheckoutService::class, $stripeMock);

        $response = $this->actingAs($admin)->json('GET', '/admin/bookings/ADMIN-PAY-001/payment-link');

        $response->assertOk()
            ->assertJsonPath('message', 'Payment link generated successfully.')
            ->assertJsonPath('data.checkout_url', 'https://checkout.stripe.com/c/pay/cs_test_admin_pay_1')
            ->assertJsonPath('data.session_id', 'cs_test_admin_pay_1')
            ->assertJsonPath('data.amount', 250.00)
            ->assertJsonPath('data.currency', 'EUR');
    }

    public function test_generate_payment_link_fails_for_non_existent_booking(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->json('GET', '/admin/bookings/NONEXISTENT-REF/payment-link');

        $response->assertStatus(404);
    }

    public function test_generate_payment_link_fails_for_zero_amount_booking(): void
    {
        $admin = $this->admin();
        BookingInfo::create([
            'reference' => 'ADMIN-ZERO-001',
            'booking_reference' => 'ADMIN-ZERO-001',
            'confirmation_code' => 'CONF-ZERO-001',
            'provider_type' => 'bokun',
            'customer_name' => 'Zero Customer',
            'customer_email' => 'zero@example.com',
            'status' => 'RESERVED',
            'total_price' => 0,
            'currency' => 'EUR',
        ]);

        $response = $this->actingAs($admin)->json('GET', '/admin/bookings/ADMIN-ZERO-001/payment-link');

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Booking has no payable amount. Set a price first.');
    }

    // ==================== AUTHORIZATION ====================

    public function test_create_booking_rejects_non_admin(): void
    {
        $user = $this->nonAdmin();
        $product = $this->createProduct();

        $response = $this->actingAs($user)->postJson('/admin/bookings/create-booking', [
            'provider' => 'bokun',
            'product_id' => $product->id,
            'customer_name' => 'Should Fail',
            'customer_email' => 'fail@test.com',
            'quantity' => 1,
            'visit_date' => now()->addDays(1)->format('Y-m-d'),
            'skip_provider' => true,
        ]);

        $response->assertRedirect();
    }

    public function test_generate_payment_link_rejects_guest(): void
    {
        $response = $this->json('GET', '/admin/bookings/SOME-REF/payment-link');
        $response->assertRedirect();
    }
}
