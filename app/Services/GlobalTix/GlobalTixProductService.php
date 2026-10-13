<?php

namespace App\Services\GlobalTix;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GlobalTixProductService extends GlobalTixBaseService
{

    /**
     * Map old get() calls to the new request() method from base service.
     */
    protected function get(string $endpoint, array $query = [], bool $isRetry = false)
    {
        return $this->request('GET', $endpoint, $query, $isRetry);
    }

    /**
     * Fetch real categories directly from the GlobalTix API.
     * Endpoint: GET /api/merchantCategory/getAllCategories
     *
     * @throws ConnectionException
     */
    public function getCategories(): array
    {
        return $this->get('/api/merchantCategory/getAllCategories');
    }

    /**
     * @throws ConnectionException
     */
    public function getCountries()
    {
        return $this->get('/api/country/getAllCountries');
    }

    /**
     * @throws ConnectionException
     */
    public function getImage(?string $image): ?string
    {
        if (empty($image)) {
            return null;
        }

        // If it's already a full URL, return as is
        if (filter_var($image, FILTER_VALIDATE_URL)) {
            return $image;
        }

        $imageUrl = $this->authService->getImageUrl();
        return rtrim($imageUrl, '/') . '/' . ltrim($image, '/');
    }

    /**
     * @throws ConnectionException
     */
    public function getProductsByCountry($countryCode, $page = 1, array $filters = [], $language = 'en', $currency = 'EUR')
    {
        $query = [
            'countryCode' => $countryCode,
            'page' => $page,
            'lang' => $language,
        ];

        if (!empty($filters['cityIds'])) {
            $query['cityIds'] = $filters['cityIds'];
        }

        if (!empty($filters['categoryIds'])) {
            $query['categoryIds'] = $filters['categoryIds'];
        }

        if (!empty($filters['searchText'])) {
            $query['searchText'] = $filters['searchText'];
        }

        // Keep backward compatibility for existing callers that pass alternative names.
        if (empty($query['searchText']) && !empty($filters['keyword'])) {
            $query['searchText'] = $filters['keyword'];
        }

        if (empty($query['cityIds']) && !empty($filters['city'])) {
            $query['cityIds'] = $filters['city'];
        }

        if (!empty($filters['pageSize'])) {
            $query['pageSize'] = $filters['pageSize'];
        }

        return $this->get('/api/product/list', $query);
    }

    /**
     * @throws ConnectionException
     */
    public function getCities(?string $countryCode = null)
    {
        $query = [];
        if ($countryCode) {
            $query['countryCode'] = $countryCode;
        }

        // Assuming endpoint is /api/city/getAllCities based on getCountries pattern or similar
        // If not, we might need to check API docs or try /api/city/list
        return $this->get('/api/city/getAllCities', $query);
    }

    /**
     * @throws ConnectionException
     */
    public function getProductInfo($productId, $language = 'en', $currency = null)
    {
        $query = [
            'id' => $productId,
            'lang' => $language,
        ];
        if ($currency) {
            $query['currency'] = $currency;
        }
        
        $response = $this->get('/api/product/info', $query);
        
        if (isset($response['data'])) {
            // GlobalTix product info already returns prices in the requested currency
            // We just need to ensure the currency flag is set correctly on the object
            // so that our normalizer knows what currency these prices are in.
            $response['data']['currency'] = $currency ?? $response['data']['currency'] ?? 'EUR';
        }
        
        return $response;
    }

    /**
     * Fetch ticket options for a product from GlobalTix.
     * Endpoint: GET /api/product/options
     *
     * @param int|string $productId      The GlobalTix product ID.
     * @param bool       $isDynamicPrice Pass true for dynamic-priced products (per GlobalTix API docs).
     * @param string     $lang           Language code (ISO 639-1). Defaults to 'en'.
     *
     * @throws ConnectionException
     */
    public function getProductOptions($productId, bool $isDynamicPrice = false, string $lang = 'en', string $currency = 'EUR'): array
    {
        $query = [
            'id'             => $productId,
            'isDynamicPrice' => $isDynamicPrice ? 'true' : 'false',
            'lang'           => $lang,
        ];

        $response = $this->get('/api/product/options', $query);

        $targetCurrency = strtoupper($currency);

        if (isset($response['data']) && is_array($response['data'])) {
            $currencyService = app(\App\Services\CurrencyService::class);
            $this->convertOptionsPrices($response['data'], $targetCurrency, $currencyService);
        }

        return $response;
    }

    /**
     * Recursively convert price fields and update currency in options response.
     */
    private function convertOptionsPrices(array &$node, string $targetCurrency, \App\Services\CurrencyService $currencyService): void
    {
        $priceFields = [
            'originalPrice',
            'originalMerchantPrice',
            'nettPrice',
            'nettMerchantPrice',
            'minimumSellingPrice',
            'minimumMerchantSellingPrice',
            'recommendedSellingPrice',
            'recommendedMerchantSellingPrice',
            'price',
            'fromPrice',
            'startingPrice',
            'minPrice',
            'sellingPrice',
            'merchantSellingPrice',
        ];

        $itemCurrency = strtoupper((string) ($node['currency'] ?? 'EUR'));
        $hasPriceOrCurrency = isset($node['currency']);

        foreach ($priceFields as $field) {
            if (isset($node[$field]) && is_numeric($node[$field])) {
                $hasPriceOrCurrency = true;
                $converted = $currencyService->convert((float) $node[$field], $itemCurrency, $targetCurrency);
                if ($converted !== null) {
                    $node[$field] = $converted;
                }
            }
        }

        if ($hasPriceOrCurrency) {
            $node['currency'] = $targetCurrency;
        }

        foreach ($node as $key => &$child) {
            if (is_array($child)) {
                $this->convertOptionsPrices($child, $targetCurrency, $currencyService);
            }
        }
    }

    /**
     * Fetch changed products from GlobalTix.
     * Endpoint path is configurable and includes fallbacks for compatibility.
     *
     * @throws ConnectionException
     */

    public function getProductChanges(?string $dateFrom = null, ?string $dateTo = null, int $page = 1): array
    {
        $query = array_filter([
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'page' => $page,
        ], fn($v) => $v !== null && $v !== '');

        $endpoints = [
            config('services.globaltix.product_changes_endpoint', '/api/product/changes'),
            '/api/product/change',
            '/api/product/getChanges',
        ];

        return $this->getFirstAvailableEndpoint($endpoints, $query);
    }

    /**
     * Fetch newly published products from GlobalTix.
     *
     * API docs: GET /api/product/newProducts?startDate=YYYY-MM-DD&endDate=YYYY-MM-DD
     * Uses same auth as other GlobalTix booking APIs.
     *
     * @throws ConnectionException
     */
    public function getNewProducts(?string $startDate = null, ?string $endDate = null): array
    {
        $query = array_filter([
            'startDate' => $startDate,
            'endDate' => $endDate,
        ], fn($v) => $v !== null && $v !== '');

        return $this->get('/api/product/newProducts', $query);
    }

    /**
     * Extract product IDs from list-like responses with variable shapes.
     */
    public function extractProductIdsFromResponse(array $response): array
    {
        $candidates = [];

        $data = $response['data'] ?? null;
        if (is_array($data)) {
            if (isset($data['products']) && is_array($data['products'])) {
                $candidates = array_merge($candidates, $data['products']);
            } elseif (array_is_list($data)) {
                $candidates = array_merge($candidates, $data);
            } else {
                $candidates[] = $data;
            }
        }

        foreach (['products', 'items'] as $key) {
            if (!empty($response[$key]) && is_array($response[$key])) {
                $candidates = array_merge($candidates, $response[$key]);
            }
        }

        $ids = [];
        foreach ($candidates as $item) {
            if (is_scalar($item)) {
                $value = trim((string) $item);
                if ($value !== '') {
                    $ids[] = $value;
                }
                continue;
            }

            if (is_array($item)) {
                $value = $item['id'] ?? $item['productId'] ?? $item['productID'] ?? null;
                if ($value !== null && trim((string) $value) !== '') {
                    $ids[] = trim((string) $value);
                }
            }
        }

        return array_values(array_unique($ids));
    }

    protected function getFirstAvailableEndpoint(array $endpoints, array $query = []): array
    {
        $lastException = null;

        foreach ($endpoints as $endpoint) {
            try {
                return $this->get((string) $endpoint, $query);
            } catch (\Throwable $e) {
                $lastException = $e;
            }
        }

        if ($lastException) {
            throw $lastException;
        }

        throw new \RuntimeException('No endpoint available for GlobalTix request.');
    }

    /**
     * @throws ConnectionException
     */
    public function checkAvailability($ticketId, $from, $to)
    {
        return $this->get('/api/ticketType/checkEventAvailability', [
            'dateFrom' => $from,
            'dateTo' => $to,
            'ticketTypeID' => $ticketId,
        ]);
    }

    /**
     * @throws ConnectionException
     */
    public function checkOfficialAvailability(array $query)
    {
        return $this->get('/api/ticketType/checkEventAvailability', $query);
    }

    /**
     * @throws ConnectionException
     */
    public function getActivityAvailability(string $activityId, string $startDate, string $endDate, string $currency = 'EUR')
    {
        try {
            // Fallback strategy: aggregate from tickets since the direct endpoint can be restricted (403)
            $optionsResponse = $this->getProductOptions($activityId);
            $ticketOptions = $optionsResponse['data'] ?? $optionsResponse;

            if (empty($ticketOptions) || (is_array($ticketOptions) && empty(array_filter($ticketOptions)))) {
                $optionsResponse = $this->getProductOptions($activityId, true);
                $ticketOptions = $optionsResponse['data'] ?? $optionsResponse;
            }

            $allAvailabilities = [];

            if (is_array($ticketOptions)) {
                // Determine if we have a list of options or a single response with ticketType
                $optionsList = (array_values($ticketOptions) === $ticketOptions) ? $ticketOptions : [$ticketOptions];

                foreach ($optionsList as $option) {
                    $tickets = $option['ticketType'] ?? [];
                    if (is_array($tickets)) {
                        foreach ($tickets as $ticket) {
                            if (!empty($ticket['id'])) {
                                // Fetch availability for each ticket type
                                $availability = $this->getTicketAvailability($ticket['id'], $startDate, $endDate);
                                if ($availability['status'] && !empty($availability['data'])) {
                                    foreach ($availability['data'] as $avail) {
                                        // Add ticket context to the availability item
                                        $avail['ticket_id'] = $ticket['id'];
                                        $avail['ticket_name'] = $ticket['name'] ?? null;
                                        $avail['option_id'] = $option['id'] ?? null;
                                        $allAvailabilities[] = $avail;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            return [
                'status' => true,
                'message' => 'Activity availability fetched successfully (aggregated from tickets)',
                'data' => $allAvailabilities,
            ];
        } catch (\Exception $e) {
            Log::error('GlobalTix getActivityAvailability error', [
                'activityId' => $activityId,
                'message' => $e->getMessage()
            ]);
            return [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search for activities using the GlobalTix API.
     * Handles 8+ different response formats from the GlobalTix API.
     *
     * @param array $params Search parameters
     * @return array|null The search results with 'items', 'totalCount', 'page', 'pageSize'
     * @throws ConnectionException
     */
    public function searchActivities(array $params)
    {
        try {
            // 🔹 Normalized Parameter Extraction
            $countryCode = $params['countryCode'] ?? ($params['filters']['countryCode'] ?? null);
            $cityIds = $params['cityIds'] ?? ($params['filters']['cityIds'] ?? ($params['filters']['city'] ?? null));
            $categoryIds = $params['categoryIds'] ?? ($params['filters']['categoryIds'] ?? null);
            $textSearch = $params['textSearch'] ?? ($params['filters']['text'] ?? ($params['filters']['searchText'] ?? null));
            $page = $params['page'] ?? 1;
            $language = $params['lang'] ?? ($params['language'] ?? 'en');
            $currency = $params['currency'] ?? 'EUR';

            if ($countryCode) {
                $filters = [];
                if (!empty($cityIds)) {
                    $filters['cityIds'] = $cityIds;
                }
                if (!empty($categoryIds)) {
                    $filters['categoryIds'] = $categoryIds;
                }
                if (!empty($textSearch)) {
                    $filters['searchText'] = $textSearch;
                }
                if (!empty($params['pageSize'])) {
                    $filters['pageSize'] = $params['pageSize'];
                }

                $response = $this->getProductsByCountry($countryCode, $page, $filters, $language, $currency);

                // Extract raw product items from response using multiple strategies
                $products = $this->extractItemsFromResponse($response);

                if (!empty($products)) {
                    // Do NOT hardcode EUR! Use the native currency from the API response
                    foreach ($products as &$product) {
                        $product['currency'] = $product['currency'] ?? $product['currencyCode'] ?? 'EUR';
                    }
                    unset($product);

                    $transformedProducts = $this->transformProductsToActivities($products);

                    // Extract total count using multiple possible keys
                    $totalCount = $response['totalCount']
                        ?? $response['total']
                        ?? $response['data']['totalCount']
                        ?? $response['data']['total']
                        ?? $response['data']['totalElements']
                        ?? $response['pagination']['total']
                        ?? $response['pagination']['totalCount']
                        ?? count($products);

                    return [
                        'items' => $transformedProducts,
                        'totalCount' => (int) $totalCount,
                        'page' => $page,
                        'pageSize' => $params['pageSize'] ?? count($products),
                    ];
                }

                return [
                    'items' => [],
                    'totalCount' => 0,
                    'page' => $page,
                    'pageSize' => $params['pageSize'] ?? 20,
                ];
            } else {
                // Use the regular activity search endpoint (without country code)
                $response = $this->get('/api/activity/search', $params);

                if (isset($response['data']['items'])) {
                    return $response['data'];
                }

                if (isset($response['items'])) {
                    return $response;
                }
                return [
                    'items' => [],
                    'totalCount' => 0,
                    'page' => $params['page'] ?? 1,
                    'pageSize' => $params['pageSize'] ?? 20,
                ];
            }
        } catch (\Exception $e) {
            Log::error('GlobalTix search error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Extract product items from the API response, trying multiple possible formats.
     * This is the catch-all that handles ANY response structure.
     */
    protected function extractItemsFromResponse(array $response): array
    {
        // Try every possible key/location where products could be
        $candidates = [];

        // Check well-known nested paths first
        $pathsToCheck = [
            'data.products',
            'data.items',
            'data.list',
            'data.content',
            'data.data',
            'data.result',
            'data.records',
            'data.results',
            'products',
            'items',
            'list',
            'content',
            'result',
            'records',
            'results',
        ];

        foreach ($pathsToCheck as $path) {
            $value = data_get($response, $path);
            if (is_array($value) && !empty($value)) {
                $candidates[] = $value;
            }
        }

        // Check if data itself is an array of items
        if (isset($response['data']) && is_array($response['data']) && array_is_list($response['data'])) {
            $candidates[] = $response['data'];
        }

        // Check if the response itself is a flat array of items
        if (array_is_list($response) && !empty($response)) {
            $candidates[] = $response;
        }

        // Check if any top-level key contains an array with items that have an 'id'
        foreach ($response as $key => $value) {
            if (is_array($value) && array_is_list($value) && !empty($value) && is_array($value[0]) && (isset($value[0]['id']) || isset($value[0]['productId']) || isset($value[0]['name']))) {
                $candidates[] = $value;
            }
        }

        // Return the first candidate that has actual items
        foreach ($candidates as $candidate) {
            if (!empty($candidate)) {
                return $candidate;
            }
        }

        return [];
    }

    /**
     * Transform product data to match the activity format
     * Handles field name variations from different API response formats.
     *
     * @param array $products Array of products from the API
     * @return array Transformed products in activity format
     */
    protected function transformProductsToActivities(array $products)
    {
        $transformedProducts = [];

        foreach ($products as $product) {
            // Normalize field names - handle various API naming conventions
            $id = $product['id']
                ?? $product['productId']
                ?? $product['productID']
                ?? $product['ID']
                ?? $product['activityId']
                ?? null;

            // Skip items with no ID
            if ($id === null) {
                continue;
            }

            $name = $product['name']
                ?? $product['productName']
                ?? $product['title']
                ?? $product['activityName']
                ?? 'Title not found';

            $description = $product['description']
                ?? $product['productDescription']
                ?? $product['desc']
                ?? $product['shortDescription']
                ?? 'Summary not found';

            $fromPrice = $product['fromPrice'] ?? null;
            $originalPrice = $product['originalPrice'] ?? null;

            $image = $product['image']
                ?? $product['imageUrl']
                ?? $product['thumbnail']
                ?? $product['photo']
                ?? $product['mainImage']
                ?? $product['coverImage']
                ?? null;

            $vendorName = $product['merchant']['name']
                ?? $product['merchantName']
                ?? $product['vendorName']
                ?? $product['supplierName']
                ?? 'Vendor not found';

            $city = $product['city']
                ?? $product['cityName']
                ?? $product['destination']
                ?? null;

            $country = $product['country']
                ?? $product['countryName']
                ?? null;

            $countryCode = $product['countryCode']
                ?? $product['country_code']
                ?? null;

            $currency = $product['currency']
                ?? $product['currencyCode']
                ?? 'EUR';

            $rating = $product['rating']
                ?? $product['averageRating']
                ?? $product['starRating']
                ?? 0;

            $reviewCount = $product['reviewCount']
                ?? $product['reviews']
                ?? $product['totalReviews']
                ?? 0;

            $latitude = $product['latitude']
                ?? $product['lat']
                ?? $product['geoLocation']['latitude']
                ?? 0;

            $longitude = $product['longitude']
                ?? $product['lng']
                ?? $product['lon']
                ?? $product['geoLocation']['longitude']
                ?? 0;

            $address = $product['address']
                ?? $product['addressLine']
                ?? $product['fullAddress']
                ?? null;

            $category = $product['category']
                ?? $product['categoryName']
                ?? $product['productCategory']
                ?? null;

            // Build the transformed product
            $transformedProduct = [
                'id' => $id,
                'title' => $name,
                'slug' => $product['slug'] ?? $product['urlSlug'] ?? null,
                'summary' => $description,
                'from_price' => $fromPrice,
                'original_price' => $originalPrice,
                'fromPrice' => $fromPrice,
                'originalPrice' => $originalPrice,
                // keep price for legacy frontend compat but map it strictly to fromPrice
                'price' => $fromPrice,
                'vendor' => [
                    'title' => $vendorName,
                ],
                'keyPhoto' => [
                    'originalUrl' => $this->getImage($image),
                ],
                'photos' => $image
                    ? [['originalUrl' => $this->getImage($image)]]
                    : [],
                'rating' => $rating,
                'reviewCount' => $reviewCount,
                'googlePlace' => [
                    'country' => $country,
                    'countryCode' => $countryCode,
                    'city' => $city,
                    'cityCode' => $product['cityCode'] ?? null,
                    'geoLocationCenter' => [
                        'lat' => $latitude,
                        'lng' => $longitude,
                    ],
                ],
                'places' => [
                    [
                        'location' => [
                            'wholeAddress' => $address,
                            'city' => $city,
                            'countryCode' => $countryCode,
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                        ],
                    ],
                ],
                'currency' => $currency,
                'isInstantConfirmation' => $product['isInstantConfirmation'] ?? $product['instantConfirmation'] ?? false,
                'isCancellable' => $product['isCancellable'] ?? $product['cancellable'] ?? false,
                'category' => $category,
                'keywords' => $product['keywords'] ?? $product['tags'] ?? null,
            ];

            $transformedProducts[] = $transformedProduct;
        }

        return $transformedProducts;
    }

    /**
     * Import product data from temporary GlobalTix products to the main product items table
     *
     * @param array $ids Array of temporary product IDs to import
     * @return array Status of the import operation
     */
    // Removal of legacy importProductData as import is handled by TempProductController standardized flow.

    /**
     * ✅ Check availability for a specific ticket type and date range
     *
     * @param string $ticketTypeId
     * @param string $dateFrom
     * @param string $dateTo
     * @return array
     */
    public function getTicketAvailability($ticketTypeId, string $dateFrom, string $dateTo, $optionId = null, ?string $timeslot = null, ?string $currency = 'EUR'): array
    {
        $currency = $currency ?? 'EUR';
        try {
            // GlobalTix API rejects requests if dateFrom is in the past. Clamp it to today to allow future dates in the range to be checked.
            $today = \Carbon\Carbon::today()->format('Y-m-d');
            if ($dateFrom < $today) {
                $dateFrom = $today;
            }
            if ($dateTo < $today) {
                // If the entire range is in the past, return empty availability immediately
                return ['status' => true, 'message' => 'Requested dates are in the past', 'data' => []];
            }

            $payload = [
                'ticketTypeID' => (int) $ticketTypeId,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
            ];

            if ($optionId !== null) {
                $payload['optionID'] = (int) $optionId;
            }

            if ($timeslot !== null) {
                $payload['timeslot'] = $timeslot;
            }

            $response = $this->request('GET', '/api/ticketType/checkEventAvailability', $payload);

            // GlobalTix API may return data in different shapes:
            // 1. { "data": [...] } — standard wrapper
            // 2. [ ... ] — direct array (when response IS the data)
            // 3. { "status": true, "data": [...] } — status + data wrapper
            $availabilityData = null;

            if (isset($response['data']) && is_array($response['data'])) {
                $availabilityData = $response['data'];
            } elseif (array_is_list($response)) {
                // Response itself is the availability list
                $availabilityData = $response;
            } elseif (isset($response['status']) && $response['status'] === true) {
                // Status-only response with no data means empty availability
                $availabilityData = [];
            } elseif (isset($response['error']) || (isset($response['success']) && $response['success'] === false)) {
                // Provider returned expected supplier unavailable / unfulfillable response
                $availabilityData = [];
            }

            if ($availabilityData === null) {
                \Illuminate\Support\Facades\Log::debug('Empty or unexpected availability response shape from GlobalTix', [
                    'payload' => $payload,
                    'response_keys' => array_keys($response),
                ]);
                // Treat as empty availability rather than hard failure
                $availabilityData = [];
            }

            $currencyService = app(\App\Services\CurrencyService::class);
            $availabilityList = collect($availabilityData)->map(function ($item) use ($ticketTypeId, $currency, $currencyService) {
                // "time" is the primary date/time field (e.g. "2026-02-05T00:00:00")
                $timeValue = $item['time'] ?? null;
                $parsedDate = null;
                $formattedDate = null;
                $formattedTime = null;

                if ($timeValue) {
                    try {
                        $carbon = \Carbon\Carbon::parse($timeValue);
                        $parsedDate = $carbon->format('Y-m-d');
                        $formattedDate = $carbon->format('F j, Y');
                        $formattedTime = $carbon->format('g:i A');
                    } catch (\Exception $e) {
                        // Fallback if time parsing fails
                    }
                }

                // "available" is a count (e.g. 999999). Derive isAvailable from it.
                $availableCount = $item['available'] ?? 0;
                $isAvailable = $availableCount > 0;

                // Pricing: API returns nettPrice and recommendedSellingPrice
                // DO NOT HARDCODE EUR! Read the actual currency from the API response
                // so that we don't incorrectly convert SGD (or THB) as if it were EUR.
                $itemCurrency = $item['currency'] ?? 'EUR';
                $rawNettPrice = $item['nettPrice'] ?? null;
                $rawSellingPrice = $item['recommendedSellingPrice'] ?? null;
                
                $nettPrice = $rawNettPrice !== null ? $currencyService->convert($rawNettPrice, $itemCurrency, $currency) : null;
                $sellingPrice = $rawSellingPrice !== null ? $currencyService->convert($rawSellingPrice, $itemCurrency, $currency) : null;
                
                $priceAmount = ($sellingPrice && $sellingPrice > 0) ? $sellingPrice : $nettPrice;

                return [
                    'id' => $item['id'] ?? $item['seriesId'] ?? null,
                    'date' => $parsedDate,
                    'isAvailable' => $isAvailable,
                    'total' => $item['total'] ?? null,
                    'available' => $availableCount,
                    'sessionId' => $item['id'] ?? $item['seriesId'] ?? null,
                    'seriesId' => $item['seriesId'] ?? null,
                    'seriesName' => $item['seriesName'] ?? null,
                    'ticket_type_id' => $ticketTypeId,
                    'time' => $timeValue,
                    'formatted_date' => $formattedDate,
                    'formatted_time' => $formattedTime,
                    'nettPrice' => $nettPrice,
                    'recommendedSellingPrice' => $sellingPrice,
                    'isAdHoc' => $item['isAdHoc'] ?? null,
                    'isInactive' => $item['isInactive'] ?? null,
                    'enableEmp' => $item['enableEmp'] ?? null,
                    'unlimited' => $item['unlimited'] ?? null,
                    'used' => $item['used'] ?? null,
                    'price' => [
                        'amount' => $item['price'] ?? null,
                        'currency' => $item['currency'] ?? 'EUR'
                    ]
                ];
            })->values()->toArray();

            return [
                'status' => true,
                'message' => 'Availability fetched successfully',
                'data' => $availabilityList,
            ];
        } catch (\Exception $e) {
            Log::error('GlobalTix getTicketAvailability error', ['message' => $e->getMessage()]);
            return [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if a product has any valid (non-sold-out) availability in the next 1 year.
     * Returns true if at least one ticket type has at least one available date.
     *
     * @param string|int $productId
     * @return bool
     */
    public function hasAvailability($productId): bool
    {
        try {
            $optionsResponse = $this->getProductOptions($productId);
            $ticketOptions = $optionsResponse['data'] ?? [];

            if (empty($ticketOptions) || (is_array($ticketOptions) && empty(array_filter($ticketOptions)))) {
                $optionsResponse = $this->getProductOptions($productId, true);
                $ticketOptions = $optionsResponse['data'] ?? [];
            }

            if (!is_array($ticketOptions) || empty($ticketOptions)) {
                return false;
            }

            // Check full current year: start_date = Jan 1, end_date = Dec 31
            $dateFrom = now()->startOfYear()->toDateString();
            $dateTo   = now()->endOfYear()->toDateString();

            $optionsList = (array_values($ticketOptions) === $ticketOptions) ? $ticketOptions : [$ticketOptions];

            foreach ($optionsList as $option) {
                $tickets = $option['ticketType'] ?? [];
                if (is_array($tickets)) {
                    foreach ($tickets as $ticket) {
                        if (!empty($ticket['id'])) {
                            $availability = $this->getTicketAvailability($ticket['id'], $dateFrom, $dateTo);
                            if ($availability['status'] && !empty($availability['data'])) {
                                // Check if at least one date is actually available (not sold out)
                                foreach ($availability['data'] as $avail) {
                                    $isAvailable = $avail['isAvailable'] ?? false;
                                    $available = $avail['available'] ?? 0;
                                    if ($isAvailable && $available > 0) {
                                        return true; // Found at least one available slot
                                    }
                                }
                            }
                        }
                    }
                }
            }

            return false; // No available slots found in any ticket type
        } catch (\Exception $e) {
            Log::error('GlobalTix hasAvailability check failed', [
                'productId' => $productId,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    protected function formatTicketOptions(array $ticketOptions): array
    {
        $organized = [];
        $priceSyncService = app(\App\Services\GlobalTix\GlobalTixPriceSyncService::class);

        foreach ($ticketOptions as $opt) {
            $ticketTypes = $opt['ticketType'] ?? [];
            $resolvedPrice = $priceSyncService->resolveFromPrice($ticketTypes);
            $priceValue = $resolvedPrice['from_price']
                ?? $ticketTypes[0]['recommendedSellingPrice']
                ?? $ticketTypes[0]['originalPrice']
                ?? $ticketTypes[0]['nettPrice']
                ?? null;

            $organized[] = [
                'id' => $opt['id'],
                'title' => $opt['name'] ?? 'Untitled Ticket',
                'description' => Str::limit(strip_tags($opt['description'] ?? ''), 200),
                'price' => [
                    'amount' => $priceValue,
                    'currency' => $opt['currency'] ?? 'EUR',
                    'formatted' => ($opt['currency'] ?? 'EUR') . ' ' . $priceValue
                ],
                'inclusions' => $opt['inclusions'] ?? [],
                'cancellation' => [
                    'isCancellable' => $opt['isCancellable'] ?? false,
                    'policy' => $opt['cancellationNotes'][0] ?? null
                ],
                'duration' => isset($opt['definedDuration']) ? $opt['definedDuration'] . ' minutes' : null,
                'features' => [
                    'Instant confirmation',
                    'Mobile ticket available',
                    ($opt['definedDuration'] ?? 0) > 0 ? ($opt['definedDuration'] . ' minutes session') : null
                ],
                'howToUse' => $opt['howToUse'] ?? [],
                'ticketTypes' => collect($opt['ticketType'] ?? [])->map(fn($t) => [
                    'id' => $t['id'] ?? null,
                    'name' => $t['name'] ?? null,
                    'nameTranslated' => $t['nameTranslated'] ?? null,
                    'price' => $t['recommendedSellingPrice'] ?? $t['sellingPrice'] ?? $t['nettPrice'] ?? null,
                    'recommendedSellingPrice' => $t['recommendedSellingPrice'] ?? null,
                    'originalPrice' => $t['originalPrice'] ?? null,
                    'merchantPrice' => $t['nettMerchantPrice'] ?? null,
                ])->toArray()
            ];
        }

        return $organized;
    }
}
