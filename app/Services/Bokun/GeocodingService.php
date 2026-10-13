<?php

namespace App\Services\Bokun;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    /**
     * Convert a city name to coordinates using OpenStreetMap Nominatim.
     * As per requirements: Use free provider, NO CACHE.
     *
     * @param string $city
     * @return array|null Returns ['lat' => float, 'lng' => float] or null if not found.
     */
    public function getCoordinatesForCity(string $city): ?array
    {
        if (empty(trim($city))) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Laravel-Activity-Search-App/1.0',
            ])->timeout(5)->get('https://nominatim.openstreetmap.org/search', [
                'q' => $city,
                'format' => 'json',
                'limit' => 1,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (!empty($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
                    return [
                        'lat' => (float) $data[0]['lat'],
                        'lng' => (float) $data[0]['lon'],
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Geocoding failed for city: {$city}", [
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }
}
