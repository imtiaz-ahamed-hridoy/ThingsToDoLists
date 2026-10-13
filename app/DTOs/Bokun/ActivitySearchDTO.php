<?php

namespace App\DTOs\Bokun;

class ActivitySearchDTO
{
    public function __construct(
        public readonly ?string $apiPlatform,
        public readonly ?string $productApiId,
        public readonly int $page,
        public readonly int $size,
        public readonly ?string $textSearch,
        public readonly string $textOperator,
        public readonly bool $textWildcard,
        public readonly ?string $city,
        public readonly ?string $distance,
        public readonly ?array $geoPolygon,
        public readonly ?array $geoBox,
        public readonly ?string $startDateFilter,
        public readonly ?string $endDateFilter,
        public readonly string $language,
        public readonly string $currency,
        public readonly ?string $countryCode,
        public readonly ?string $categoryIds,
        public readonly ?string $sortField,
        public readonly ?string $sortOrder,
        public readonly ?bool $excludeBoxes,
        public readonly ?bool $comboActivity,
        public readonly ?int $vendorId,
        public readonly ?int $participants,
        public readonly ?array $participantsByAge,
        public readonly ?string $publishedSince,
        public readonly ?string $externalId,
        public readonly ?array $facets,
    ) {
    }

    public static function fromRequest(array $data): self
    {
        return new self(
            apiPlatform: $data['api_platform'] ?? 'bokun',
            productApiId: $data['product_api_id'] ?? null,
            page: (int) ($data['page'] ?? 1),
            size: (int) ($data['size'] ?? 10),
            textSearch: $data['textSearch'] ?? null,
            textOperator: $data['textOperator'] ?? 'AND',
            textWildcard: (bool) ($data['textWildcard'] ?? true),
            city: $data['city'] ?? null,
            distance: $data['distance'] ?? null,
            geoPolygon: $data['geoPolygon'] ?? null,
            geoBox: $data['geoBox'] ?? null,
            startDateFilter: $data['startDateFilter'] ?? null,
            endDateFilter: $data['endDateFilter'] ?? null,
            language: strtoupper($data['language'] ?? 'EN'),
            currency: strtoupper($data['currency'] ?? 'EUR'),
            countryCode: $data['countryCode'] ?? null,
            categoryIds: $data['categoryIds'] ?? null,
            sortField: $data['sortField'] ?? null,
            sortOrder: $data['sortOrder'] ?? null,
            excludeBoxes: isset($data['excludeBoxes']) ? (bool) $data['excludeBoxes'] : null,
            comboActivity: isset($data['comboActivity']) ? (bool) $data['comboActivity'] : null,
            vendorId: isset($data['vendorId']) ? (int) $data['vendorId'] : null,
            participants: isset($data['participants']) ? (int) $data['participants'] : null,
            participantsByAge: $data['participantsByAge'] ?? null,
            publishedSince: $data['publishedSince'] ?? null,
            externalId: $data['externalId'] ?? null,
            facets: $data['facets'] ?? null,
        );
    }
}
