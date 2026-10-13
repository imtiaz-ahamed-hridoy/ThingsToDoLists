<?php

use App\Models\Product;
use App\Services\Bokun\BokunProductSyncService;
use App\Services\Bokun\BokunService;
use App\Services\GlobalTix\GlobalTixProductService;
use App\Services\GlobalTix\GlobalTixProductSyncService;
use App\Services\GlobalTix\GlobaltixService;

function loadProviderRawFixture(string $fileName): array
{
    $payload = json_decode((string) file_get_contents(base_path('tests/Fixtures/provider_raw/' . $fileName)), true);

    expect($payload)->toBeArray();

    return $payload;
}

test('bokun product sync imports from raw payload and persists tiered pricing plus normalized child tables', function () {
    $provider = Mockery::mock(BokunService::class);
    $provider->shouldReceive('getFullProductData')
        ->never();
    $provider->shouldReceive('getFullProductDataRaw')
        ->once()
        ->with('259906')
        ->andReturn(loadProviderRawFixture('bokun_259906_raw.json'));

    app()->instance(BokunService::class, $provider);

    $result = app(BokunProductSyncService::class)->importOrUpdateByApiId('bokun', '259906');

    expect($result['success'])->toBeTrue()
        ->and($result['status'])->toBe('created');

    $product = Product::with([
        'prices',
        'options.pricingCategories.tieredPrices',
        'availabilities.categoryPrices',
        'cancellationPolicies',
        'locations',
    ])->firstOrFail();

    $adultRow = $product->prices->firstWhere('category_code', 'ADULT');
    $adultCategory = $product->options
        ->flatMap(fn ($option) => $option->pricingCategories)
        ->firstWhere('category_code', 'ADULT');

    expect($product->platform)->toBe('bokun')
        ->and($product->title)->toBe('Combo Tour: Metro System, Christmas Lights and Food Tour of Medellin')
        ->and($product->availabilities)->toHaveCount(1)
        ->and($product->images)->toHaveCount(1)
        ->and($product->bookingQuestions)->toHaveCount(0)
        ->and($product->passengerFields)->toHaveCount(3)
        ->and($product->hotelContactFields)->toHaveCount(0)
        ->and($product->cancellationPolicies)->toHaveCount(1)
        ->and($product->locations)->toHaveCount(1)
        ->and($product->raw_response)->not->toBeNull()
        ->and($product->raw_response->raw_data['data']['activity']['id'])->toBe(259906)
        ->and($product->original_price)->toBe('299.00');

    expect($product->prices->count())->toBeGreaterThanOrEqual(1);
    expect($adultRow)->not->toBeNull();
    expect($adultCategory)->not->toBeNull();
    expect($adultRow->selling_price)->toBe('299.00')
        ->and($adultRow->currency)->toBe('USD')
        ->and($adultCategory->selling_price)->toBe('299.00')
        ->and($adultCategory->tieredPrices)->toHaveCount(4)
        ->and($adultCategory->tieredPrices[0]->amount)->toBe('299.00')
        ->and($product->availabilities->first()->categoryPrices)->toHaveCount(1)
        ->and($product->availabilities->first()->categoryPrices->first()->category_code)->toBe('ADULT');
});

test('globaltix product sync imports from raw payload and persists category price summaries including family tickets', function () {
    $provider = Mockery::mock(GlobaltixService::class);
    $provider->shouldReceive('getFullProductData')
        ->never();
    $provider->shouldReceive('getFullProductDataRaw')
        ->once()
        ->with('38997')
        ->andReturn(loadProviderRawFixture('globaltix_38997_raw.json'));

    $productService = Mockery::mock(GlobalTixProductService::class);
    $productService->shouldReceive('getImage')
        ->andReturnUsing(fn(string $path) => "https://img.globaltix.test/{$path}");

    app()->instance(GlobaltixService::class, $provider);
    app()->instance(GlobalTixProductService::class, $productService);

    $result = app(GlobalTixProductSyncService::class)->importOrUpdateByApiId('globaltix', '38997');

    expect($result['success'])->toBeTrue()
        ->and($result['status'])->toBe('created');

    $product = Product::with([
        'prices',
        'options.pricingCategories',
        'availabilities.categoryPrices',
        'locations',
        'cancellationPolicies',
    ])->firstOrFail();

    $adultRow = $product->prices->firstWhere('ticket_type_id', '169361');
    $childRow = $product->prices->firstWhere('ticket_type_id', '169362');
    $familyRow = $product->prices->firstWhere('ticket_type_id', '169363');
    $categories = $product->options->flatMap(fn ($option) => $option->pricingCategories);
    $adultCategory = $categories->firstWhere('external_id', '169361');
    $childCategory = $categories->firstWhere('external_id', '169362');
    $familyCategory = $categories->firstWhere('external_id', '169363');

    expect($product->platform)->toBe('globaltix')
        ->and($product->title)->toBe('1 Day Transfer to Fitzroy Island - Full Day')
        ->and($product->availabilities)->toHaveCount(3)
        ->and($product->images)->toHaveCount(1)
        ->and($product->bookingQuestions)->toHaveCount(1)
        ->and($product->locations)->toHaveCount(1)
        ->and($product->cancellationPolicies)->toHaveCount(1)
        ->and($product->raw_response)->not->toBeNull()
        ->and($product->raw_response->raw_data['data']['activity']['data']['id'])->toBe(38997)
        ->and($product->original_price)->toBe('53.00')
        ->and($product->from_price)->toBe('49.20');

    expect($product->prices->count())->toBeGreaterThanOrEqual(3);
    expect($adultRow)->not->toBeNull();
    expect($childRow)->not->toBeNull();
    expect($familyRow)->not->toBeNull();
    expect($adultCategory)->not->toBeNull();
    expect($childCategory)->not->toBeNull();
    expect($familyCategory)->not->toBeNull();

    expect($adultRow->selling_price)->toBe('88.54')
        ->and($adultRow->nett_price)->toBe('52.09')
        ->and($adultRow->recommended_price)->toBe('88.54')
        ->and($childRow->selling_price)->toBe('50.46')
        ->and($familyRow->selling_price)->toBe('227.53')
        ->and($adultCategory->selling_price)->toBe('88.54')
        ->and($childCategory->selling_price)->toBe('50.46')
        ->and($familyCategory->selling_price)->toBe('227.53')
        ->and($product->availabilities->sum(fn ($slot) => $slot->categoryPrices->count()))->toBe(3);
});
