<?php

use App\Models\BookingInfo;
use App\Services\GlobalTix\GlobalTixBookingService;
use App\Services\Payments\StripeCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('reserves globaltix booking and creates stripe checkout session', function () {
    $globaltix = \Mockery::mock(GlobalTixBookingService::class);
    $stripe = \Mockery::mock(StripeCheckoutService::class);

    $this->app->instance(GlobalTixBookingService::class, $globaltix);
    $this->app->instance(StripeCheckoutService::class, $stripe);

    $globaltix->shouldReceive('reserveBooking')
        ->once()
        ->andReturn([
            'success' => true,
            'data' => [
                'referenceNumber' => 'GT-REF-1',
                'bookingTime' => '2026-05-20T08:48:40Z',
                'amount' => 15,
                'customer' => 'Customer A',
                'email' => 'customer.a@email.com',
                'status' => 'RESERVED',
            ],
            'error' => null,
        ]);

    $stripe->shouldReceive('createCheckoutSession')
        ->once()
        ->withArgs(function (
            string $reference,
            float $amount,
            string $currency,
            string $successUrl,
            string $cancelUrl,
            array $metadata,
            string $productName
        ): bool {
            return $reference === 'GT-REF-1'
                && $amount === 15.0
                && $currency === 'SGD'
                && str_contains($successUrl, 'session_id={CHECKOUT_SESSION_ID}')
                && str_contains($cancelUrl, 'reference=GT-REF-1')
                && (($metadata['provider'] ?? null) === 'globaltix')
                && $productName === 'GlobalTix Booking';
        })
        ->andReturn([
            'id' => 'cs_test_gt_1',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_gt_1',
        ]);

    $response = $this->postJson('/api/v1/globaltix/booking/reserve', [
        'customerName' => 'Customer A',
        'email' => 'customer.a@email.com',
        'currency' => 'SGD',
        'successUrl' => 'https://example.com/success',
        'cancelUrl' => 'https://example.com/cancel',
        'ticketTypes' => [
            [
                'id' => 256022,
                'quantity' => 1,
                'visitDate' => '2026-05-20',
                'visitTime' => '09:30',
                'index' => 0,
                'questionList' => [],
            ],
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'RESERVED')
        ->assertJsonPath('reference', 'GT-REF-1')
        ->assertJsonPath('confirmation_code', 'GT-REF-1')
        ->assertJsonPath('provider', 'globaltix')
        ->assertJsonPath('provider_currency', 'SGD')
        ->assertJsonPath('stripe_session_id', 'cs_test_gt_1');

    $this->assertDatabaseHas('booking_info', [
        'reference' => 'GT-REF-1',
        'confirmation_code' => 'GT-REF-1',
        'provider_type' => 'globaltix',
        'payment_gateway' => 'stripe',
        'status' => 'RESERVED',
    ]);

    if (Schema::hasTable('booking_info')) {
        expect(BookingInfo::query()->count())->toBe(1);
    }
});

it('processes globaltix stripe webhook once and remains idempotent', function () {
    $globaltix = \Mockery::mock(GlobalTixBookingService::class);
    $stripe = \Mockery::mock(StripeCheckoutService::class);

    $this->app->instance(GlobalTixBookingService::class, $globaltix);
    $this->app->instance(StripeCheckoutService::class, $stripe);

    BookingInfo::create([
        'reference' => 'GT-REF-WEB-1',
        'confirmation_code' => 'GT-REF-WEB-1',
        'booking_currency' => 'SGD',
        'booking_amount' => 15.00,
        'amount' => 15.00,
        'provider_type' => 'globaltix',
        'status' => 'RESERVED',
        'payment_gateway' => 'stripe',
    ]);

    $event = [
        'id' => 'evt_gt_1',
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_gt_webhook_1',
                'payment_intent' => 'pi_gt_webhook_1',
                'metadata' => [
                    'reference' => 'GT-REF-WEB-1',
                ],
            ],
        ],
    ];

    $stripe->shouldReceive('constructWebhookEvent')
        ->twice()
        ->andReturn($event);

    $globaltix->shouldReceive('confirmBooking')
        ->once()
        ->with('GT-REF-WEB-1', 'Stripe payment captured successfully.')
        ->andReturn([
            'success' => true,
            'data' => [
                'referenceNumber' => 'GT-REF-WEB-1',
                'status' => 'CONFIRMED',
                'confirmTime' => '2026-05-20T08:50:28Z',
            ],
            'error' => null,
        ]);

    $globaltix->shouldReceive('getBookingInfos')
        ->once()
        ->with('GT-REF-WEB-1')
        ->andReturn([
            'success' => true,
            'data' => [
                'referenceNumber' => 'GT-REF-WEB-1',
                'status' => 'CONFIRMED',
                'eTicketUrl' => 'https://eticket.example.com/GT-REF-WEB-1.pdf',
                'tickets' => [
                    [
                        'code' => 'GT-TICKET-1',
                        'name' => 'PDF Voucher Ticket',
                        'eventTime' => '2026-05-20T09:00:00Z',
                        'paidAmount' => 15,
                        'quantity_total' => 1,
                        'transaction' => [
                            'referenceNumber' => 'GT-REF-WEB-1',
                        ],
                        'product' => [
                            'id' => 256022,
                        ],
                    ],
                ],
            ],
            'error' => null,
        ]);

    $headers = ['Stripe-Signature' => 'test-signature'];

    $first = $this->withHeaders($headers)->postJson('/api/v1/webhook/stripe');
    $first->assertStatus(200)
        ->assertJsonPath('status', 'CONFIRMED')
        ->assertJsonPath('reference', 'GT-REF-WEB-1');

    $payment = BookingInfo::where('reference', 'GT-REF-WEB-1')->firstOrFail();
    expect($payment->status)->toBe('CONFIRMED');

    if (Schema::hasColumn('booking_info', 'stripe_last_webhook_event_id')) {
        expect($payment->stripe_last_webhook_event_id)->toBe('evt_gt_1');
    }

    $second = $this->withHeaders($headers)->postJson('/api/v1/webhook/stripe');
    $second->assertStatus(200)
        ->assertJsonPath('status', 'already_processed');
});

it('keeps globaltix booking routes and shared stripe webhook registered', function () {
    $uris = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => $route->uri())
        ->values()
        ->all();

    expect($uris)->toContain('api/v1/globaltix/booking/reserve');
    expect($uris)->toContain('api/v1/globaltix/booking/confirm');
    expect($uris)->toContain('api/v1/globaltix/booking/release');
    expect($uris)->toContain('api/v1/globaltix/booking/details');
    expect($uris)->toContain('api/v1/globaltix/booking/show/{reference}');
    expect($uris)->toContain('api/v1/webhook/stripe');
    expect($uris)->not->toContain('api/v1/globaltix/booking/webhook/stripe');
});

it('handles globaltix payment from the shared stripe webhook endpoint', function () {
    $globaltix = \Mockery::mock(GlobalTixBookingService::class);
    $stripe = \Mockery::mock(StripeCheckoutService::class);

    $this->app->instance(GlobalTixBookingService::class, $globaltix);
    $this->app->instance(StripeCheckoutService::class, $stripe);

    BookingInfo::create([
        'reference' => 'GT-REF-SHARED-1',
        'confirmation_code' => 'GT-REF-SHARED-1',
        'booking_currency' => 'SGD',
        'booking_amount' => 22.00,
        'amount' => 22.00,
        'provider_type' => 'globaltix',
        'status' => 'RESERVED',
        'payment_gateway' => 'stripe',
    ]);

    $event = [
        'id' => 'evt_gt_shared_1',
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_gt_shared_1',
                'payment_intent' => 'pi_gt_shared_1',
                'metadata' => [
                    'reference' => 'GT-REF-SHARED-1',
                    'provider' => 'globaltix',
                ],
            ],
        ],
    ];

    // Unified webhook parses the Stripe event once and then routes by provider.
    $stripe->shouldReceive('constructWebhookEvent')
        ->once()
        ->andReturn($event);

    $globaltix->shouldReceive('confirmBooking')
        ->once()
        ->with('GT-REF-SHARED-1', 'Stripe payment captured successfully.')
        ->andReturn([
            'success' => true,
            'data' => [
                'referenceNumber' => 'GT-REF-SHARED-1',
                'status' => 'CONFIRMED',
            ],
        ]);

    $globaltix->shouldReceive('getBookingInfos')
        ->once()
        ->with('GT-REF-SHARED-1')
        ->andReturn([
            'success' => true,
            'data' => [
                'referenceNumber' => 'GT-REF-SHARED-1',
                'status' => 'CONFIRMED',
                'tickets' => [],
            ],
        ]);

    $response = $this->withHeaders(['Stripe-Signature' => 'test-signature'])
        ->postJson('/api/v1/webhook/stripe');

    $response->assertStatus(200)
        ->assertJsonPath('status', 'CONFIRMED')
        ->assertJsonPath('provider', 'globaltix')
        ->assertJsonPath('reference', 'GT-REF-SHARED-1');
});
