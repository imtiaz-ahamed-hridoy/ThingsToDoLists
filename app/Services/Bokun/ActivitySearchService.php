<?php

namespace App\Services\Bokun;

use App\DTOs\Bokun\ActivitySearchDTO;
use App\Repositories\Bokun\ActivitySearchRepository;
use App\Services\Bokun\Builders\ActivityPayloadBuilder;
use Illuminate\Support\Facades\Log;

class ActivitySearchService
{
    private ActivitySearchRepository $repository;
    private GeocodingService $geocodingService;

    public function __construct(
        ActivitySearchRepository $repository,
        GeocodingService $geocodingService
    ) {
        $this->repository = $repository;
        $this->geocodingService = $geocodingService;
    }

    /**
     * Execute the full search using the parsed DTO.
     *
     * @param ActivitySearchDTO $dto
     * @return array
     */
    public function search(ActivitySearchDTO $dto): array
    {
        $builder = new ActivityPayloadBuilder();

        // 1. Basic Filters
        $builder->setPagination($dto->page, $dto->size)
                ->setDateRange($dto->startDateFilter, $dto->endDateFilter)
                ->setSort($dto->sortField, $dto->sortOrder)
                ->setExcludeBoxes($dto->excludeBoxes)
                ->setComboActivity($dto->comboActivity)
                ->setVendor($dto->vendorId)
                ->setParticipants($dto->participants, $dto->participantsByAge)
                ->setPublishedSince($dto->publishedSince)
                ->setExternalId($dto->externalId);

        // Text Search
        if ($dto->textSearch) {
            $builder->setText(
                text: $dto->textSearch,
                operator: $dto->textOperator,
                wildcard: $dto->textWildcard
            );
        }

        // Explicit Facets from request
        if (!empty($dto->facets)) {
            $builder->setFacets($dto->facets);
        }

        // Geo Explicit Filters from request
        if (!empty($dto->geoPolygon)) {
            $builder->setGeoPolygon($dto->geoPolygon);
        }
        if (!empty($dto->geoBox)) {
            $builder->setGeoBoundingBox(
                $dto->geoBox['topLeft']['lat'],
                $dto->geoBox['topLeft']['lng'],
                $dto->geoBox['bottomRight']['lat'],
                $dto->geoBox['bottomRight']['lng']
            );
        }

        // 2. City Search Priority Resolution
        if ($dto->city) {
            $this->applyCitySearchPriority($builder, $dto->city, $dto->language, $dto->currency, $dto->distance ?? '30km');
        }

        // 3. Build & Execute
        $payload = $builder->build();
        
        Log::info('Executing Bokun Activity Search', ['payload' => $payload]);

        $response = $this->repository->search($payload, $dto->language, $dto->currency);
        
        // Priority 4: Local filtering fallback
        // If the city was provided but both facet and geocoding failed, we try local filtering.
        // Or if Bokun returns no strict matching, we manually filter the result set.
        if ($dto->city && !empty($response['items']) && !$this->wasCitySearchAppliedToPayload($payload)) {
            $response['items'] = $this->applyLocalCityFilter($response['items'], $dto->city);
            $response['totalCount'] = count($response['items']);
        }

        return $response;
    }

    /**
     * Resolves the best way to search by City.
     */
    private function applyCitySearchPriority(
        ActivityPayloadBuilder $builder,
        string $city,
        string $language,
        string $currency,
        string $distance
    ): void {
        // Priority 1: Check available facets dynamically
        $availableFacets = $this->getAvailableFacets($language, $currency);
        
        $cityFacetName = null;
        foreach (['CITY', 'DESTINATION'] as $possibleFacet) {
            if (in_array($possibleFacet, $availableFacets, true)) {
                $cityFacetName = $possibleFacet;
                break;
            }
        }

        if ($cityFacetName) {
            $builder->setFacet($cityFacetName, [$city]);
            return;
        }

        // Priority 2: Geocoding via GeocodingService (No Cache as requested)
        $coords = $this->geocodingService->getCoordinatesForCity($city);
        if ($coords) {
            $builder->setGeoDistance($coords['lat'], $coords['lng'], $distance);
            return;
        }

        // Priority 3: Fallback to Text Search
        // We only overwrite text search if they didn't already supply a specific text search
        $builder->setText(
            text: $city,
            searchTitle: true,
            searchKeywords: true,
            searchFullText: true,
            wildcard: true
        );
    }

    /**
     * Fetch available facets from Bokun by sending an empty search request.
     */
    private function getAvailableFacets(string $language, string $currency): array
    {
        try {
            $response = $this->repository->search([
                'page' => 0,
                'pageSize' => 1
            ], $language, $currency);

            if (!empty($response['facets']) && is_array($response['facets'])) {
                return array_column($response['facets'], 'name');
            }
            
            // If they are under data.facets or raw_response.facets depending on how the repository returns it
            if (isset($response['raw_response']['facets']) && is_array($response['raw_response']['facets'])) {
                return array_column($response['raw_response']['facets'], 'name');
            }
            
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch available facets for city resolution', ['error' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * Determines if the payload successfully included a native city search (facet or geo).
     */
    private function wasCitySearchAppliedToPayload(array $payload): bool
    {
        if (isset($payload['facetFilters'])) {
            foreach ($payload['facetFilters'] as $facet) {
                if (in_array($facet['name'], ['CITY', 'DESTINATION'])) {
                    return true;
                }
            }
        }
        
        if (isset($payload['agendaLocationFilters']['geoDistanceFilter'])) {
            return true;
        }

        return false;
    }

    /**
     * Priority 4: Filter items locally by city name.
     */
    private function applyLocalCityFilter(array $items, string $city): array
    {
        $cityLower = strtolower(trim($city));

        return array_values(array_filter($items, function ($item) use ($cityLower) {
            $cityField = $item['city'] ?? $item['destination'] ?? $item['meetingPoint'] ?? $item['agendaLocation'] ?? $item['startPoint'] ?? '';
            
            if (empty($cityField) && isset($item['location']['city'])) {
                $cityField = $item['location']['city'];
            }
            
            if (empty($cityField) && isset($item['address']['city'])) {
                $cityField = $item['address']['city'];
            }

            return str_contains(strtolower((string)$cityField), $cityLower);
        }));
    }
}
