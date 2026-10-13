<?php

namespace Tests\Feature;

use App\Models\City;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationListApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_city_list_returns_cities_from_database(): void
    {
        City::create([
            'city_name' => 'Dubai',
            'city_short_code' => 'DXB',
            'city_is_capital' => false,
            'slug' => 'dubai',
            'country_name' => 'United Arab Emirates',
            'country_short_code' => 'AE',
            'country_slug' => 'united-arab-emirates',
            'currency_code' => 'AED',
            'mobile_prefix' => '+971',
            'title' => 'Dubai Experiences',
            'short_description' => 'Book Dubai experiences.',
            'thumbnail_image' => 'uploads/cities/thumbnail/dubai.jpg',
            'gallery_image' => json_encode(['uploads/cities/gallery/dubai-1.jpg']),
            'item_status' => '1',
        ]);

        City::create([
            'city_name' => 'Abu Dhabi',
            'slug' => 'abu-dhabi',
            'country_name' => 'United Arab Emirates',
            'item_status' => '0',
        ]);

        $response = $this->getJson('/api/v1/city/list');

        $response->assertOk()
            ->assertJsonPath('message', 'City list fetched successfully')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.city_name', 'Dubai')
            ->assertJsonPath('data.1.city_short_code', 'DXB')
            ->assertJsonPath('data.1.city_is_capital', false)
            ->assertJsonPath('data.0.country_name', 'United Arab Emirates')
            ->assertJsonPath('data.1.gallery_image.0', 'uploads/cities/gallery/dubai-1.jpg');
    }

    public function test_country_list_returns_distinct_countries_from_database(): void
    {
        City::create([
            'city_name' => 'Dubai',
            'slug' => 'dubai',
            'country_name' => 'United Arab Emirates',
            'country_short_code' => 'AE',
            'country_slug' => 'united-arab-emirates',
            'currency_code' => 'AED',
            'mobile_prefix' => '+971',
            'item_status' => '1',
        ]);

        City::create([
            'city_name' => 'Abu Dhabi',
            'slug' => 'abu-dhabi',
            'country_name' => 'United Arab Emirates',
            'country_short_code' => 'AE',
            'country_slug' => 'united-arab-emirates',
            'currency_code' => 'AED',
            'mobile_prefix' => '+971',
            'item_status' => '1',
        ]);

        City::create([
            'city_name' => 'Dhaka',
            'slug' => 'dhaka',
            'country_name' => 'Bangladesh',
            'country_short_code' => 'BD',
            'country_slug' => 'bangladesh',
            'currency_code' => 'BDT',
            'mobile_prefix' => '+880',
            'item_status' => '1',
        ]);

        City::create([
            'city_name' => 'No Country City',
            'slug' => 'no-country-city',
            'country_name' => null,
            'country_short_code' => 'IC',
            'item_status' => '0',
        ]);

        $response = $this->getJson('/api/v1/country/list');

        $response->assertOk()
            ->assertJsonPath('message', 'Country list fetched successfully')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.country_name', 'Bangladesh')
            ->assertJsonPath('data.0.country_short_code', 'BD')
            ->assertJsonPath('data.1.country_name', 'United Arab Emirates')
            ->assertJsonPath('data.1.country_short_code', 'AE');
    }

    public function test_city_list_returns_not_found_when_no_cities_exist(): void
    {
        $response = $this->getJson('/api/v1/city/list');

        $response->assertNotFound()
            ->assertJsonPath('message', 'No city data found')
            ->assertJsonPath('status', 404);
    }

    public function test_country_list_returns_not_found_when_no_countries_exist(): void
    {
        City::create([
            'city_name' => 'No Country City',
            'slug' => 'no-country-city',
            'country_name' => null,
            'item_status' => '1',
        ]);

        $response = $this->getJson('/api/v1/country/list');

        $response->assertNotFound()
            ->assertJsonPath('message', 'No country data found')
            ->assertJsonPath('status', 404);
    }
}
