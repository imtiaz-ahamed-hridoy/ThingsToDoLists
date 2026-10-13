<?php

namespace Tests\Feature;

use App\Models\AvailabilityCategoryPrice;
use App\Models\AvailabilityCheck;
use App\Models\OptionPricingCategory;
use App\Models\Product;
use App\Models\ProductOption;
use App\Services\Bokun\BokunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityCheckCacheFirstTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        \Mockery::close();

        parent::tearDown();
    }

    public function test_availability_check_returns_local_rows_without_calling_provider(): void
    {
        [$product, $option, $category] = $this->createBokunProductGraph('bokun-local-1');

        $slot = AvailabilityCheck::create([
            'product_id' => $product->id,
            'product_option_id' => $option->id,
            'idempotency_key' => md5('local-slot'),
            'provider' => 'bokun',
            'external_id' => 'local-slot',
            'available_date' => '2026-05-01',
            'start_time' => '09:00:00',
            'start_at' => '2026-05-01 09:00:00',
            'capacity_total' => 10,
            'capacity_booked' => 2,
            'capacity_reserved' => 0,
            'display_price' => 95,
            'currency' => 'USD',
            'sold_out' => false,
            'is_active' => true,
            'is_closed' => false,
        ]);

        AvailabilityCategoryPrice::create([
            'availability_id' => $slot->id,
            'pricing_category_id' => $category->id,
            'category_external_id' => 'adult',
            'category_code' => 'ADULT',
            'category_label' => 'Adult',
            'selling_price' => 95,
            'currency' => 'USD',
            'available_qty' => 8,
        ]);

        $provider = \Mockery::mock(BokunService::class);
        $provider->shouldReceive('checkAvailability')->never();
        $this->app->instance(BokunService::class, $provider);

        $response = $this->postJson('/api/v1/availability/check', [
            'product_id' => $product->id,
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-01',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Availability loaded from local database successfully.')
            ->assertJsonPath('data.provider', 'bokun')
            ->assertJsonPath('data.dates.0.date', '2026-05-01')
            ->assertJsonPath('data.dates.0.time_slots.0.categories.0.selling_price', 95);
    }

    public function test_availability_check_force_refresh_calls_provider_and_replaces_range_rows(): void
    {
        [$product, $option, $category] = $this->createBokunProductGraph('bokun-refresh-1');

        $localSlot = AvailabilityCheck::create([
            'product_id' => $product->id,
            'product_option_id' => $option->id,
            'idempotency_key' => md5('stale-slot'),
            'provider' => 'bokun',
            'external_id' => 'stale-slot',
            'available_date' => '2026-05-01',
            'start_time' => '09:00:00',
            'start_at' => '2026-05-01 09:00:00',
            'capacity_total' => 10,
            'capacity_booked' => 1,
            'capacity_reserved' => 0,
            'display_price' => 100,
            'currency' => 'USD',
            'sold_out' => false,
            'is_active' => true,
            'is_closed' => false,
        ]);

        AvailabilityCategoryPrice::create([
            'availability_id' => $localSlot->id,
            'pricing_category_id' => $category->id,
            'category_external_id' => 'adult',
            'category_code' => 'ADULT',
            'category_label' => 'Adult',
            'selling_price' => 100,
            'currency' => 'USD',
            'available_qty' => 9,
        ]);

        $provider = \Mockery::mock(BokunService::class);
        $provider->shouldReceive('checkAvailability')
            ->once()
            ->with('bokun-refresh-1', '2026-05-01', '2026-05-02', 'USD')
            ->andReturn([
                'availabilities' => [[
                    'availability_id' => 'provider-slot-2',
                    'date' => '2026-05-02',
                    'start_time' => '10:00:00',
                    'availability_count' => 5,
                    'sold_out' => false,
                    'unlimited_availability' => false,
                    'prices_by_rate' => [[
                        'rateId' => 'default',
                        'pricePerCategoryUnit' => [[
                            'pricingCategoryId' => 'adult',
                            'ticketCategory' => 'ADULT',
                            'title' => 'Adult',
                            'amount' => [
                                'amount' => 90,
                                'currency' => 'USD',
                            ],
                        ]],
                    ]],
                ]],
            ]);
        $this->app->instance(BokunService::class, $provider);

        $response = $this->postJson('/api/v1/availability/check', [
            'product_id' => $product->id,
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-02',
            'force_refresh' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Availability fetched and synced successfully.')
            ->assertJsonPath('data.dates.0.date', '2026-05-02')
            ->assertJsonPath('data.dates.0.time_slots.0.categories.0.selling_price', 90);

        $this->assertDatabaseMissing('availability', [
            'id' => $localSlot->id,
        ]);
        $this->assertDatabaseHas('availability', [
            'product_id' => $product->id,
            'provider' => 'bokun',
            'external_id' => 'provider-slot-2',
        ]);
        $this->assertDatabaseHas('availability_category_prices', [
            'category_external_id' => 'adult',
            'selling_price' => 90,
        ]);
    }

    /**
     * @return array{0: Product, 1: ProductOption, 2: OptionPricingCategory}
     */
    private function createBokunProductGraph(string $productApiId): array
    {
        $product = Product::create([
            'product_api_id' => $productApiId,
            'platform' => 'bokun',
            'provider' => 'bokun',
            'title' => 'Bokun Availability Fixture',
            'slug' => 'bokun-availability-' . $productApiId,
            'status' => 'active',
            'published' => true,
            'default_currency' => 'USD',
            'from_price' => 100,
            'original_price' => 120,
            'time_zone' => 'UTC',
        ]);

        $option = ProductOption::create([
            'product_id' => $product->id,
            'provider' => 'bokun',
            'external_id' => 'default',
            'name' => 'Standard',
            'currency' => 'USD',
            'is_default' => true,
            'sort_order' => 0,
        ]);

        $category = OptionPricingCategory::create([
            'product_option_id' => $option->id,
            'provider' => 'bokun',
            'idempotency_key' => md5($productApiId . '-adult'),
            'external_id' => 'adult',
            'category_code' => 'ADULT',
            'category_label' => 'Adult',
            'selling_price' => 100,
            'currency' => 'USD',
            'min_purchase_qty' => 1,
            'max_purchase_qty' => 10,
            'sort_order' => 0,
        ]);

        return [$product, $option, $category];
    }
}
