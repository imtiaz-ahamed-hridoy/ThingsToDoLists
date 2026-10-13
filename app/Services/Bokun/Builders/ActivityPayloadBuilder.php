<?php

namespace App\Services\Bokun\Builders;

class ActivityPayloadBuilder
{
    private array $payload = [];

    public function __construct()
    {
        // Set default text operator to AND as it's common
        $this->payload['textFilter'] = ['operator' => 'AND'];
    }

    public function setPagination(int $page, int $size): self
    {
        $this->payload['page'] = max(0, $page - 1); // Bokun API might be 0-indexed if $page starts at 1, but let's assume it passes the literal page. Wait, documentation says page: 0. So yes, 0-indexed.
        $this->payload['pageSize'] = $size;
        return $this;
    }

    public function setSort(?string $sortField, ?string $sortOrder): self
    {
        if ($sortField) {
            $this->payload['sortField'] = $sortField;
        }
        if ($sortOrder) {
            $this->payload['sortOrder'] = strtoupper($sortOrder);
        }
        return $this;
    }

    public function setDateRange(?string $startDate, ?string $endDate): self
    {
        if ($startDate) {
            $this->payload['startDate'] = $startDate;
        }
        if ($endDate) {
            $this->payload['endDate'] = $endDate;
        }
        return $this;
    }
    
    public function setDateFormats(?array $dateFormats): self
    {
        if (!empty($dateFormats)) {
            $this->payload['dateFormats'] = $dateFormats;
        }
        return $this;
    }

    public function setParticipants(?int $participants, ?array $participantsByAge = null): self
    {
        if ($participants !== null) {
            $this->payload['participants'] = $participants;
        }
        if (!empty($participantsByAge)) {
            $this->payload['participantsByAge'] = $participantsByAge;
        }
        return $this;
    }

    public function setVendor(?int $vendorId): self
    {
        if ($vendorId !== null) {
            $this->payload['vendorId'] = $vendorId;
        }
        return $this;
    }

    public function setExternalId(?string $externalId): self
    {
        if ($externalId) {
            $this->payload['externalId'] = $externalId;
        }
        return $this;
    }

    public function setPublishedSince(?string $publishedSince): self
    {
        if ($publishedSince) {
            $this->payload['publishedSince'] = $publishedSince;
        }
        return $this;
    }

    public function setExcludeBoxes(?bool $excludeBoxes): self
    {
        if ($excludeBoxes !== null) {
            $this->payload['excludeBoxes'] = $excludeBoxes;
        }
        return $this;
    }

    public function setComboActivity(?bool $comboActivity): self
    {
        if ($comboActivity !== null) {
            $this->payload['comboActivity'] = $comboActivity;
        }
        return $this;
    }

    // --- TEXT FILTER ---

    public function setText(
        ?string $text,
        bool $searchTitle = true,
        bool $searchKeywords = true,
        bool $searchFullText = true,
        bool $searchExternalId = false,
        bool $wildcard = true,
        string $operator = 'AND'
    ): self {
        if ($text) {
            $this->payload['textFilter'] = [
                'text' => $text,
                'searchTitle' => $searchTitle,
                'searchKeywords' => $searchKeywords,
                'searchFullText' => $searchFullText,
                'searchExternalId' => $searchExternalId,
                'wildcard' => $wildcard,
                'operator' => strtoupper($operator),
            ];
        }
        return $this;
    }

    // --- FACET FILTERS ---

    public function setFacet(string $name, array $values, bool $excluded = false): self
    {
        if (!isset($this->payload['facetFilters'])) {
            $this->payload['facetFilters'] = [];
        }

        $this->payload['facetFilters'][] = [
            'name' => $name,
            'values' => array_values($values),
            'excluded' => $excluded,
        ];

        return $this;
    }

    public function setFacets(array $facets): self
    {
        foreach ($facets as $facet) {
            if (isset($facet['name']) && isset($facet['values'])) {
                $this->setFacet($facet['name'], $facet['values'], $facet['excluded'] ?? false);
            }
        }
        return $this;
    }

    // --- AGENDA LOCATION FILTERS ---

    public function setGeoDistance(float $lat, float $lng, string $distance = '30km', string $locationType = 'agendaLocationFilters'): self
    {
        $this->payload[$locationType]['geoDistanceFilter'] = [
            'center' => [
                'lat' => $lat,
                'lng' => $lng,
            ],
            'distance' => $distance,
        ];
        return $this;
    }

    public function setGeoDistanceRange(float $lat, float $lng, string $fromDistance, string $toDistance, bool $includeLower = true, bool $includeUpper = true, string $locationType = 'agendaLocationFilters'): self
    {
        $this->payload[$locationType]['geoDistanceRangeFilter'] = [
            'center' => [
                'lat' => $lat,
                'lng' => $lng,
            ],
            'fromDistance' => $fromDistance,
            'toDistance' => $toDistance,
            'includeLower' => $includeLower,
            'includeUpper' => $includeUpper,
        ];
        return $this;
    }

    public function setGeoBoundingBox(float $tlLat, float $tlLng, float $brLat, float $brLng, string $locationType = 'agendaLocationFilters'): self
    {
        $this->payload[$locationType]['geoBoundingBoxFilter'] = [
            'topLeft' => [
                'lat' => $tlLat,
                'lng' => $tlLng,
            ],
            'bottomRight' => [
                'lat' => $brLat,
                'lng' => $brLng,
            ],
        ];
        return $this;
    }

    public function setGeoPolygon(array $points, string $locationType = 'agendaLocationFilters'): self
    {
        if (!empty($points)) {
            $this->payload[$locationType]['geoPolygonFilter'] = [
                'points' => $points,
            ];
        }
        return $this;
    }
    
    // START POINT FILTERS (same format as agendaLocationFilters, just different key)
    public function setStartPointGeoDistance(float $lat, float $lng, string $distance = '30km'): self
    {
        return $this->setGeoDistance($lat, $lng, $distance, 'startPointLocationFilters');
    }

    public function setStartPointGeoPolygon(array $points): self
    {
        return $this->setGeoPolygon($points, 'startPointLocationFilters');
    }
    
    public function setStartPointGeoBoundingBox(float $tlLat, float $tlLng, float $brLat, float $brLng): self
    {
        return $this->setGeoBoundingBox($tlLat, $tlLng, $brLat, $brLng, 'startPointLocationFilters');
    }

    /**
     * Build the final payload, automatically removing any null or empty arrays, 
     * but preserving explicit false/0 values if set.
     */
    public function build(): array
    {
        // If textFilter is just the default operator but no text, remove it.
        if (isset($this->payload['textFilter']) && !isset($this->payload['textFilter']['text'])) {
            unset($this->payload['textFilter']);
        }

        return $this->cleanArray($this->payload);
    }

    private function cleanArray(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $cleaned = $this->cleanArray($value);
                if (!empty($cleaned)) {
                    $result[$key] = $cleaned;
                }
            } elseif ($value !== null) {
                $result[$key] = $value;
            }
        }
        return $result;
    }
}
