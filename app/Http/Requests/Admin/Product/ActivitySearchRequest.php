<?php

namespace App\Http\Requests\Admin\Product;

use Illuminate\Foundation\Http\FormRequest;

class ActivitySearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $merge = [];

        if ($this->filled('textSearch') && !$this->filled('city')) {
            $merge['city'] = $this->textSearch;
        }

        if ($this->filled('participantsByAge_raw')) {
            $ages = array_filter(array_map('trim', explode(',', $this->participantsByAge_raw)));
            $merge['participantsByAge'] = array_map('intval', $ages);
        }

        if ($this->filled('geoPolygon_raw')) {
            $polygon = json_decode($this->geoPolygon_raw, true);
            if (is_array($polygon)) {
                $merge['geoPolygon'] = $polygon;
            }
        }

        if ($this->has('geoBox') && is_array($this->geoBox)) {
            $hasBoxValue = false;
            foreach ($this->geoBox as $corner) {
                if (isset($corner['lat']) && $corner['lat'] !== '' || isset($corner['lng']) && $corner['lng'] !== '') {
                    $hasBoxValue = true;
                    break;
                }
            }
            if (!$hasBoxValue) {
                $this->request->remove('geoBox');
            }
        }

        if ($this->has('facets') && is_array($this->facets)) {
            $validFacets = [];
            foreach ($this->facets as $facet) {
                if ((isset($facet['name']) && $facet['name'] !== '') || (isset($facet['values_raw']) && $facet['values_raw'] !== '')) {
                    if (!empty($facet['values_raw'])) {
                        $facet['values'] = array_filter(array_map('trim', explode(',', $facet['values_raw'])));
                    }
                    $validFacets[] = $facet;
                }
            }
            
            if (empty($validFacets)) {
                $this->request->remove('facets');
            } else {
                $merge['facets'] = $validFacets;
            }
        }

        if (!empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'api_platform'     => 'nullable|string|in:bokun,globaltix',
            'product_api_id'   => 'nullable|string|max:255',
            'page'             => 'nullable|integer|min:1|max:100',
            'size'             => 'nullable|integer|min:1|max:100',
            
            // Text Search
            'textSearch'       => 'nullable|string|max:255',
            'textOperator'     => 'nullable|string|in:AND,OR',
            'textWildcard'     => 'nullable|boolean',
            
            // City / Geo
            'city'             => 'nullable|string|max:100',
            'distance'         => 'nullable|string|max:20', // e.g. 30km
            
            // Geo Polygon
            'geoPolygon'       => 'nullable|array',
            'geoPolygon.*.lat' => 'required_with:geoPolygon|numeric|between:-90,90',
            'geoPolygon.*.lng' => 'required_with:geoPolygon|numeric|between:-180,180',

            // Geo Bounding Box
            'geoBox'           => 'nullable|array',
            'geoBox.bottomRight.lat' => 'required_with:geoBox|numeric|between:-90,90',
            'geoBox.bottomRight.lng' => 'required_with:geoBox|numeric|between:-180,180',
            'geoBox.topLeft.lat'     => 'required_with:geoBox|numeric|between:-90,90',
            'geoBox.topLeft.lng'     => 'required_with:geoBox|numeric|between:-180,180',

            // Dates
            'startDateFilter'  => 'nullable|date',
            'endDateFilter'    => 'nullable|date|after_or_equal:startDateFilter',
            
            // Configuration
            'language'         => 'nullable|string|max:5',
            'currency'         => 'nullable|string|max:5',
            'countryCode'      => 'nullable|string|max:10',
            'categoryIds'      => 'nullable|string|max:255',
            'sortField'        => 'nullable|string|max:50',
            'sortOrder'        => 'nullable|string|in:ASC,DESC',
            
            // Bokun specific filters
            'excludeBoxes'     => 'nullable|boolean',
            'comboActivity'    => 'nullable|boolean',
            'vendorId'         => 'nullable|integer|min:1',
            'participants'     => 'nullable|integer|min:1',
            'participantsByAge'=> 'nullable|array',
            'participantsByAge.*'=> 'integer|min:0',
            'publishedSince'   => 'nullable|date',
            'externalId'       => 'nullable|string|max:255',
            
            // Facet filters
            'facets'           => 'nullable|array',
            'facets.*.name'    => 'required_with:facets|string',
            'facets.*.values'  => 'required_with:facets|array',
            'facets.*.values.*'=> 'string',
            'facets.*.excluded'=> 'nullable|boolean',
        ];
    }
}
