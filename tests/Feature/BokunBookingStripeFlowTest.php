<?php

use App\Models\BookingInfo;
use App\Services\Bokun\BokunService;
use App\Services\Payments\StripeCheckoutService;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('submits checkout with official bokun payload and creates stripe session', function () {
    $bokun = \Mockery::mock(BokunService::class);
    $stripe = \Mockery::mock(StripeCheckoutService::class);

    $this->app->instance(BokunService::class, $bokun);
    $this->app->instance(StripeCheckoutService::class, $stripe);

    $bokun->shouldReceive('submitDirectCheckout')
        ->once()
        ->andReturn([
            'bookingHash' => 'ref_official_1',
            'booking' => [
                'bookingId' => 89552415,
                'confirmationCode' => 'CONF-OFF-1',
                'totalDue' => 120.50,
                'currency' => 'USD',
                'productBookings' => [
                    [
                        'bookingId' => 128004767,
                        'productConfirmationCode' => 'LAN-T128004767',
                        'productTitle' => 'Cairo Tour',
                        'totalPrice' => 120.50,
                        'quantity' => 1,
                        'date' => '2026-05-20',
                        'startTime' => '09:00',
                    ],
                ],
            ],
        ]);

    $stripe->shouldReceive('createCheckoutSession')
        ->once()
        ->andReturn([
            'id' => 'cs_test_official_1',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_official_1',
        ]);

    $payload = [
        'checkoutOption' => 'CUSTOMER_FULL_PAYMENT',
        'paymentMethod' => 'RESERVE_FOR_EXTERNAL_PAYMENT',
        'currency' => 'USD',
        'lang' => 'EN',
        'successUrl' => 'https://example.com/success',
        'cancelUrl' => 'https://example.com/cancel',
        'directBooking' => [
            'mainContactDetails' => [
                ['questionId' => 'firstName', 'values' => ['John']],
                ['questionId' => 'email', 'values' => ['john@example.com']],
            ],
            'activityBookings' => [
                [
                    'activityId' => 279300,
                    'answers' => [],
                    'pickupAnswers' => [],
                    'dropoffAnswers' => [],
                    'rateId' => 646161,
                    'startTimeId' => 662672,
                    'date' => '2026-05-20',
                    'pickup' => false,
                    'dropoff' => false,
                    'checkedIn' => false,
                    'customized' => false,
                    'customizedTime' => '',
                    'customizedPrice' => 0,
                    'passengers' => [
                        [
                            'pricingCategoryId' => 185995,
                            'groupSize' => 1,
                            'passengerDetails' => [],
                            'answers' => [],
                            'extras' => [],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $response = $this->postJson('/api/v1/bokun-booking/submit-checkout', $payload);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'RESERVED')
        ->assertJsonPath('reference', 'ref_official_1')
        ->assertJsonPath('confirmation_code', 'CONF-OFF-1')
        ->assertJsonPath('confirmationCode', 'CONF-OFF-1')
        ->assertJsonPath('bookingId', 89552415)
        ->assertJsonPath('productBookingId', 128004767)
        ->assertJsonPath('productConfirmationCode', 'LAN-T128004767')
        ->assertJsonPath('stripe_session_id', 'cs_test_official_1');

    $this->assertDatabaseHas('booking_info', [
        'reference' => 'ref_official_1',
        'confirmation_code' => 'CONF-OFF-1',
        'status' => 'RESERVED',
    ]);

    $this->assertDatabaseHas('booking_info', [
        'booking_reference' => 'ref_official_1',
        'confirmation_code' => 'CONF-OFF-1',
        'status' => 'RESERVED',
    ]);

    if (Schema::hasTable('booking_info')) {
        expect(\App\Models\BookingInfo::query()->count())->toBe(1);
    }
});

it('accepts checkout options payload in wrapped and raw formats', function () {
    $bokun = \Mockery::mock(BokunService::class);
    $stripe = \Mockery::mock(StripeCheckoutService::class);

    $this->app->instance(BokunService::class, $bokun);
    $this->app->instance(StripeCheckoutService::class, $stripe);

    $bookingRequest = [
        'mainContactDetails' => [],
        'activityBookings' => [
            ['activityId' => 101, 'passengers' => []],
        ],
    ];

    $bokun->shouldReceive('getCheckoutOptions')
        ->twice()
        ->with($bookingRequest, 'USD', 'EN')
        ->andReturn(['options' => []]);

    $wrapped = $this->postJson('/api/v1/bokun-booking/checkout-options', [
        'currency' => 'USD',
        'lang' => 'EN',
        'bookingRequest' => $bookingRequest,
    ]);
    $wrapped->assertStatus(200)->assertJsonPath('message', 'Checkout options retrieved successfully');

    $raw = $this->postJson('/api/v1/bokun-booking/checkout-options?currency=USD&lang=EN', $bookingRequest);
    $raw->assertStatus(200)->assertJsonPath('message', 'Checkout options retrieved successfully');
});

it('returns bokun identifiers from fallback fields when primary keys are missing', function () {
    $bokun = \Mockery::mock(BokunService::class);
    $stripe = \Mockery::mock(StripeCheckoutService::class);

    $this->app->instance(BokunService::class, $bokun);
    $this->app->instance(StripeCheckoutService::class, $stripe);

    $bokun->shouldReceive('submitDirectCheckout')
        ->once()
        ->andReturn([
            'bookingHash' => 'ref_fallback_1',
            'booking' => [
                'confirmationCode' => 'CONF-FALLBACK-1',
                'totalDue' => 77.00,
                'currency' => 'USD',
                'productBookings' => [
                    [
                        'bookingId' => 128004767,
                        'parentBookingId' => 89552415,
                        'productTitle' => 'Fallback Tour',
                        'totalPrice' => 77.00,
                        'quantity' => 1,
                    ],
                ],
            ],
            'travelDocuments' => [
                'activityTickets' => [
                    [
                        'bookingId' => 89552415,
                        'productConfirmationCode' => 'LAN-T128004767',
                    ],
                ],
            ],
        ]);

    $stripe->shouldReceive('createCheckoutSession')
        ->once()
        ->andReturn([
            'id' => 'cs_test_fallback_1',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_fallback_1',
        ]);

    $payload = [
        'checkoutOption' => 'CUSTOMER_FULL_PAYMENT',
        'paymentMethod' => 'RESERVE_FOR_EXTERNAL_PAYMENT',
        'currency' => 'USD',
        'lang' => 'EN',
        'successUrl' => 'https://example.com/success',
        'cancelUrl' => 'https://example.com/cancel',
        'directBooking' => [
            'mainContactDetails' => [
                ['questionId' => 'firstName', 'values' => ['John']],
                ['questionId' => 'email', 'values' => ['john@example.com']],
            ],
            'activityBookings' => [
                [
                    'activityId' => 279300,
                    'answers' => [],
                    'pickupAnswers' => [],
                    'dropoffAnswers' => [],
                    'rateId' => 646161,
                    'startTimeId' => 662672,
                    'date' => '2026-05-20',
                    'pickup' => false,
                    'dropoff' => false,
                    'checkedIn' => false,
                    'customized' => false,
                    'customizedTime' => '',
                    'customizedPrice' => 0,
                    'passengers' => [
                        [
                            'pricingCategoryId' => 185995,
                            'groupSize' => 1,
                            'passengerDetails' => [],
                            'answers' => [],
                            'extras' => [],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $response = $this->postJson('/api/v1/bokun-booking/submit-checkout', $payload);

    $response->assertStatus(200)
        ->assertJsonPath('confirmation_code', 'CONF-FALLBACK-1')
        ->assertJsonPath('confirmationCode', 'CONF-FALLBACK-1')
        ->assertJsonPath('bookingId', 89552415)
        ->assertJsonPath('productBookingId', 128004767)
        ->assertJsonPath('productConfirmationCode', 'LAN-T128004767');
});

it('strips payment-only submit fields and keeps non-empty attribution fields', function () {
    $bokun = \Mockery::mock(BokunService::class);
    $stripe = \Mockery::mock(StripeCheckoutService::class);

    $this->app->instance(BokunService::class, $bokun);
    $this->app->instance(StripeCheckoutService::class, $stripe);

    $bokun->shouldReceive('submitDirectCheckout')
        ->once()
        ->withArgs(function (array $payload, string $currency, string $lang): bool {
            return $currency === 'USD'
                && $lang === 'EN'
                && !array_key_exists('amount', $payload)
                && !array_key_exists('uti', $payload)
                && !array_key_exists('paymentToken', $payload)
                && !array_key_exists('transactionDetails', $payload)
                && !array_key_exists('providerPaymentParameters', $payload)
                && (($payload['affiliateCode'] ?? null) === 'AFF-123')
                && !array_key_exists('bookingReferralSource', $payload)
                && !array_key_exists('googleClickId', $payload);
        })
        ->andReturn([
            'bookingHash' => 'ref_strip_1',
            'booking' => [
                'confirmationCode' => 'CONF-STRIP-1',
                'totalDue' => 1050.99,
                'currency' => 'USD',
                'productBookings' => [
                    [
                        'productTitle' => 'Transfer',
                        'totalPrice' => 1050.99,
                        'quantity' => 1,
                        'date' => '2026-04-29',
                        'startTime' => '08:00',
                    ],
                ],
            ],
        ]);

    $stripe->shouldReceive('createCheckoutSession')
        ->once()
        ->andReturn([
            'id' => 'cs_test_strip_1',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_strip_1',
        ]);

    $payload = [
        'checkoutOption' => 'CUSTOMER_FULL_PAYMENT',
        'paymentMethod' => 'RESERVE_FOR_EXTERNAL_PAYMENT',
        'currency' => 'USD',
        'lang' => 'EN',
        'successUrl' => 'https://example.com/success',
        'cancelUrl' => 'https://example.com/cancel',
        'amount' => 1050.99,
        'uti' => '',
        'paymentToken' => ['token' => ''],
        'transactionDetails' => [
            'transactionDate' => '',
            'transactionId' => '',
            'cardBrand' => '',
            'last4' => '',
        ],
        'providerPaymentParameters' => [],
        'affiliateCode' => 'AFF-123',
        'bookingReferralSource' => '',
        'googleClickId' => '',
        'directBooking' => [
            'mainContactDetails' => [
                ['questionId' => 'firstName', 'values' => ['Imtiaz']],
                ['questionId' => 'email', 'values' => ['imtiaz@example.com']],
            ],
            'activityBookings' => [
                [
                    'activityId' => 279300,
                    'answers' => [],
                    'pickupAnswers' => [],
                    'dropoffAnswers' => [],
                    'rateId' => 646161,
                    'startTimeId' => 662672,
                    'date' => '2026-04-29',
                    'pickup' => true,
                    'dropoff' => false,
                    'checkedIn' => true,
                    'customized' => false,
                    'customizedTime' => '',
                    'customizedPrice' => 0,
                    'passengers' => [
                        [
                            'pricingCategoryId' => 185995,
                            'groupSize' => 1,
                            'passengerDetails' => [],
                            'answers' => [],
                            'extras' => [],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $response = $this->postJson('/api/v1/bokun-booking/submit-checkout', $payload);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'RESERVED')
        ->assertJsonPath('reference', 'ref_strip_1')
        ->assertJsonPath('confirmation_code', 'CONF-STRIP-1')
        ->assertJsonPath('stripe_session_id', 'cs_test_strip_1');
});

it('submits checkout with legacy payload items and normalizes to activityBookings', function () {
    $bokun = \Mockery::mock(BokunService::class);
    $stripe = \Mockery::mock(StripeCheckoutService::class);

    $this->app->instance(BokunService::class, $bokun);
    $this->app->instance(StripeCheckoutService::class, $stripe);

    $bokun->shouldReceive('submitDirectCheckout')
        ->once()
        ->withArgs(function (array $payload, string $currency, string $lang): bool {
            return $currency === 'USD'
                && $lang === 'EN'
                && isset($payload['directBooking']['activityBookings'][0]['activityId'])
                && !isset($payload['directBooking']['items']);
        })
        ->andReturn([
            'bookingHash' => 'ref_legacy_1',
            'booking' => [
                'confirmationCode' => 'CONF-LEG-1',
                'totalDue' => 49.99,
                'currency' => 'USD',
                'productBookings' => [
                    [
                        'productTitle' => 'Legacy Tour',
                        'totalPrice' => 49.99,
                        'quantity' => 1,
                    ],
                ],
            ],
        ]);

    $stripe->shouldReceive('createCheckoutSession')
        ->once()
        ->andReturn([
            'id' => 'cs_test_legacy_1',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_legacy_1',
        ]);

    $payload = [
        'checkoutOption' => 'CUSTOMER_FULL_PAYMENT',
        'paymentMethod' => 'RESERVE_FOR_EXTERNAL_PAYMENT',
        'currency' => 'USD',
        'lang' => 'EN',
        'successUrl' => 'https://example.com/success',
        'cancelUrl' => 'https://example.com/cancel',
        'directBooking' => [
            'mainContactDetails' => [
                ['questionId' => 'email', 'values' => ['legacy@example.com']],
            ],
            'items' => [
                [
                    'activityId' => 889486,
                    'rateId' => 1717084,
                    'startTimeId' => 3102174,
                    'date' => '2026-06-10',
                    'pickup' => false,
                    'dropoff' => false,
                    'checkedIn' => false,
                    'customized' => false,
                    'customizedTime' => '',
                    'customizedPrice' => 0,
                    'passengers' => [
                        [
                            'pricingCategoryId' => 863387,
                            'groupSize' => 1,
                            'passengerDetails' => [],
                            'answers' => [],
                            'extras' => [],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $response = $this->postJson('/api/v1/bokun-booking/submit-checkout', $payload);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'RESERVED')
        ->assertJsonPath('reference', 'ref_legacy_1')
        ->assertJsonPath('confirmation_code', 'CONF-LEG-1')
        ->assertJsonPath('stripe_session_id', 'cs_test_legacy_1');
});

it('processes stripe webhook once and stays idempotent on duplicates', function () {
    $bokun = \Mockery::mock(BokunService::class);
    $stripe = \Mockery::mock(StripeCheckoutService::class);

    $this->app->instance(BokunService::class, $bokun);
    $this->app->instance(StripeCheckoutService::class, $stripe);

    BookingInfo::create([
        'reference' => 'ref_webhook_1',
        'confirmation_code' => 'CONF-WEB-1',
        'booking_currency' => 'USD',
        'booking_amount' => 200.00,
        'amount' => 200.00,
        'provider_type' => 'bokun',
        'status' => 'RESERVED',
    ]);

    $event = [
        'id' => 'evt_test_1',
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_webhook_1',
                'payment_intent' => 'pi_webhook_1',
                'metadata' => [
                    'reference' => 'ref_webhook_1',
                ],
            ],
        ],
    ];

    $stripe->shouldReceive('constructWebhookEvent')
        ->twice()
        ->andReturn($event);

    $bokun->shouldReceive('confirmReservedBooking')
        ->once()
        ->andReturn(['message' => 'ok']);

    $bokun->shouldReceive('confirmBookingAfterPayment')
        ->once()
        ->andReturn([
            'booking' => [
                'confirmationCode' => 'CONF-WEB-1',
                'productBookings' => [
                    [
                        'productTitle' => 'Webhook Tour',
                        'totalPrice' => 200.00,
                        'quantity' => 1,
                    ],
                ],
            ],
        ]);

    $headers = ['Stripe-Signature' => 'test-signature'];

    $first = $this->withHeaders($headers)->postJson('/api/v1/webhook/stripe');
    $first->assertStatus(200)->assertJsonPath('status', 'CONFIRMED');

    $payment = BookingInfo::where('reference', 'ref_webhook_1')->firstOrFail();
    expect($payment->status)->toBe('CONFIRMED');
    if (Schema::hasColumn('booking_info', 'stripe_last_webhook_event_id')) {
        expect($payment->stripe_last_webhook_event_id)->toBe('evt_test_1');
    }

    $second = $this->withHeaders($headers)->postJson('/api/v1/webhook/stripe');
    $second->assertStatus(200)->assertJsonPath('status', 'already_processed');
});

it('keeps bokun booking and shared stripe routes registered', function () {
    $uris = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => $route->uri())
        ->values()
        ->all();

    expect($uris)->toContain('api/v1/bokun-booking/checkout-options');
    expect($uris)->toContain('api/v1/bokun-booking/submit-checkout');
    expect($uris)->toContain('api/v1/bokun-booking/show/{reference}');
    expect($uris)->toContain('api/v1/bokun-booking/cancel-booking/{confirmationCode}');
    expect($uris)->toContain('api/v1/webhook/stripe');

    expect($uris)->not->toContain('api/v1/direct-booking/checkout-options');
    expect($uris)->not->toContain('api/v1/direct-booking/submit-checkout');
    expect($uris)->not->toContain('api/v1/direct-booking/show/{reference}');
    expect($uris)->not->toContain('api/v1/direct-booking/booking-cancel');
    expect($uris)->toContain('api/v1/paypal/checkout-options');
    expect($uris)->toContain('api/v1/paypal/reserve');
    expect($uris)->toContain('api/v1/paypal/capture');
    expect($uris)->toContain('api/v1/paypal/webhook');
});

it('proxies abort reserved booking to bokun api', function () {
    $bokun = \Mockery::mock(BokunService::class);
    $stripe = \Mockery::mock(StripeCheckoutService::class);

    $this->app->instance(BokunService::class, $bokun);
    $this->app->instance(StripeCheckoutService::class, $stripe);

    $bokun->shouldReceive('abortReservedBooking')
        ->once()
        ->with('CONF-ABORT-1', true)
        ->andReturn(['message' => 'aborted']);

    $response = $this->getJson('/api/v1/bokun-booking/CONF-ABORT-1/abort-reserved?timeout=true');

    $response->assertStatus(200)->assertJsonPath('message', 'Reserved booking aborted successfully');
});

it('treats booking is not reserved as idempotent success on abort endpoint', function () {
    $bokun = \Mockery::mock(BokunService::class);
    $stripe = \Mockery::mock(StripeCheckoutService::class);

    $this->app->instance(BokunService::class, $bokun);
    $this->app->instance(StripeCheckoutService::class, $stripe);

    BookingInfo::create([
        'reference' => 'ref_not_reserved_1',
        'confirmation_code' => 'CONF-NOT-RESERVED-1',
        'booking_currency' => 'USD',
        'booking_amount' => 75.00,
        'amount' => 75.00,
        'provider_type' => 'bokun',
        'status' => 'CONFIRMED',
    ]);

    $providerResponse = new PsrResponse(400, ['Content-Type' => 'application/json'], json_encode([
        'message' => 'Booking is not reserved.',
        'fields' => [],
    ]));

    $bokun->shouldReceive('abortReservedBooking')
        ->once()
        ->with('CONF-NOT-RESERVED-1', false)
        ->andThrow(new RequestException(
            'Booking is not reserved.',
            new PsrRequest('GET', 'https://api.bokun.io/booking.json/CONF-NOT-RESERVED-1/abort-reserved?timeout=false'),
            $providerResponse
        ));

    $response = $this->getJson('/api/v1/bokun-booking/CONF-NOT-RESERVED-1/abort-reserved?timeout=false');

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Booking is already not reserved.')
        ->assertJsonPath('data.already_not_reserved', true)
        ->assertJsonPath('data.booking_payment_status', 'CONFIRMED')
        ->assertJsonPath('data.booking_product_status', 'CONFIRMED')
        ->assertJsonPath('data.provider_response.message', 'Booking is not reserved.');
});

it('proxies payment error to bokun api', function () {
    $bokun = \Mockery::mock(BokunService::class);
    $stripe = \Mockery::mock(StripeCheckoutService::class);

    $this->app->instance(BokunService::class, $bokun);
    $this->app->instance(StripeCheckoutService::class, $stripe);

    $bokun->shouldReceive('markPaymentError')
        ->once()
        ->with('CONF-ERR-1', [
            'errorCode' => 'PAYMENT_DECLINED',
            'errorDescription' => 'Card declined by issuer',
        ])
        ->andReturn(['message' => 'ok']);

    $response = $this->postJson('/api/v1/bokun-booking/CONF-ERR-1/payment-error', [
        'errorCode' => 'PAYMENT_DECLINED',
        'errorDescription' => 'Card declined by issuer',
    ]);

    $response->assertStatus(200)->assertJsonPath('message', 'Payment error sent successfully');
});

it('aborts reserved booking from cancel endpoint and marks status CANCELLED', function () {
    $bokun = \Mockery::mock(BokunService::class);
    $stripe = \Mockery::mock(StripeCheckoutService::class);

    $this->app->instance(BokunService::class, $bokun);
    $this->app->instance(StripeCheckoutService::class, $stripe);

    BookingInfo::create([
        'reference' => 'ref_cancel_1',
        'confirmation_code' => 'CONF-CANCEL-1',
        'booking_currency' => 'USD',
        'booking_amount' => 200.00,
        'amount' => 200.00,
        'provider_type' => 'bokun',
        'status' => 'RESERVED',
    ]);

    $bokun->shouldReceive('cancelBooking')->never();

    $bokun->shouldReceive('abortReservedBooking')
        ->once()
        ->with('CONF-CANCEL-1', false)
        ->andReturn(['message' => 'aborted']);

    $response = $this->getJson('/api/v1/bokun-booking/cancel-booking/CONF-CANCEL-1');

    $response->assertStatus(200)
        ->assertJsonPath('message', 'aborted');

    $this->assertDatabaseHas('booking_info', [
        'confirmation_code' => 'CONF-CANCEL-1',
        'status' => 'CANCELLED',
    ]);

    $this->assertDatabaseHas('booking_info', [
        'confirmation_code' => 'CONF-CANCEL-1',
        'status' => 'CANCELLED',
    ]);
});

it('cancels bokun product booking when cancel endpoint receives product confirmation code', function () {
    $bokun = \Mockery::mock(BokunService::class);
    $stripe = \Mockery::mock(StripeCheckoutService::class);

    $this->app->instance(BokunService::class, $bokun);
    $this->app->instance(StripeCheckoutService::class, $stripe);

    $bokun->shouldReceive('abortReservedBooking')->never();
    $bokun->shouldReceive('cancelBooking')->never();

    $bokun->shouldReceive('cancelProductBooking')
        ->once()
        ->with('YFR-T130451579', [
            'note' => 'Cancelled from API.',
            'notify' => true,
            'refund' => true,
            'refundAmount' => 0.0,
        ])
        ->andReturn(['message' => 'cancelled']);

    $response = $this->getJson('/api/v1/bokun-booking/cancel-booking/YFR-T130451579');

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Product booking cancelled successfully')
        ->assertJsonPath('data.product_confirmation_code', 'YFR-T130451579')
        ->assertJsonPath('data.provider_response.message', 'cancelled');
});

it('cancels comma separated bokun product confirmation codes', function () {
    $bokun = \Mockery::mock(BokunService::class);
    $stripe = \Mockery::mock(StripeCheckoutService::class);

    $this->app->instance(BokunService::class, $bokun);
    $this->app->instance(StripeCheckoutService::class, $stripe);

    $payload = [
        'note' => 'Cancelled from API.',
        'notify' => true,
        'refund' => true,
        'refundAmount' => 0.0,
    ];

    $bokun->shouldReceive('abortReservedBooking')->never();
    $bokun->shouldReceive('cancelBooking')->never();

    $bokun->shouldReceive('cancelProductBooking')
        ->once()
        ->with('YFR-T130451579', $payload)
        ->andReturn(['message' => 'YFR cancelled']);

    $bokun->shouldReceive('cancelProductBooking')
        ->once()
        ->with('ADV-T131751355', $payload)
        ->andReturn(['message' => 'ADV cancelled']);

    $response = $this->getJson('/api/v1/bokun-booking/cancel-booking/YFR-T130451579,ADV-T131751355');

    $response->assertStatus(200)
        ->assertJsonPath('status', 'CANCELLED')
        ->assertJsonPath('message', 'Booking cancellations processed successfully')
        ->assertJsonPath('data.0.identifier', 'YFR-T130451579')
        ->assertJsonPath('data.0.product_confirmation_code', 'YFR-T130451579')
        ->assertJsonPath('data.0.provider_response.message', 'YFR cancelled')
        ->assertJsonPath('data.1.identifier', 'ADV-T131751355')
        ->assertJsonPath('data.1.product_confirmation_code', 'ADV-T131751355')
        ->assertJsonPath('data.1.provider_response.message', 'ADV cancelled');
});

it('uses parent confirmation code when cancelling a reserved booking by product confirmation code', function () {
    $bokun = \Mockery::mock(BokunService::class);
    $stripe = \Mockery::mock(StripeCheckoutService::class);

    $this->app->instance(BokunService::class, $bokun);
    $this->app->instance(StripeCheckoutService::class, $stripe);

    BookingInfo::create([
        'reference' => 'ref_reserved_product_code_1',
        'confirmation_code' => 'YFR-130451579',
        'booking_currency' => 'USD',
        'booking_amount' => 200.00,
        'amount' => 200.00,
        'provider_type' => 'bokun',
        'status' => 'RESERVED',
    ]);

    BookingInfo::updateOrCreate(
        ['booking_reference' => 'ref_reserved_product_code_1'],
        [
            'confirmation_code' => 'YFR-130451579',
            'product_confirmation_code' => 'YFR-T130451579',
            'provider_type' => 'bokun',
            'status' => 'RESERVED',
            'total_price' => 200.00,
            'currency' => 'USD',
        ]
    );

    $bokun->shouldReceive('cancelProductBooking')->never();
    $bokun->shouldReceive('cancelBooking')->never();

    $bokun->shouldReceive('abortReservedBooking')
        ->once()
        ->with('YFR-130451579', false)
        ->andReturn(['message' => 'aborted']);

    $response = $this->getJson('/api/v1/bokun-booking/cancel-booking/YFR-T130451579');

    $response->assertStatus(200)
        ->assertJsonPath('message', 'aborted');

    $this->assertDatabaseHas('booking_info', [
        'confirmation_code' => 'YFR-130451579',
        'product_confirmation_code' => 'YFR-T130451579',
        'status' => 'CANCELLED',
    ]);
});

it('uses abort reserved directly on cancel endpoint', function () {
    $bokun = \Mockery::mock(BokunService::class);
    $stripe = \Mockery::mock(StripeCheckoutService::class);

    $this->app->instance(BokunService::class, $bokun);
    $this->app->instance(StripeCheckoutService::class, $stripe);

    $bokun->shouldReceive('cancelBooking')->never();

    $bokun->shouldReceive('abortReservedBooking')
        ->once()
        ->with('CONF-CANCEL-RES-1', false)
        ->andReturn(['message' => 'aborted']);

    $response = $this->getJson('/api/v1/bokun-booking/cancel-booking/CONF-CANCEL-RES-1');

    $response->assertStatus(200)
        ->assertJsonPath('message', 'aborted');
});

it('forwards timeout query to abort reserved endpoint', function () {
    $bokun = \Mockery::mock(BokunService::class);
    $stripe = \Mockery::mock(StripeCheckoutService::class);

    $this->app->instance(BokunService::class, $bokun);
    $this->app->instance(StripeCheckoutService::class, $stripe);

    $bokun->shouldReceive('cancelBooking')->never();

    $bokun->shouldReceive('abortReservedBooking')
        ->once()
        ->with('CONF-CANCEL-TIMEOUT-1', true)
        ->andReturn(['message' => 'aborted']);

    $response = $this->getJson('/api/v1/bokun-booking/cancel-booking/CONF-CANCEL-TIMEOUT-1?timeout=true');

    $response->assertStatus(200)
        ->assertJsonPath('message', 'aborted');
});

it('does not update status when abort reserved response is not ok', function () {
    $bokun = \Mockery::mock(BokunService::class);
    $stripe = \Mockery::mock(StripeCheckoutService::class);

    $this->app->instance(BokunService::class, $bokun);
    $this->app->instance(StripeCheckoutService::class, $stripe);

    BookingInfo::create([
        'reference' => 'ref_cancel_not_ok_1',
        'confirmation_code' => 'CONF-CANCEL-NOT-OK-1',
        'booking_currency' => 'USD',
        'booking_amount' => 210.00,
        'amount' => 210.00,
        'provider_type' => 'bokun',
        'status' => 'RESERVED',
    ]);

    $bokun->shouldReceive('cancelBooking')->never();

    $bokun->shouldReceive('abortReservedBooking')
        ->once()
        ->with('CONF-CANCEL-NOT-OK-1', false)
        ->andReturn([
            'success' => false,
            'message' => 'failed to abort',
        ]);

    $response = $this->getJson('/api/v1/bokun-booking/cancel-booking/CONF-CANCEL-NOT-OK-1');

    $response->assertStatus(400)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'failed to abort');

    $this->assertDatabaseHas('booking_info', [
        'confirmation_code' => 'CONF-CANCEL-NOT-OK-1',
        'status' => 'RESERVED',
    ]);

    $this->assertDatabaseHas('booking_info', [
        'confirmation_code' => 'CONF-CANCEL-NOT-OK-1',
        'status' => 'RESERVED',
    ]);
});

it('proxies booking summary to bokun api', function () {
    $bokun = \Mockery::mock(BokunService::class);
    $stripe = \Mockery::mock(StripeCheckoutService::class);

    $this->app->instance(BokunService::class, $bokun);
    $this->app->instance(StripeCheckoutService::class, $stripe);

    $bokun->shouldReceive('getBookingSummary')
        ->once()
        ->with('12345')
        ->andReturn([
            'bookingId' => 12345,
            'status' => 'CONFIRMED',
        ]);

    $response = $this->getJson('/api/v1/bokun-booking/12345/summary');

    $response->assertStatus(200)
        ->assertJsonPath('bookingId', 12345)
        ->assertJsonPath('status', 'CONFIRMED');
});

it('proxies activity booking ticket to bokun api', function () {
    $bokun = \Mockery::mock(BokunService::class);
    $stripe = \Mockery::mock(StripeCheckoutService::class);

    $this->app->instance(BokunService::class, $bokun);
    $this->app->instance(StripeCheckoutService::class, $stripe);

    $bokun->shouldReceive('getActivityBookingTicket')
        ->once()
        ->with('PROD-CONF-1')
        ->andReturn([
            'content_type' => 'application/pdf',
            'raw_body' => 'PDF-DATA',
            'status_code' => 200,
        ]);

    $response = $this->get('/api/v1/bokun-booking/activity-booking/PROD-CONF-1/ticket');

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/pdf');
    $response->assertSee('PDF-DATA');
});
