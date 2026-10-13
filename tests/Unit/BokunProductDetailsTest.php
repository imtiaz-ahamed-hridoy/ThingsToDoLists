<?php

uses(Tests\TestCase::class);

use App\Services\Bokun\BokunService;

test('BokunService getFullProductData returns bokun native data and sync compatibility payload', function () {
    $service = Mockery::mock(BokunService::class)->makePartial();

    $activity = [
        'id' => 768957,
        'title' => 'Manawakie Wildlife sanctuary and Macaw Market ',
        'description' => '<p>Wildlife tour</p>',
        'published' => false,
        'productCategory' => 'ACTIVITIES',
        'activityType' => 'DAY_TOUR_OR_ACTIVITY',
        'bookingType' => 'DATE_AND_TIME',
        'cutoffType' => 'RELATIVE_TO_START_TIME',
        'cutoffReferenceHour' => 0,
        'cutoffReferenceMinute' => 0,
        'capacityType' => 'FREE_SALE',
        'meetingType' => 'PICK_UP',
        'passCapacity' => 10,
        'passesAvailable' => 0,
        'allowCustomizedBookings' => false,
        'passportRequired' => false,
        'timeZone' => 'America/Tegucigalpa',
        'barcodeType' => 'CODE_128',
        'reviewRating' => 0,
        'reviewCount' => 0,
        'included' => 'Air-conditioned vehicle<br />Macaw market',
        'excluded' => '',
        'attention' => '<ul><li>Not wheelchair accessible</li></ul>',
        'excerpt' => 'Island wildlife highlights',
        'keywords' => ['market', 'monkeys'],
        'activityCategories' => ['NATURE'],
        'activityAttributes' => ['OUTDOOR'],
        'pickupService' => true,
        'privateActivity' => false,
        'vendor' => [
            'title' => 'Roatan Henry tour ',
            'currencyCode' => 'EUR',
        ],
        'googlePlace' => [
            'country' => 'Honduras',
            'countryCode' => 'HN',
            'geoLocationCenter' => [
                'lat' => 16.33997,
                'lng' => -86.57963,
            ],
        ],
        'cancellationPolicy' => [
            'title' => 'Standard Viator policy',
            'policyType' => 'ADVANCED',
            'tax' => null,
            'penaltyRules' => [
                [
                    'cutoffHours' => 24,
                    'charge' => 100,
                    'chargeType' => 'percentage',
                    'percentage' => 100,
                ],
            ],
        ],
        'bookingQuestions' => [
            [
                'id' => 1087266,
                'label' => 'Cruise Ship',
                'required' => true,
            ],
        ],
        'passengerFields' => [
            [
                'field' => 'FIRST_NAME',
                'required' => true,
            ],
        ],
        'mainContactFields' => [
            [
                'field' => 'PHONE_NUMBER',
                'required' => true,
            ],
        ],
        'requiredCustomerFields' => ['phoneNumber'],
        'photos' => [
            [
                'originalUrl' => 'https://bokun.example/original.jpg',
                'derived' => [
                    ['name' => 'preview', 'url' => 'https://bokun.example/preview.jpg'],
                    ['name' => 'large', 'url' => 'https://bokun.example/large.jpg'],
                ],
            ],
        ],
        'videos' => [
            [
                'sourceUrl' => 'https://video.example/watch',
                'thumbnailUrl' => 'https://video.example/thumb.jpg',
                'previewUrl' => 'https://video.example/preview.jpg',
                'html' => '<iframe></iframe>',
                'providerName' => 'youtube',
            ],
        ],
        'keyPhoto' => [
            'originalUrl' => 'https://bokun.example/original.jpg',
        ],
        'durationText' => '4 hours',
        'durationType' => 'HOURS',
        'knowBeforeYouGoItems' => [],
    ];

    $priceList = [
        'activityId' => 768957,
        'isPriceConverted' => false,
        'defaultCurrency' => 'EUR',
        'pricesByDateRange' => [
            [
                'from' => '2026-04-09',
                'to' => '2026-04-11',
                'rates' => [],
            ],
        ],
    ];

    $availabilities = [
        [
            'id' => 2001,
            'date' => 1775692800000,
            'soldOut' => false,
            'availabilityCount' => 8,
        ],
    ];

    $service->shouldReceive('makeRequest')
        ->once()
        ->with('GET', '/activity.json/768957', ['lang' => 'en', 'currency' => 'EUR'])
        ->andReturn($activity);

    $service->shouldReceive('makeRequest')
        ->once()
        ->with('GET', '/activity.json/768957/price-list', ['currency' => 'EUR'])
        ->andReturn($priceList);

    $service->shouldReceive('makeRequest')
        ->once()
        ->with('GET', '/activity.json/768957/availabilities', Mockery::on(function ($query) {
            return ($query['currency'] ?? null) === 'EUR'
                && !empty($query['start'])
                && !empty($query['end']);
        }))
        ->andReturn($availabilities);

    $result = $service->getFullProductData('768957');

    expect($result['status'])->toBeTrue();
    expect($result['data']['title'])->toBe('Manawakie Wildlife sanctuary and Macaw Market ');
    expect($result['data']['priceList']['activityId'])->toBe(768957);
    expect($result['data']['availabilities'][0]['id'])->toBe(2001);
    expect($result['data']['bookingQuestions'][0]['label'])->toBe('Cruise Ship');
    expect(array_key_exists('price_list', $result['data']))->toBeFalse();
    expect(array_key_exists('normalized', $result))->toBeFalse();
    expect($result['data']['vendor']['title'])->toBe('Roatan Henry tour ');
});
