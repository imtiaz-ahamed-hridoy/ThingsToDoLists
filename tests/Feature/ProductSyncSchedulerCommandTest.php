<?php

use App\Models\Product;
use App\Services\Bokun\BokunProductSyncService;

test('products sync command includes provider only products and clamps chunk size', function () {
    Product::create([
        'provider' => 'bokun',
        'platform' => null,
        'product_api_id' => 'provider-only-123',
        'slug' => 'provider-only-product',
        'title' => 'Provider Only Product',
        'status' => 'active',
    ]);

    $syncService = Mockery::mock(BokunProductSyncService::class);
    $syncService->shouldReceive('importOrUpdateByApiId')
        ->once()
        ->with('bokun', 'provider-only-123', true)
        ->andReturn([
            'success' => true,
            'status' => 'updated',
            'product_api_id' => 'provider-only-123',
        ]);

    app()->instance(BokunProductSyncService::class, $syncService);

    $this->artisan('products:sync-all --platform=bokun --chunk=0')
        ->expectsOutput('Syncing 1 products...')
        ->expectsOutput('Updated: 1')
        ->assertExitCode(0);
});

test('products sync command rejects unknown platforms', function () {
    $this->artisan('products:sync-all --platform=bad')
        ->expectsOutput('Invalid platform [bad]. Use bokun or globaltix.')
        ->assertExitCode(2);
});
