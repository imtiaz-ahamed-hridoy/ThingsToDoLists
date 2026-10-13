<?php

namespace Tests\Feature;

use App\Services\Bokun\BokunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BokunProductDetailsResponseTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        \Mockery::close();

        parent::tearDown();
    }

    public function test_product_details_endpoint_returns_bokun_native_data_shape(): void
    {
        $mock = \Mockery::mock(BokunService::class);

        $mock->shouldReceive('getFullProductData')
            ->once()
            ->with('768957', 'en', 'USD')
            ->andReturn([
                'status' => true,
                'data' => [
                    'activity' => [
                        'id' => 768957,
                        'title' => 'Manawakie Wildlife sanctuary and Macaw Market ',
                    ],
                    'priceList' => [
                        'activityId' => 768957,
                        'pricesByDateRange' => [],
                    ],
                    'availabilities' => [
                        [
                            'id' => 2001,
                            'soldOut' => false,
                        ],
                    ],
                    'booking_questions' => [
                        [
                            'label' => 'Cruise Ship',
                        ],
                    ],
                    'vendor' => [
                        'name' => 'Roatan Henry tour ',
                        'currency' => 'USD',
                    ],
                ],
                'normalized' => [
                    'title' => 'This should stay internal',
                ],
            ]);

        $this->app->instance(BokunService::class, $mock);

        $response = $this->postJson('/api/v1/product-details', [
            'product_api_id' => '768957',
            'platform' => 'bokun',
            'language' => 'en',
            'currency' => 'USD',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('provider', 'bokun')
            ->assertJsonPath('product_api_id', '768957')
            ->assertJsonPath('data.activity.id', 768957)
            ->assertJsonPath('data.priceList.activityId', 768957)
            ->assertJsonPath('data.availabilities.0.id', 2001)
            ->assertJsonPath('data.booking_questions.0.label', 'Cruise Ship')
            ->assertJsonPath('data.vendor.name', 'Roatan Henry tour ')
            ->assertJsonMissingPath('data.normalized')
            ->assertJsonMissingPath('data.price_list');
    }
}
