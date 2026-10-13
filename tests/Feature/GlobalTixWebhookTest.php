<?php

namespace Tests\Feature;

use App\Models\AvailabilityCategoryPrice;
use App\Models\AvailabilityCheck;
use App\Models\BookingInfo;
use App\Models\OptionPricingCategory;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\TempProduct;
use App\Jobs\ProcessGlobalTixBookingWebhookJob;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

class GlobalTixWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_globaltix_webhook_with_valid_signature()
    {
        Queue::fake();

        $secret = 'TEST_MOCK_GLOBALTIX_WEBHOOK_SECRET';
        config(['services.globaltix.webhook_secret' => $secret]);

        BookingInfo::create([
            'booking_reference' => 'GT12345678',
            'provider_type' => 'globaltix',
            'status' => 'CONFIRMED',
            'total_price' => 100,
            'currency' => 'USD',
        ]);

        $payload = [
            'eventType' => 'BOOKING_TICKET_UPDATE',
            'timestamp' => '2024-08-20T12:04:31',
            'data' => [
                'referenceNumber' => 'GT12345678',
                'eTicketUrl' => 'https://neweticket.globaltix.com/example.pdf',
                'tickets' => [
                    ['code' => 'GTABC123', 'status' => 'READY'],
                ],
            ]
        ];

        $signature = $this->signatureFor($payload['eventType'], $payload['timestamp'], $secret);
        $payload['signature'] = $signature;

        $response = $this->postJson('/api/v1/webhook/globaltix/booking-ticket-update', $payload);

        $response->assertAccepted();
        $response->assertJsonPath('event', 'BOOKING_TICKET_UPDATE');

        Queue::assertPushed(ProcessGlobalTixBookingWebhookJob::class, 1);

        $this->assertDatabaseHas('provider_webhook_logs', [
            'provider' => 'globaltix',
            'event'    => 'BOOKING_TICKET_UPDATE',
            'status'   => 'queued',
        ]);

        $booking = BookingInfo::where('booking_reference', 'GT12345678')->firstOrFail();
        $this->assertSame('CONFIRMED', (string) $booking->status);
        $this->assertSame(null, $booking->e_ticket_url);
    }

    public function test_rejects_mismatched_event_type_for_specific_route()
    {
        $secret = 'TEST_MOCK_GLOBALTIX_WEBHOOK_SECRET';
        config(['services.globaltix.webhook_secret' => $secret]);

        $payload = [
            'eventType' => 'PRICE_UPDATE',
            'timestamp' => '2024-09-17T16:25:20',
            'data' => [
                'productId' => 1953,
                'newNettPrice' => 18188.0,
                'currency' => 'SGD',
            ],
        ];
        $payload['signature'] = $this->signatureFor($payload['eventType'], $payload['timestamp'], $secret);

        $response = $this->postJson('/api/v1/webhook/globaltix/booking-ticket-update', $payload);
        $response->assertStatus(422);

        $this->assertDatabaseHas('provider_webhook_logs', [
            'provider' => 'globaltix',
            'status' => 'rejected',
        ]);
    }

    public function test_price_update_updates_only_targeted_globaltix_rows()
    {
        $secret = 'TEST_MOCK_GLOBALTIX_WEBHOOK_SECRET';
        config(['services.globaltix.webhook_secret' => $secret]);

        $product = Product::create([
            'product_api_id' => '1953',
            'slug' => 'globaltix-price-product',
            'platform' => 'globaltix',
            'provider' => 'globaltix',
            'title' => 'GlobalTix Product',
            'status' => 'active',
        ]);

        $optionA = ProductOption::create([
            'product_id' => $product->id,
            'provider' => 'globaltix',
            'external_id' => '6808',
            'name' => 'Option A',
        ]);

        $optionB = ProductOption::create([
            'product_id' => $product->id,
            'provider' => 'globaltix',
            'external_id' => '6809',
            'name' => 'Option B',
        ]);

        $categoryA = OptionPricingCategory::create([
            'product_option_id' => $optionA->id,
            'external_id' => '9961',
            'category_code' => 'ADULT',
            'category_label' => 'Adult',
        ]);

        $categoryB = OptionPricingCategory::create([
            'product_option_id' => $optionB->id,
            'external_id' => '9962',
            'category_code' => 'CHILD',
            'category_label' => 'Child',
        ]);

        $priceA = Price::create([
            'product_id' => $product->id,
            'product_option_id' => $optionA->id,
            'pricing_category_id' => $categoryA->id,
            'provider' => 'globaltix',
            'selling_price' => 25.00,
            'nett_price' => 20.00,
            'currency' => 'SGD',
            'is_default' => true,
        ]);

        $priceB = Price::create([
            'product_id' => $product->id,
            'product_option_id' => $optionB->id,
            'pricing_category_id' => $categoryB->id,
            'provider' => 'globaltix',
            'selling_price' => 30.00,
            'nett_price' => 22.00,
            'currency' => 'SGD',
        ]);

        $availabilityA = AvailabilityCheck::create([
            'product_id' => $product->id,
            'product_option_id' => $optionA->id,
            'provider' => 'globaltix',
            'available_date' => now()->toDateString(),
            'start_time' => '10:00:00',
        ]);

        $availabilityB = AvailabilityCheck::create([
            'product_id' => $product->id,
            'product_option_id' => $optionB->id,
            'provider' => 'globaltix',
            'available_date' => now()->toDateString(),
            'start_time' => '11:00:00',
        ]);

        $acpA = AvailabilityCategoryPrice::create([
            'availability_id' => $availabilityA->id,
            'pricing_category_id' => $categoryA->id,
            'category_code' => 'ADULT',
            'category_label' => 'Adult',
            'selling_price' => 25.00,
            'nett_price' => 20.00,
            'currency' => 'SGD',
        ]);

        $acpB = AvailabilityCategoryPrice::create([
            'availability_id' => $availabilityB->id,
            'pricing_category_id' => $categoryB->id,
            'category_code' => 'CHILD',
            'category_label' => 'Child',
            'selling_price' => 30.00,
            'nett_price' => 22.00,
            'currency' => 'SGD',
        ]);

        $payload = [
            'eventType' => 'PRICE_UPDATE',
            'timestamp' => '2024-09-17T16:25:20',
            'data' => [
                'productId' => 1953,
                'optionId' => 6808,
                'ticketTypeId' => 9961,
                'newNettPrice' => 18188.0,
                'currency' => 'SGD',
            ],
        ];
        $payload['signature'] = $this->signatureFor($payload['eventType'], $payload['timestamp'], $secret);

        $response = $this->postJson('/api/v1/webhook/globaltix/ticket-type-price-update', $payload);
        $response->assertAccepted();
        $response->assertJsonPath('event', 'PRICE_UPDATE');

        $priceA->refresh();
        $priceB->refresh();
        $acpA->refresh();
        $acpB->refresh();

        $this->assertEquals(18188.0, (float) $priceA->nett_price);
        $this->assertEquals(22.0, (float) $priceB->nett_price);
        $this->assertEquals(18188.0, (float) $acpA->nett_price);
        $this->assertEquals(22.0, (float) $acpB->nett_price);
    }

    public function test_ticket_redeem_alias_event_updates_booking_status_and_tickets()
    {
        Queue::fake();

        $secret = 'TEST_MOCK_GLOBALTIX_WEBHOOK_SECRET';
        config(['services.globaltix.webhook_secret' => $secret]);

        $booking = BookingInfo::create([
            'booking_reference' => 'LB6YX7ZKGT',
            'provider_type' => 'globaltix',
            'status' => 'READY',
            'total_price' => 100,
            'currency' => 'USD',
            'globaltix_tickets' => [
                ['code' => 'GTHNCCUC', 'status' => 'READY'],
            ],
        ]);

        $payload = [
            'eventType' => 'BOOKING_TICKET_REDEEM',
            'timestamp' => '2024-09-07T09:46:38',
            'data' => [
                'referenceNumber' => 'LB6YX7ZKGT',
                'tickets' => [
                    ['id' => 823127, 'code' => 'GTHNCCUC', 'quantity' => 1],
                    ['id' => 823128, 'code' => 'GTUV5EZT', 'quantity' => 1],
                ],
            ],
        ];
        $payload['signature'] = $this->signatureFor($payload['eventType'], $payload['timestamp'], $secret);

        $response = $this->postJson('/api/v1/webhook/globaltix/ticket-redeem', $payload);
        $response->assertAccepted();
        $response->assertJsonPath('event', 'TICKET_REDEEM');

        Queue::assertPushed(ProcessGlobalTixBookingWebhookJob::class, 1);

        $this->assertDatabaseHas('provider_webhook_logs', [
            'provider' => 'globaltix',
            'event' => 'TICKET_REDEEM',
            'status' => 'queued',
        ]);

        $booking->refresh();
        $this->assertSame('READY', (string) $booking->status);
        $ticketMap = collect((array) $booking->globaltix_tickets)->keyBy(fn(array $t) => strtoupper((string) ($t['code'] ?? '')));
        $this->assertSame('READY', strtoupper((string) data_get($ticketMap->get('GTHNCCUC'), 'status')));
        $this->assertSame(null, data_get($ticketMap->get('GTUV5EZT'), 'status'));
    }

    public function test_product_info_update_updates_titles_and_location_fields()
    {
        $secret = 'TEST_MOCK_GLOBALTIX_WEBHOOK_SECRET';
        config(['services.globaltix.webhook_secret' => $secret]);

        $product = Product::create([
            'product_api_id' => '5025',
            'slug' => 'globaltix-product-5025',
            'platform' => 'globaltix',
            'provider' => 'globaltix',
            'title' => 'Old Product Name',
            'status' => 'active',
        ]);

        TempProduct::create([
            'product_api_id' => '5025',
            'api_platform' => 'globaltix',
            'title' => 'Old Temp Name',
            'description' => 'Temp',
            'slug' => 'temp-5025',
            'status' => 'active',
        ]);

        \App\Models\Location::create([
            'product_id' => $product->id,
            'city' => 'Old City',
            'countryCode' => 'US',
            'country' => 'United States',
            'city_id' => '999',
        ]);

        $payload = [
            'eventType' => 'PRODUCT_INFO_UPDATE',
            'timestamp' => '2024-09-17T09:49:30',
            'data' => [
                'productId' => 5025,
                'countryCode' => 'SG',
                'cityId' => 1,
                'name' => 'Cable Car (Mount Faber Line)',
                'lastUpdated' => '2024-09-17T09:49:30',
            ],
        ];
        $payload['signature'] = $this->signatureFor($payload['eventType'], $payload['timestamp'], $secret);

        $response = $this->postJson('/api/v1/webhook/globaltix/product-info-update', $payload);
        $response->assertAccepted();
        $response->assertJsonPath('event', 'PRODUCT_INFO_UPDATE');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'title' => 'Cable Car (Mount Faber Line)',
            'provider' => 'globaltix',
        ]);

        $this->assertDatabaseHas('temp_products', [
            'product_api_id' => '5025',
            'api_platform' => 'globaltix',
            'title' => 'Cable Car (Mount Faber Line)',
        ]);

        $this->assertDatabaseHas('locations', [
            'product_id' => $product->id,
            'countryCode' => 'SG',
            'city_id' => '1',
        ]);
    }

    public function test_price_update_can_use_event_specific_secret_override()
    {
        config([
            'services.globaltix.webhook_secret' => 'GLOBAL_DEFAULT_SECRET',
            'services.globaltix.webhook_secrets.PRICE_UPDATE' => 'PRICE_EVENT_SECRET',
        ]);

        $product = Product::create([
            'product_api_id' => '3001',
            'slug' => 'globaltix-product-3001',
            'platform' => 'globaltix',
            'provider' => 'globaltix',
            'title' => 'Price Secret Product',
            'status' => 'active',
        ]);

        $option = ProductOption::create([
            'product_id' => $product->id,
            'provider' => 'globaltix',
            'external_id' => '7777',
            'name' => 'Secret Option',
        ]);

        $category = OptionPricingCategory::create([
            'product_option_id' => $option->id,
            'external_id' => '8888',
            'category_code' => 'ADULT',
            'category_label' => 'Adult',
        ]);

        $price = Price::create([
            'product_id' => $product->id,
            'product_option_id' => $option->id,
            'pricing_category_id' => $category->id,
            'provider' => 'globaltix',
            'selling_price' => 50,
            'nett_price' => 40,
            'currency' => 'USD',
        ]);

        $payload = [
            'eventType' => 'PRICE_UPDATE',
            'timestamp' => '2024-09-17T16:25:20',
            'data' => [
                'productId' => 3001,
                'optionId' => 7777,
                'ticketTypeId' => 8888,
                'newNettPrice' => 99.0,
                'currency' => 'USD',
            ],
        ];

        $payload['signature'] = $this->signatureFor($payload['eventType'], $payload['timestamp'], 'PRICE_EVENT_SECRET');
        $response = $this->postJson('/api/v1/webhook/globaltix/ticket-type-price-update', $payload);

        $response->assertAccepted();
        $price->refresh();
        $this->assertEquals(99.0, (float) $price->nett_price);
    }

    private function signatureFor(string $eventType, string $timestamp, string $secret): string
    {
        return hash_hmac('sha256', "{$eventType}|{$timestamp}", $secret);
    }
}
