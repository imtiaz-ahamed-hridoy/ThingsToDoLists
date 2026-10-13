<?php

namespace App\DTOs;

/**
 * Normalized location data from any provider.
 *
 * Bokun:     googlePlace.city, googlePlace.countryCode, geoLocationCenter
 * GlobalTix: activity.city, activity.country, latitude/longitude
 * Supplier:  user-entered address fields
 */
final class LocationDTO
{
    public function __construct(
        public readonly ?string $city,
        public readonly ?string $country,
        public readonly ?string $countryCode,
        public readonly ?string $address,
        public readonly ?string $postalCode,
        public readonly ?string $cityId,
        public readonly ?float  $latitude,
        public readonly ?float  $longitude,
        public readonly array   $metadata = [],
    ) {}

    public static function fromBokunData(array $data): self
    {
        $location = $data['location'] ?? [];
        $google = $data['googlePlace'] ?? [];

        $city = $location['city'] ?? $google['city'] ?? $google['name'] ?? null;
        $country = $location['country'] ?? $google['country'] ?? null;
        $countryCode = $location['countryCode'] ?? $location['country_code'] ?? $google['countryCode'] ?? null;

        $lat = data_get($location, 'coordinates.lat',
            data_get($google, 'geoLocationCenter.lat', $location['latitude'] ?? null));
        $lng = data_get($location, 'coordinates.lng',
            data_get($google, 'geoLocationCenter.lng', $location['longitude'] ?? null));

        return new self(
            city:        $city,
            country:     $country,
            countryCode: $countryCode,
            address:     $location['address'] ?? $location['addressLine'] ?? null,
            postalCode:  $location['postalCode'] ?? $location['postal_code'] ?? null,
            cityId:      isset($location['cityId']) ? (string) $location['cityId'] : null,
            latitude:    is_numeric($lat) ? (float) $lat : null,
            longitude:   is_numeric($lng) ? (float) $lng : null,
        );
    }

    public static function fromGlobalTixData(array $data): self
    {
        return new self(
            city:        $data['city'] ?? $data['location'] ?? null,
            country:     $data['country'] ?? null,
            countryCode: $data['countryCode'] ?? null,
            address:     $data['addressLine'] ?? $data['location'] ?? null,
            postalCode:  $data['postalCode'] ?? null,
            cityId:      isset($data['cityId']) ? (string) $data['cityId'] : null,
            latitude:    is_numeric($data['latitude'] ?? null) ? (float) $data['latitude'] : null,
            longitude:   is_numeric($data['longitude'] ?? null) ? (float) $data['longitude'] : null,
        );
    }

    public static function fromSupplierData(array $data): self
    {
        return new self(
            city:        $data['city'] ?? null,
            country:     $data['country'] ?? null,
            countryCode: $data['country_code'] ?? null,
            address:     $data['address'] ?? null,
            postalCode:  $data['postal_code'] ?? null,
            cityId:      null,
            latitude:    is_numeric($data['latitude'] ?? null) ? (float) $data['latitude'] : null,
            longitude:   is_numeric($data['longitude'] ?? null) ? (float) $data['longitude'] : null,
        );
    }
}
