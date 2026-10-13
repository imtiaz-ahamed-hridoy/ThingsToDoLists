<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\GlobalTix\GlobalTixProductService;

class CitySeeder extends Seeder
{
    protected function getCities(): array
    {
        try {
            $globalTixService = app(GlobalTixProductService::class);
            $response = $globalTixService->getCountries();

            if (isset($response['data']) && is_array($response['data']) && ! empty($response['data'])) {
                return $response;
            }

            throw new \RuntimeException('GlobalTix countries payload is empty or invalid.');
        } catch (\Throwable $exception) {
            Log::warning('CitySeeder: GlobalTix countries fetch failed, using fallback dataset.', [
                'error' => $exception->getMessage(),
            ]);

            return $this->fallbackCountries();
        }
    }

    protected function fallbackCountries(): array
    {
        return [
            'data' => [
                [
                    'name' => 'United Arab Emirates',
                    'code' => 'AE',
                    'currency' => ['code' => 'AED'],
                    'mobilePrefix' => '+971',
                    'cities' => [
                        ['name' => 'Abu Dhabi', 'isCapital' => true],
                        ['name' => 'Dubai', 'isCapital' => false],
                    ],
                ],
                [
                    'name' => 'Bangladesh',
                    'code' => 'BD',
                    'currency' => ['code' => 'BDT'],
                    'mobilePrefix' => '+880',
                    'cities' => [
                        ['name' => 'Dhaka', 'isCapital' => true],
                        ['name' => 'Chattogram', 'isCapital' => false],
                    ],
                ],
                [
                    'name' => 'Singapore',
                    'code' => 'SG',
                    'currency' => ['code' => 'SGD'],
                    'mobilePrefix' => '+65',
                    'cities' => [
                        ['name' => 'Singapore', 'isCapital' => true],
                    ],
                ],
                [
                    'name' => 'United Kingdom',
                    'code' => 'GB',
                    'currency' => ['code' => 'GBP'],
                    'mobilePrefix' => '+44',
                    'cities' => [
                        ['name' => 'London', 'isCapital' => true],
                        ['name' => 'Manchester', 'isCapital' => false],
                    ],
                ],
                [
                    'name' => 'United States',
                    'code' => 'US',
                    'currency' => ['code' => 'USD'],
                    'mobilePrefix' => '+1',
                    'cities' => [
                        ['name' => 'New York', 'isCapital' => false],
                        ['name' => 'Washington', 'isCapital' => true],
                    ],
                ],
            ],
        ];
    }

    public function run(): void
    {
        $data = $this->getCities();

        if (!isset($data['data']) || !is_array($data['data'])) {
            Log::warning('CitySeeder: invalid cities payload format. No cities were seeded.');
            return;
        }

        foreach ($data['data'] as $country) {
            $country_name = $country['name'] ?? null;
            $country_short_code = $country['code'] ?? null;
            $currency_code = $country['currency']['code'] ?? null;
            $mobile_prefix = $country['mobilePrefix'] ?? null;


            if (!$country_name || !isset($country['cities']) || !is_array($country['cities'])) {
                continue;
            }

            foreach ($country['cities'] as $city) {
                $city_name = $city['name'] ?? null;
                $city_is_capital = $city['isCapital'] ?? null;

                $slug = Str::slug($city_name);

                // Check if city with same slug already exists
                if (DB::table('cities')->where('slug', $slug)->exists()) {
                    continue;
                }

                if (!$city_name) {
                    continue;
                }

                // Avoid duplicates
                if (DB::table('cities')->where('country_name', $country_name)->where('city_name', $city_name)->exists()) {
                    continue;
                }

                DB::table('cities')->insert([
                    'city_name' => $city_name,
                    'city_short_code' => null,
                    'city_is_capital' => $city_is_capital,
                    'slug' => $slug,
                    'country_name' => $country_name,
                    'country_short_code' => $country_short_code,
                    'country_slug' => Str::slug($country_name),
                    'currency_code' => $currency_code,
                    'mobile_prefix' => $mobile_prefix,
                    'title' => null,
                    'short_description' => null,
                    'thumbnail_image' => null,
                    'gallery_image' => null,
                    'item_status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
