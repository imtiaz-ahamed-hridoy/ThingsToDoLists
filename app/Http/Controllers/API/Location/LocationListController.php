<?php

namespace App\Http\Controllers\API\Location;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\CityRepositoryInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Throwable;

class LocationListController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CityRepositoryInterface $cityRepository
    ) {}

    public function cities(): JsonResponse
    {
        try {
            $cities = Cache::remember('location:cities:all', now()->addMinutes(20), function () {
                return $this->cityRepository->query()
                    ->select([
                        'id',
                        'city_name',
                        'city_short_code',
                        'city_is_capital',
                        'slug',
                        'country_name',
                        'country_short_code',
                        'country_slug',
                        'currency_code',
                        'mobile_prefix',
                        'title',
                        'short_description',
                        'thumbnail_image',
                        'gallery_image',
                    ])
                    ->orderBy('city_name')
                    ->get()
                    ->map(function ($city): array {
                        return [
                            'id' => $city->id,
                            'city_name' => $city->city_name,
                            'city_short_code' => $city->city_short_code,
                            'city_is_capital' => (bool) $city->city_is_capital,
                            'slug' => $city->slug,
                            'country_name' => $city->country_name,
                            'country_short_code' => $city->country_short_code,
                            'country_slug' => $city->country_slug,
                            'currency_code' => $city->currency_code,
                            'mobile_prefix' => $city->mobile_prefix,
                            'title' => $city->title,
                            'short_description' => $city->short_description,
                            'thumbnail_image' => $city->thumbnail_image,
                            'gallery_image' => $this->decodeGalleryImage($city->gallery_image),
                        ];
                    });
            });

            if ($cities->isEmpty()) {
                return $this->error('No city data found', 404);
            }

            return $this->success('City list fetched successfully', $cities, 200);
        } catch (Throwable $exception) {
            return $this->error('Failed to fetch city list', 500);
        }
    }

    public function countries(): JsonResponse
    {
        try {
            $countries = Cache::remember('location:countries:all', now()->addMinutes(20), function () {
                return $this->cityRepository->query()
                    ->whereNotNull('country_name')
                    ->where('country_name', '!=', '')
                    ->select([
                        'country_name',
                        'country_short_code',
                        'country_slug',
                        'currency_code',
                        'mobile_prefix',
                    ])
                    ->distinct()
                    ->orderBy('country_name')
                    ->get()
                    ->map(function ($country): array {
                        return [
                            'country_name' => $country->country_name,
                            'country_short_code' => $country->country_short_code,
                            'country_slug' => $country->country_slug,
                            'currency_code' => $country->currency_code,
                            'mobile_prefix' => $country->mobile_prefix,
                        ];
                    });
            });

            if ($countries->isEmpty()) {
                return $this->error('No country data found', 404);
            }

            return $this->success('Country list fetched successfully', $countries, 200);
        } catch (Throwable $exception) {
            return $this->error('Failed to fetch country list', 500);
        }
    }

    private function decodeGalleryImage(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
