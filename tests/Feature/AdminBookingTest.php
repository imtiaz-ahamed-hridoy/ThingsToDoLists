<?php

namespace Tests\Feature;

use App\Models\BookingInfo;
use App\Models\User;
use App\Services\Bokun\BokunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bookings_index_returns_datatables_shape(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        Sanctum::actingAs($admin);

        BookingInfo::create([
            'booking_reference' => 'REF-1',
            'reference' => 'REF-1',
            'confirmation_code' => 'CONF-1',
            'customer_name' => 'Alice',
            'customer_email' => 'alice@example.com',
            'status' => 'RESERVED',
            'total_price' => 100,
            'currency' => 'USD',
            'provider_type' => 'bokun',
        ]);

        BookingInfo::create([
            'booking_reference' => 'REF-2',
            'reference' => 'REF-2',
            'confirmation_code' => 'CONF-2',
            'customer_name' => 'Bob',
            'customer_email' => 'bob@example.com',
            'status' => 'RESERVED',
            'total_price' => 50,
            'currency' => 'USD',
            'provider_type' => 'globaltix',
        ]);

        $response = $this->getJson('/api/v1/admin/bookings?draw=1');

        $response->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data'])
            ->assertJsonPath('draw', 1);
    }

    public function test_admin_show_and_update_booking(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        Sanctum::actingAs($admin);

        $booking = BookingInfo::create([
            'booking_reference' => 'REFSHOW123',
            'reference' => 'REFSHOW123',
            'confirmation_code' => 'CONF-SHOW-1',
            'customer_name' => 'Carol',
            'customer_email' => 'carol@example.com',
            'status' => 'RESERVED',
            'total_price' => 75,
            'currency' => 'USD',
            'provider_type' => 'bokun',
        ]);

        $show = $this->getJson('/api/v1/admin/bookings/' . $booking->booking_reference);
        $show->assertOk()->assertJsonPath('data.booking_reference', 'REFSHOW123');

        $update = $this->putJson('/api/v1/admin/bookings/' . $booking->booking_reference, [
            'status' => 'FAILED',
            'failure_reason' => 'Test failure',
            'failure_code' => 'TEST-ERR',
            'admin_note' => 'Investigate later',
        ]);

        $update->assertOk()->assertJsonPath('data.status', 'FAILED');

        $this->assertDatabaseHas('booking_info', [
            'booking_reference' => 'REFSHOW123',
            'status' => 'FAILED',
            'failure_reason' => 'Test failure',
            'failure_code' => 'TEST-ERR',
            'admin_note' => 'Investigate later',
        ]);
    }

    public function test_bokun_payment_error_records_failure_and_admin_can_view(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        // Create both payment and product rows for the booking
        BookingInfo::create([
            'reference' => 'REFERR1',
            'booking_reference' => 'REFERR1',
            'confirmation_code' => 'CONF-ERR-1',
            'status' => 'RESERVED',
            'booking_amount' => 120,
            'booking_currency' => 'USD',
            'provider_type' => 'bokun',
        ]);

        BookingInfo::updateOrCreate([
            'booking_reference' => 'REFERR1',
        ], [
            'reference' => 'REFERR1',
            'confirmation_code' => 'CONF-ERR-1',
            'status' => 'RESERVED',
            'total_price' => 120,
            'currency' => 'USD',
            'provider_type' => 'bokun',
        ]);

        // Prevent the controller from making an external Bokun call by mocking the service.
        $mock = $this->createMock(BokunService::class);
        $mock->method('markPaymentError')->willReturn(['success' => true]);
        $this->app->instance(BokunService::class, $mock);

        $response = $this->postJson('/api/v1/bokun-booking/CONF-ERR-1/payment-error', [
            'errorCode' => 'PMT-DECLINE',
            'errorDescription' => 'Card declined',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('booking_info', [
            'confirmation_code' => 'CONF-ERR-1',
            'status' => 'FAILED',
            'failure_code' => 'PMT-DECLINE',
            'failure_reason' => 'Card declined',
        ]);

        Sanctum::actingAs($admin);
        $adminView = $this->getJson('/api/v1/admin/bookings?search=CONF-ERR-1');
        $adminView->assertOk()->assertJsonFragment(['failure_code' => 'PMT-DECLINE']);
    }
}
