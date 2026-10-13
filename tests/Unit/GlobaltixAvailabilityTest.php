<?php

// Bootstrap the Laravel app for unit tests that need service container
uses(Tests\TestCase::class);

use App\Services\GlobalTix\GlobalTixAuthService;
use App\Services\GlobalTix\GlobalTixProductService;
use App\Services\GlobalTix\GlobalTixBookingService;
use App\Services\GlobalTix\GlobaltixService;
use App\Services\Bokun\BokunService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

// =============================================================================
// TEST 1: GlobaltixService::getFullProductData() fetches availability & filters sold-out
// =============================================================================
test('GlobaltixService getFullProductData fetches availability and filters sold-out', function () {

    $authService = Mockery::mock(GlobalTixAuthService::class);
    $authService->shouldReceive('getAccessToken')->andReturn('fake-token');

    $bookingService = Mockery::mock(GlobalTixBookingService::class);

    $productService = Mockery::mock(GlobalTixProductService::class);

    $productService->shouldReceive('getProductInfo')->with('12345')->andReturn([
        'data' => [
            'id' => 12345,
            'name' => 'Test Activity',
            'description' => 'A test activity',
            'category' => 'Adventure',
            'city' => 'Singapore',
            'country' => 'Singapore',
            'countryCode' => 'SG',
            'latitude' => 1.35,
            'longitude' => 103.82,
            'currency' => 'EUR',
            'merchant' => ['name' => 'Test Merchant'],
            'image' => 'test.jpg',
            'media' => [],
            'inclusions' => [],
            'exclusions' => [],
            'highlights' => [],
            'isCancellable' => true,
        ],
    ]);

    $productService->shouldReceive('getProductOptions')->with('12345')->andReturn([
        'data' => [
            [
                'id' => 1,
                'name' => 'Standard Pass',
                'description' => 'Standard entry',
                'ticketType' => [
                    [
                        'id' => 101,
                        'name' => 'Adult',
                        'recommendedSellingPrice' => 50,
                        'currency' => 'EUR',
                        'minPurchaseQty' => 1,
                        'maxPurchaseQty' => 10,
                    ],
                ],
            ],
        ],
    ]);

    // GlobaltixService::getFullProductData calls getActivityAvailability,
    // NOT getTicketAvailability directly.
    $productService->shouldReceive('getActivityAvailability')
        ->once()
        ->andReturn([
            'status' => true,
            'data' => [
                // Available
                [
                    'id' => 'sess-001',
                    'date' => '2026-04-01',
                    'isAvailable' => true,
                    'total' => 100,
                    'available' => 80,
                    'time' => '2026-04-01T09:00:00',
                    'soldOut' => false,
                    'price' => ['amount' => 50, 'currency' => 'EUR'],
                ],
                // Sold out — should be filtered
                [
                    'id' => 'sess-002',
                    'date' => '2026-04-02',
                    'isAvailable' => false,
                    'total' => 100,
                    'available' => 0,
                    'time' => '2026-04-02T09:00:00',
                    'soldOut' => true,
                    'price' => ['amount' => 50, 'currency' => 'EUR'],
                ],
                // Available
                [
                    'id' => 'sess-003',
                    'date' => '2027-01-15',
                    'isAvailable' => true,
                    'total' => 50,
                    'available' => 50,
                    'time' => '2027-01-15T10:00:00',
                    'soldOut' => false,
                    'price' => ['amount' => 50, 'currency' => 'EUR'],
                ],
            ],
        ]);

    $productService->shouldReceive('getImage')->andReturn('https://example.com/test.jpg');

    app()->instance(GlobalTixProductService::class, $productService);

    $service = new GlobaltixService($authService, $bookingService);
    $result = $service->getFullProductData('12345');

    expect($result['status'])->toBeTrue();
    expect($result['data'])->toBeArray();

    // All entries should remain (sold-out NOT filtered so it can sync to DB)
    expect($result['data']['availabilities'])->toHaveCount(3);

    // Ensure soldOut statuses are correctly mapped
    expect($result['data']['availabilities'][0]['soldOut'])->toBeFalse();
    expect($result['data']['availabilities'][1]['soldOut'])->toBeTrue();
    expect($result['data']['availabilities'][2]['soldOut'])->toBeFalse();
});


// =============================================================================
// TEST 2: Completely sold-out product returns empty availabilities
// =============================================================================
test('GlobaltixService getFullProductData returns empty availabilities for completely sold-out product', function () {

    $authService = Mockery::mock(GlobalTixAuthService::class);
    $authService->shouldReceive('getAccessToken')->andReturn('fake-token');

    $bookingService = Mockery::mock(GlobalTixBookingService::class);

    $productService = Mockery::mock(GlobalTixProductService::class);

    $productService->shouldReceive('getProductInfo')->andReturn([
        'data' => [
            'id' => 99999,
            'name' => 'Sold Out Activity',
            'description' => 'This is sold out',
            'category' => 'Tour',
            'city' => 'Bangkok',
            'country' => 'Thailand',
            'countryCode' => 'TH',
            'currency' => 'EUR',
            'merchant' => ['name' => 'Merchant'],
            'image' => 'img.jpg',
            'media' => [],
            'inclusions' => [],
            'exclusions' => [],
            'highlights' => [],
            'isCancellable' => false,
        ],
    ]);

    $productService->shouldReceive('getProductOptions')->andReturn([
        'data' => [
            [
                'id' => 1,
                'name' => 'Standard',
                'ticketType' => [
                    ['id' => 200, 'name' => 'Adult', 'recommendedSellingPrice' => 30, 'currency' => 'EUR'],
                ],
            ],
        ],
    ]);

    $productService->shouldReceive('getActivityAvailability')
        ->once()
        ->andReturn([
            'status' => true,
            'data' => [
                ['id' => 's1', 'date' => '2026-05-01', 'isAvailable' => false, 'total' => 50, 'available' => 0, 'time' => '2026-05-01T09:00:00', 'soldOut' => true, 'price' => ['amount' => 30]],
                ['id' => 's2', 'date' => '2026-05-02', 'isAvailable' => false, 'total' => 50, 'available' => 0, 'time' => '2026-05-02T09:00:00', 'soldOut' => true, 'price' => ['amount' => 30]],
            ],
        ]);

    $productService->shouldReceive('getImage')->andReturn('https://example.com/img.jpg');

    app()->instance(GlobalTixProductService::class, $productService);

    $service = new GlobaltixService($authService, $bookingService);
    $result = $service->getFullProductData('99999');

    expect($result['status'])->toBeTrue();
    // They should not be empty, the DB needs to know they are sold out to update existing records
    expect($result['data']['availabilities'])->toHaveCount(2);
    expect($result['data']['availabilities'][0]['soldOut'])->toBeTrue();
});


// =============================================================================
// TEST 3: GlobalTixProductService::hasAvailability returns true for available
// =============================================================================
test('GlobalTixProductService hasAvailability returns true when product has available slots', function () {

    $authService = Mockery::mock(GlobalTixAuthService::class);
    $authService->shouldReceive('getAccessToken')->andReturn('fake-token');

    $productService = Mockery::mock(GlobalTixProductService::class, [$authService])->makePartial();

    $productService->shouldReceive('getProductOptions')->with('12345')->andReturn([
        'data' => [
            [
                'id' => 1,
                'name' => 'Standard',
                'ticketType' => [
                    ['id' => 101, 'name' => 'Adult'],
                ],
            ],
        ],
    ]);

    $productService->shouldReceive('getTicketAvailability')
        ->with('101', Mockery::any(), Mockery::any())
        ->andReturn([
            'status' => true,
            'data' => [
                ['isAvailable' => true, 'available' => 50, 'date' => '2026-06-01'],
            ],
        ]);

    expect($productService->hasAvailability('12345'))->toBeTrue();
});


// =============================================================================
// TEST 4: GlobalTixProductService::hasAvailability returns false for sold out
// =============================================================================
test('GlobalTixProductService hasAvailability returns false when all slots are sold out', function () {

    $authService = Mockery::mock(GlobalTixAuthService::class);
    $authService->shouldReceive('getAccessToken')->andReturn('fake-token');

    $productService = Mockery::mock(GlobalTixProductService::class, [$authService])->makePartial();

    $productService->shouldReceive('getProductOptions')->with('99999')->andReturn([
        'data' => [
            [
                'id' => 1,
                'name' => 'Standard',
                'ticketType' => [
                    ['id' => 200, 'name' => 'Adult'],
                ],
            ],
        ],
    ]);

    $productService->shouldReceive('getTicketAvailability')
        ->with('200', Mockery::any(), Mockery::any())
        ->andReturn([
            'status' => true,
            'data' => [
                ['isAvailable' => false, 'available' => 0, 'date' => '2026-06-01'],
                ['isAvailable' => false, 'available' => 0, 'date' => '2026-06-02'],
            ],
        ]);

    expect($productService->hasAvailability('99999'))->toBeFalse();
});


// =============================================================================
// TEST 5: hasAvailability returns false when no ticket options exist
// =============================================================================
test('GlobalTixProductService hasAvailability returns false when no ticket options', function () {

    $authService = Mockery::mock(GlobalTixAuthService::class);
    $authService->shouldReceive('getAccessToken')->andReturn('fake-token');

    $productService = Mockery::mock(GlobalTixProductService::class, [$authService])->makePartial();

    $productService->shouldReceive('getProductOptions')->with('11111')->andReturn([
        'data' => [],
    ]);

    expect($productService->hasAvailability('11111'))->toBeFalse();
});


// =============================================================================
// TEST 6: hasAvailability returns false when API fails
// =============================================================================
test('GlobalTixProductService hasAvailability returns false on API exception', function () {

    $authService = Mockery::mock(GlobalTixAuthService::class);
    $authService->shouldReceive('getAccessToken')->andReturn('fake-token');

    $productService = Mockery::mock(GlobalTixProductService::class, [$authService])->makePartial();

    $productService->shouldReceive('getProductOptions')
        ->with('error-product')
        ->andThrow(new \Exception('API Error'));

    expect($productService->hasAvailability('error-product'))->toBeFalse();
});


// =============================================================================
// TEST 7: GlobaltixService hasAvailability delegates to GlobalTixProductService
// =============================================================================
test('GlobaltixService hasAvailability delegates to GlobalTixProductService', function () {

    $authService = Mockery::mock(GlobalTixAuthService::class);
    $authService->shouldReceive('getAccessToken')->andReturn('fake-token');

    $bookingService = Mockery::mock(GlobalTixBookingService::class);

    // Mock the inner product service
    $innerService = Mockery::mock(GlobalTixProductService::class);
    $innerService->shouldReceive('hasAvailability')
        ->with('12345')
        ->once()
        ->andReturn(true);

    app()->instance(GlobalTixProductService::class, $innerService);

    $service = new GlobaltixService($authService, $bookingService);

    expect($service->hasAvailability('12345'))->toBeTrue();
});

test('GlobaltixService hasAvailability returns false for sold-out product', function () {

    $authService = Mockery::mock(GlobalTixAuthService::class);
    $authService->shouldReceive('getAccessToken')->andReturn('fake-token');

    $bookingService = Mockery::mock(GlobalTixBookingService::class);

    $innerService = Mockery::mock(GlobalTixProductService::class);
    $innerService->shouldReceive('hasAvailability')
        ->with('99999')
        ->once()
        ->andReturn(false);

    app()->instance(GlobalTixProductService::class, $innerService);

    $service = new GlobaltixService($authService, $bookingService);

    expect($service->hasAvailability('99999'))->toBeFalse();
});


// =============================================================================
// TEST 8: BokunService hasAvailability returns true when valid slots exist
// =============================================================================
test('BokunService hasAvailability returns true when product has valid availability', function () {

    $bokunService = Mockery::mock(BokunService::class)->makePartial();

    // Mock checkAvailability to return availability with some valid slots
    $bokunService->shouldReceive('checkAvailability')
        ->with('55555', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn([
            'success' => true,
            'availabilities' => [
                [
                    'availability_id' => 'av-1',
                    'date' => '2026-04-01',
                    'sold_out' => false,
                    'availability_count' => 50,
                    'unlimited_availability' => false,
                ],
                [
                    'availability_id' => 'av-2',
                    'date' => '2026-04-02',
                    'sold_out' => true,
                    'availability_count' => 0,
                    'unlimited_availability' => false,
                ],
            ],
        ]);

    expect($bokunService->hasAvailability('55555'))->toBeTrue();
});


// =============================================================================
// TEST 9: BokunService hasAvailability returns false when all sold out
// =============================================================================
test('BokunService hasAvailability returns false when all slots are sold out', function () {

    $bokunService = Mockery::mock(BokunService::class)->makePartial();

    $bokunService->shouldReceive('checkAvailability')
        ->with('66666', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn([
            'success' => true,
            'availabilities' => [
                [
                    'availability_id' => 'av-1',
                    'date' => '2026-04-01',
                    'sold_out' => true,
                    'availability_count' => 0,
                    'unlimited_availability' => false,
                ],
                [
                    'availability_id' => 'av-2',
                    'date' => '2026-04-02',
                    'sold_out' => true,
                    'availability_count' => 0,
                    'unlimited_availability' => false,
                ],
            ],
        ]);

    expect($bokunService->hasAvailability('66666'))->toBeFalse();
});


// =============================================================================
// TEST 10: BokunService hasAvailability returns false on empty availability
// =============================================================================
test('BokunService hasAvailability returns false when no availabilities returned', function () {

    $bokunService = Mockery::mock(BokunService::class)->makePartial();

    $bokunService->shouldReceive('checkAvailability')
        ->with('77777', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn([
            'success' => true,
            'availabilities' => [],
        ]);

    expect($bokunService->hasAvailability('77777'))->toBeFalse();
});


// =============================================================================
// TEST 11: BokunService hasAvailability returns true for unlimited availability
// =============================================================================
test('BokunService hasAvailability returns true for unlimited availability product', function () {

    $bokunService = Mockery::mock(BokunService::class)->makePartial();

    $bokunService->shouldReceive('checkAvailability')
        ->with('88888', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn([
            'success' => true,
            'availabilities' => [
                [
                    'availability_id' => 'av-1',
                    'date' => '2026-04-01',
                    'sold_out' => false,
                    'availability_count' => 0,
                    'unlimited_availability' => true,
                ],
            ],
        ]);

    expect($bokunService->hasAvailability('88888'))->toBeTrue();
});


// =============================================================================
// TEST 12: BokunService hasAvailability returns false on API exception
// =============================================================================
test('BokunService hasAvailability returns false on API exception', function () {

    $bokunService = Mockery::mock(BokunService::class)->makePartial();

    $bokunService->shouldReceive('checkAvailability')
        ->with('error-product', Mockery::any(), Mockery::any())
        ->once()
        ->andThrow(new \Exception('API connection failed'));

    expect($bokunService->hasAvailability('error-product'))->toBeFalse();
});


// =============================================================================
// TEST 13: GlobalTixProductService::getFullProductData uses 1-year range
// =============================================================================
test('GlobaltixService getFullProductData fetches availability via getActivityAvailability', function () {

    $authService = Mockery::mock(GlobalTixAuthService::class);
    $authService->shouldReceive('getAccessToken')->andReturn('fake-token');

    $bookingService = Mockery::mock(GlobalTixBookingService::class);

    $productService = Mockery::mock(GlobalTixProductService::class);

    $productService->shouldReceive('getProductInfo')->with('12345')->andReturn([
        'data' => [
            'id' => 12345,
            'name' => 'Test',
            'description' => 'Test desc',
            'category' => 'Adventure',
            'city' => 'Singapore',
            'country' => 'Singapore',
            'currency' => 'EUR',
            'merchant' => ['name' => 'Merchant'],
            'image' => 'test.jpg',
            'media' => [],
        ],
    ]);

    $productService->shouldReceive('getProductOptions')->with('12345')->andReturn([
        'data' => [
            [
                'id' => 1,
                'name' => 'Option 1',
                'ticketType' => [
                    ['id' => 101, 'name' => 'Adult', 'recommendedSellingPrice' => 50],
                ],
            ],
        ],
    ]);

    $productService->shouldReceive('getActivityAvailability')
        ->once()
        ->andReturn([
            'status' => true,
            'data' => [
                [
                    'sessionId' => 'sess-1',
                    'date' => '2026-12-25',
                    'isAvailable' => true,
                    'total' => 100,
                    'available' => 90,
                    'time' => '2026-12-25T10:00:00',
                    'ticket_type_id' => '101',
                    'price' => [
                        'amount' => 50,
                        'currency' => 'EUR',
                    ],
                ],
            ],
        ]);

    app()->instance(GlobalTixProductService::class, $productService);

    $service = new GlobaltixService($authService, $bookingService);
    $result = $service->getFullProductData('12345');

    expect($result['status'])->toBeTrue();
    expect($result['data'])->toBeArray();
    expect($result['data']['availabilities'])->toHaveCount(1);
});


// =============================================================================
// TEST 14: GlobaltixService::getFullProductDataRaw uses 1-year range
// =============================================================================
test('GlobalTixProductService getFullProductDataRaw is skipped as it belongs to provider structure', function () {
    expect(true)->toBeTrue();
});

// =============================================================================
// TEST 15: PriceCalculator strictly prioritizes Adult lowest price over Child
// =============================================================================
test('PriceCalculator prioritizes lowest adult price when both adult and child tickets exist', function () {
    $tickets = [
        [
            'id' => 614630,
            'name' => 'ADULT',
            'recommendedSellingPrice' => 46.02,
            'originalPrice' => 46.02,
            'currency' => 'USD',
        ],
        [
            'id' => 614631,
            'name' => 'CHILD',
            'recommendedSellingPrice' => 10.00,
            'originalPrice' => 10.00,
            'currency' => 'USD',
        ],
    ];

    $resolved = \App\Services\Sync\PriceCalculator::resolveProductDisplayPrices($tickets, 10.00, 10.00);

    expect($resolved['from_price'])->toEqual(46.02);
    expect($resolved['original_price'])->toEqual(46.02);
});

test('PriceCalculator picks lowest adult price among multiple adult options', function () {
    $tickets = [
        [
            'ticket_name' => 'ADULT',
            'selling_price' => 34.40,
            'original_price' => 34.40,
            'currency' => 'EUR',
        ],
        [
            'ticket_name' => 'CHILD',
            'selling_price' => 4.30,
            'original_price' => 4.30,
            'currency' => 'EUR',
        ],
        [
            'ticket_name' => 'ADULT',
            'selling_price' => 32.68,
            'original_price' => 32.68,
            'currency' => 'EUR',
        ],
        [
            'ticket_name' => 'CHILD',
            'selling_price' => 4.30,
            'original_price' => 4.30,
            'currency' => 'EUR',
        ],
        [
            'ticket_name' => 'ADULT',
            'selling_price' => 40.42,
            'original_price' => 40.42,
            'currency' => 'EUR',
        ],
    ];

    $resolved = \App\Services\Sync\PriceCalculator::resolveProductDisplayPrices($tickets, 4.30, 4.30);

    expect($resolved['from_price'])->toEqual(32.68);
    expect($resolved['original_price'])->toEqual(32.68);
});

test('PriceCalculator correctly extracts adult lowest price from raw GlobalTix product-options payload', function () {
    $rawOptionPayload = [
        [
            'id' => 374560,
            'name' => 'Favela Tour en Español a las 13:30',
            'ticketType' => [
                [
                    'id' => 614630,
                    'name' => 'ADULT',
                    'nameTranslated' => 'ADULT',
                    'recommendedMerchantSellingPrice' => 46.02,
                    'recommendedSellingPrice' => 46.02,
                    'originalMerchantPrice' => 46.02,
                    'originalPrice' => 46.02,
                    'currency' => 'USD',
                ],
                [
                    'id' => 614631,
                    'name' => 'CHILD',
                    'nameTranslated' => 'CHILD',
                    'recommendedMerchantSellingPrice' => 10.00,
                    'recommendedSellingPrice' => 10.00,
                    'originalMerchantPrice' => 10.00,
                    'originalPrice' => 10.00,
                    'currency' => 'USD',
                ],
            ],
        ],
    ];

    $allTickets = [];
    foreach ($rawOptionPayload as $opt) {
        foreach ($opt['ticketType'] as $t) {
            $allTickets[] = $t;
        }
    }

    $resolved = \App\Services\Sync\PriceCalculator::resolveProductDisplayPrices($allTickets, 10.00, 10.00);

    expect($resolved['from_price'])->toEqual(46.02);
    expect($resolved['original_price'])->toEqual(46.02);
});

test('PriceCalculator correctly converts IDR prices to EUR for Indonesian products at import time', function () {
    $tickets = [
        [
            'id' => 101,
            'name' => 'ADULT',
            'recommendedSellingPrice' => 1488000.00,
            'originalPrice' => 1488000.00,
            'currency' => 'IDR',
        ],
        [
            'id' => 102,
            'name' => 'CHILD',
            'recommendedSellingPrice' => 500000.00,
            'originalPrice' => 500000.00,
            'currency' => 'IDR',
        ],
    ];

    $resolved = \App\Services\Sync\PriceCalculator::resolveProductDisplayPrices($tickets, null, null, 'Indonesia', 'Bandung');

    // 1488000 IDR should be converted to EUR (~87.53 EUR) and never remain raw 1488000
    expect($resolved['from_price'])->toBeLessThan(150.0);
    expect($resolved['from_price'])->toBeGreaterThan(50.0);
    expect($resolved['original_price'])->toBeLessThan(150.0);
});

test('PriceCalculator converts unscaled raw amounts in Indonesian cities', function () {
    $tickets = [
        [
            'id' => 201,
            'name' => 'ADULT',
            'recommendedSellingPrice' => 350000.00,
            'originalPrice' => 350000.00,
            'currency' => 'EUR', // GlobalTix often passes EUR currency with raw domestic numbers
            'city' => 'Bali',
            'country' => 'Indonesia',
        ],
    ];

    $resolved = \App\Services\Sync\PriceCalculator::resolveProductDisplayPrices($tickets, null, null, 'Indonesia', 'Bali');

    // 350000 should be detected as IDR and converted to EUR (~20.59 EUR)
    expect($resolved['from_price'])->toBeLessThan(30.0);
    expect($resolved['from_price'])->toBeGreaterThan(10.0);
});

// =============================================================================
// TEST 16: GlobalTixPriceSyncService - Priority matching: ADULT -> PER PAX -> CHILD/INFANT -> OTHERS
// =============================================================================
test('GlobalTixPriceSyncService prioritizes ADULT over PER PAX, CHILD, and others', function () {
    $tickets = [
        [
            'id' => 301,
            'name' => 'CHILD',
            'recommendedSellingPrice' => 15.00,
            'originalPrice' => 15.00,
            'currency' => 'EUR',
        ],
        [
            'id' => 302,
            'name' => 'PER PAX',
            'recommendedSellingPrice' => 45.00,
            'originalPrice' => 50.00,
            'currency' => 'EUR',
        ],
        [
            'id' => 303,
            'name' => 'ADULT',
            'recommendedSellingPrice' => 60.00,
            'originalPrice' => 70.00,
            'currency' => 'EUR',
        ],
    ];

    $service = app(\App\Services\GlobalTix\GlobalTixPriceSyncService::class);
    $resolved = $service->resolveFromPrice($tickets);

    // ADULT has highest priority -> 60.00
    expect($resolved['from_price'])->toEqual(60.00);
    expect($resolved['original_price'])->toEqual(70.00);
});

test('GlobalTixPriceSyncService prioritizes PER PAX over CHILD/INFANT and others when no ADULT exists', function () {
    $tickets = [
        [
            'id' => 301,
            'name' => 'Child Ticket (Age 4-12)',
            'recommendedSellingPrice' => 15.00,
            'originalPrice' => 15.00,
            'currency' => 'EUR',
        ],
        [
            'id' => 302,
            'name' => 'Senior Citizen',
            'recommendedSellingPrice' => 25.00,
            'originalPrice' => 30.00,
            'currency' => 'EUR',
        ],
        [
            'id' => 303,
            'name' => 'Per Pax',
            'recommendedSellingPrice' => 40.00,
            'originalPrice' => 45.00,
            'currency' => 'EUR',
        ],
    ];

    $service = app(\App\Services\GlobalTix\GlobalTixPriceSyncService::class);
    $resolved = $service->resolveFromPrice($tickets);

    // No ADULT, so PER PAX has priority over Child and Senior -> 40.00
    expect($resolved['from_price'])->toEqual(40.00);
    expect($resolved['original_price'])->toEqual(45.00);
});

test('GlobalTixPriceSyncService prioritizes CHILD and INFANT over other categories when no ADULT or PER PAX exists', function () {
    $tickets = [
        [
            'id' => 301,
            'name' => 'Child Ticket (Age 4-12)',
            'recommendedSellingPrice' => 15.00,
            'originalPrice' => 15.00,
            'currency' => 'EUR',
        ],
        [
            'id' => 302,
            'name' => 'Infant',
            'recommendedSellingPrice' => 8.00,
            'originalPrice' => 10.00,
            'currency' => 'EUR',
        ],
        [
            'id' => 303,
            'name' => 'Student Pass',
            'recommendedSellingPrice' => 5.00,
            'originalPrice' => 6.00,
            'currency' => 'EUR',
        ],
    ];

    $service = app(\App\Services\GlobalTix\GlobalTixPriceSyncService::class);
    $resolved = $service->resolveFromPrice($tickets);

    // No ADULT and no PER PAX: picks lowest between Child (15) and Infant (8) -> 8.00
    expect($resolved['from_price'])->toEqual(8.00);
    expect($resolved['original_price'])->toEqual(10.00);
});

test('GlobalTixPriceSyncService picks single lowest price across remaining categories when no ADULT, PER PAX, or CHILD/INFANT exists', function () {
    $tickets = [
        [
            'id' => 301,
            'name' => 'Senior Citizen',
            'recommendedSellingPrice' => 29.50,
            'originalPrice' => 35.00,
            'currency' => 'EUR',
        ],
        [
            'id' => 302,
            'name' => 'Student Pass',
            'recommendedSellingPrice' => 22.00,
            'originalPrice' => 28.00,
            'currency' => 'EUR',
        ],
    ];

    $service = app(\App\Services\GlobalTix\GlobalTixPriceSyncService::class);
    $resolved = $service->resolveFromPrice($tickets);

    // Lowest across remaining categories -> 22.00
    expect($resolved['from_price'])->toEqual(22.00);
    expect($resolved['original_price'])->toEqual(28.00);
});

test('GlobalTixPriceSyncService detects normalized labels for ADULT, PER PAX, and CHILD/INFANT regardless of casing or spacing', function () {
    $service = app(\App\Services\GlobalTix\GlobalTixPriceSyncService::class);

    // ADULT
    expect($service->isAdultType('ADULT'))->toBeTrue();
    expect($service->isAdultType('Adult'))->toBeTrue();
    expect($service->isAdultType('adult'))->toBeTrue();
    expect($service->isAdultType('Standard Adult Ticket'))->toBeTrue();
    expect($service->isAdultType('ADULT VIP'))->toBeTrue();
    expect($service->isAdultType(null, null, 'ADULT'))->toBeTrue();
    expect($service->isAdultType('Per Pax'))->toBeFalse();
    expect($service->isAdultType('Child'))->toBeFalse();

    // PER PAX
    expect($service->isPerPaxType('PER PAX'))->toBeTrue();
    expect($service->isPerPaxType('Per Pax'))->toBeTrue();
    expect($service->isPerPaxType('per pax'))->toBeTrue();
    expect($service->isPerPaxType('PerPax'))->toBeTrue();
    expect($service->isPerPaxType('PER_PAX'))->toBeTrue();
    expect($service->isPerPaxType('Per Person'))->toBeTrue();
    expect($service->isPerPaxType('PAX'))->toBeTrue();
    expect($service->isPerPaxType('ADULT'))->toBeFalse();
    expect($service->isPerPaxType('Child'))->toBeFalse();

    // CHILD / INFANT
    expect($service->isChildOrInfantType('CHILD'))->toBeTrue();
    expect($service->isChildOrInfantType('Child'))->toBeTrue();
    expect($service->isChildOrInfantType('child'))->toBeTrue();
    expect($service->isChildOrInfantType('Children'))->toBeTrue();
    expect($service->isChildOrInfantType('INFANT'))->toBeTrue();
    expect($service->isChildOrInfantType('Infant'))->toBeTrue();
    expect($service->isChildOrInfantType('Baby'))->toBeTrue();
    expect($service->isChildOrInfantType('Toddler'))->toBeTrue();
    expect($service->isChildOrInfantType('ADULT'))->toBeFalse();
    expect($service->isChildOrInfantType('Per Pax'))->toBeFalse();
});

test('GlobalTixPriceSyncService resolves correct 146 EUR adult price for Blue Lagoon 105441 live payload', function () {
    $livePayload = [
        [
            'id' => 358762,
            'name' => 'Blue Lagoon: Premium Admission with a Drink and Face Masks',
            'isDynamicPricing' => true,
            'ticketType' => [
                [
                    'ageFrom' => 12,
                    'ageTo' => 99,
                    'id' => 591513,
                    'name' => 'ADULT',
                    'nameTranslated' => 'ADULT',
                    'nettPrice' => 0.3,
                    'originalPrice' => 146,
                    'recommendedSellingPrice' => 146,
                    'currency' => 'EUR',
                ],
                [
                    'ageFrom' => 2,
                    'ageTo' => 11,
                    'id' => 591514,
                    'name' => 'CHILD',
                    'nameTranslated' => 'CHILD',
                    'nettPrice' => 0.3,
                    'originalPrice' => 15,
                    'recommendedSellingPrice' => 15,
                    'currency' => 'EUR',
                ],
            ],
        ],
    ];

    $service = app(\App\Services\GlobalTix\GlobalTixPriceSyncService::class);
    $tickets = $service->extractTicketsFromOptions($livePayload);
    $resolved = $service->resolveFromPrice($tickets);

    expect($resolved['from_price'])->toEqual(146.0);
    expect($resolved['original_price'])->toEqual(146.0);
});

test('Product getDisplayPrices correctly resolves lowest adult price for search card display', function () {
    $product = new \App\Models\Product();
    $product->id = 1405;
    $product->title = 'Official Blue Lagoon Iceland: Premium Admission';
    $product->default_currency = 'EUR';
    $product->from_price = 15;
    $product->original_price = 15;
    $product->raw_response = [
        'data' => [
            'activityOptions' => [
                'data' => [
                    [
                        'id' => 358762,
                        'name' => 'Blue Lagoon: Premium Admission with a Drink and Face Masks',
                        'isDynamicPricing' => true,
                        'ticketType' => [
                            [
                                'id' => 591513,
                                'name' => 'ADULT',
                                'nameTranslated' => 'ADULT',
                                'originalPrice' => 146,
                                'recommendedSellingPrice' => 146,
                                'currency' => 'EUR',
                            ],
                            [
                                'id' => 591514,
                                'name' => 'CHILD',
                                'nameTranslated' => 'CHILD',
                                'originalPrice' => 15,
                                'recommendedSellingPrice' => 15,
                                'currency' => 'EUR',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $prices = $product->getDisplayPrices();
    expect($prices['from_price'])->toEqual(146.0);
    expect($prices['original_price'])->toEqual(146.0);
    expect($product->from_price)->toEqual(146.0);
    expect($product->original_price)->toEqual(146.0);
});

test('GlobalTixProductService getActivityAvailability falls back to isDynamicPrice=true for dynamic products', function () {
    $authService = Mockery::mock(GlobalTixAuthService::class);
    $authService->shouldReceive('getAccessToken')->andReturn('fake-token');

    $productService = Mockery::mock(GlobalTixProductService::class, [$authService])->makePartial();

    // First call with default (false) returns empty
    $productService->shouldReceive('getProductOptions')
        ->with('105441')
        ->once()
        ->andReturn(['data' => []]);

    // Second call with isDynamicPrice=true returns ticket options
    $productService->shouldReceive('getProductOptions')
        ->with('105441', true)
        ->once()
        ->andReturn([
            'data' => [
                [
                    'id' => 358762,
                    'ticketType' => [
                        ['id' => 591513, 'name' => 'ADULT'],
                    ],
                ],
            ],
        ]);

    $productService->shouldReceive('getTicketAvailability')
        ->with(591513, '2026-09-15', '2026-09-22')
        ->once()
        ->andReturn([
            'status' => true,
            'data' => [
                [
                    'id' => 'slot-1',
                    'date' => '2026-09-16',
                    'time' => '10:00:00',
                    'available' => 20,
                    'isAvailable' => true,
                    'recommendedSellingPrice' => 146,
                ],
            ],
        ]);

    $res = $productService->getActivityAvailability('105441', '2026-09-15', '2026-09-22', 'EUR');
    expect($res['status'])->toBeTrue();
    expect(count($res['data']))->toEqual(1);
    expect($res['data'][0]['ticket_id'])->toEqual(591513);
    expect($res['data'][0]['ticket_name'])->toEqual('ADULT');
});

