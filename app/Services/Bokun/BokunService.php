<?php

namespace App\Services\Bokun;

use App\Services\Provider\ProviderInterface;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class BokunService implements ProviderInterface
{
    private mixed $accessKey;

    private mixed $secretKey;

    private mixed $baseUrl;

    private Client $client;

    public function __construct()
    {
        $this->accessKey = config('services.bokun.access_key');
        $this->secretKey = config('services.bokun.secret_key');
        $this->baseUrl = config('services.bokun.base_url', 'https://api.bokun.io');
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 120,
            'connect_timeout' => 30,
        ]);
    }

    /**
     * Generate the Bokun API signature
     *
     * @param  string  $date  ISO formatted date
     * @param  string  $method  HTTP method (GET, POST, etc.)
     * @param  string  $path  API endpoint path including query string
     * @return string Base64 encoded signature
     */
    private function generateSignature(string $date, string $method, string $path): string
    {
        $stringToSign = $date . $this->accessKey . $method . $path;
        $signature = hash_hmac('sha1', $stringToSign, $this->secretKey, true);

        return base64_encode($signature);
    }

    /**
     * Make a request to the Bokun API
     *
     * @param  string  $method  HTTP method (GET, POST, etc.)
     * @param  string  $endpoint  API endpoint without base URL
     * @param  array  $query  Query parameters
     * @param  array  $body  Request body
     * @return array Response data
     *
     * @throws GuzzleException
     */
    public function makeRequest(string $method, string $endpoint, array $query = [], array $body = []): array
    {
        try {
            // Build the full path including query parameters
            $path = $endpoint;
            if (! empty($query)) {
                $path .= '?' . http_build_query($query);
            }

            // Generate the current date in the format Bokun expects
            $date = Carbon::now()->format('Y-m-d H:i:s');

            // Generate the signature
            $signature = $this->generateSignature($date, $method, $path);

            // Set the headers
            $headers = [
                'X-Bokun-Date' => $date,
                'X-Bokun-AccessKey' => $this->accessKey,
                'X-Bokun-Signature' => $signature,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Accept-Language' => app()->bound('user.language') ? strtoupper((string) app('user.language')) : 'EN',
            ];

            // Make the request
            $options = [
                'headers' => $headers,
            ];

            if (! empty($body)) {
                $options['json'] = $body;
            }

            // Increase memory for large Bokun API responses (activities with full relations)
            $previousMemoryLimit = ini_set('memory_limit', '512M');
            
            $response = $this->client->request($method, $path, $options);
            
            $responseBody = (string) $response->getBody();
            $decoded = json_decode($responseBody, true);
            
            if (!is_array($decoded)) {
                // Free memory before returning
                $truncatedRaw = mb_substr(mb_convert_encoding($responseBody, 'UTF-8', 'UTF-8'), 0, 2000);
                unset($responseBody);
                
                // Restore previous memory limit
                if ($previousMemoryLimit !== false) {
                    ini_set('memory_limit', $previousMemoryLimit);
                }
                
                return [
                    'success' => false,
                    'message' => 'Invalid or empty JSON response from API.',
                    'raw_response' => $truncatedRaw,
                ];
            }
            
            unset($responseBody); // Free memory immediately
            
            // Restore previous memory limit
            if ($previousMemoryLimit !== false) {
                ini_set('memory_limit', $previousMemoryLimit);
            }
            
            return $decoded;
            
        } catch (GuzzleException $e) {
            Log::error("Bokun API Error: " . $e->getMessage());
            $response = method_exists($e, 'getResponse') ? $e->getResponse() : null;
            $statusCode = $response ? $response->getStatusCode() : 500;
            $errorBody = $response ? (string) $response->getBody() : '';
            
            $decoded = json_decode($errorBody, true);
            if (is_array($decoded)) {
                return array_merge(['success' => false, 'status' => $statusCode], $decoded);
            }
            
            return [
                'success' => false,
                'status' => $statusCode,
                'message' => 'Bokun API request failed: ' . $e->getMessage(),
                'raw_response' => mb_convert_encoding($errorBody, 'UTF-8', 'UTF-8')
            ];
        } catch (\Throwable $e) {
            Log::error("Bokun API Unexpected Error: " . $e->getMessage());
            return [
                'success' => false,
                'status' => 500,
                'message' => 'Unexpected error: ' . mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8')
            ];
        }
    }

    public function makeRawRequest(string $method, string $endpoint, array $query = [], array $body = []): array
    {
        $path = $endpoint;
        if (!empty($query)) {
            $path .= '?' . http_build_query($query);
        }

        $date = Carbon::now()->format('Y-m-d H:i:s');
        $signature = $this->generateSignature($date, $method, $path);

        $headers = [
            'X-Bokun-Date' => $date,
            'X-Bokun-AccessKey' => $this->accessKey,
            'X-Bokun-Signature' => $signature,
            'Accept' => '*/*',
        ];

        $options = ['headers' => $headers];
        if (!empty($body)) {
            $options['json'] = $body;
        }

        $response = $this->client->request($method, $path, $options);

        return [
            'status_code' => $response->getStatusCode(),
            'content_type' => $response->getHeaderLine('Content-Type'),
            'raw_body' => (string) $response->getBody(),
        ];
    }

    public function getFullProductData(string $productId, string $language = 'en', string $currency = 'EUR'): array
    {
        try {
            // Fetch main activity data
            $activity = $this->makeRequest('GET', "/activity.json/{$productId}", [
                'lang' => $language,
                'currency' => $currency,
            ]);

            if (empty($activity) || (isset($activity['success']) && $activity['success'] === false)) {
                return ['status' => false, 'message' => 'Product not found', 'data' => []];
            }

            // Optional: Price list
            $priceList = [];
            try {
                $priceList = $this->makeRequest('GET', "/activity.json/{$productId}/price-list", ['currency' => $currency]);
            } catch (\Throwable $e) {
                Log::error("Price list fetch failed for product {$productId}: " . $e->getMessage());
            }

            // Optional: Availabilities
            $availabilities = [];
            try {
                // Check full current year: start_date = Jan 1 of this year, end_date = Dec 31 of this year
                $yearStart = now()->startOfYear()->format('Y-m-d');
                $yearEnd   = now()->endOfYear()->format('Y-m-d');
                $availabilities = $this->makeRequest('GET', "/activity.json/{$productId}/availabilities", [
                    'start'          => $yearStart,
                    'end'            => $yearEnd,
                    'currency'       => $currency,
                    'lang'           => strtoupper($language),
                    'includeSoldOut' => false,
                ]);
            } catch (\Throwable $e) {
                Log::error("Availability fetch failed for product {$productId}: " . $e->getMessage());
            }

            // Media: Photos (transformed to unified structure)
            $photos = collect($activity['photos'] ?? [])->map(fn($p) => [
                'original' => $p['originalUrl'] ?? null,
                'large'    => collect($p['derived'] ?? [])->firstWhere('name', 'large')['url'] ?? null,
                'preview'  => collect($p['derived'] ?? [])->firstWhere('name', 'preview')['url'] ?? null,
            ])->values()->toArray();

            // Media: Videos (direct pass-through)
            $videos = collect($activity['videos'] ?? [])->map(fn($v) => [
                'id'                => $v['id'] ?? null,
                'title'             => $v['title'] ?? null,
                'sourceUrl'         => $v['sourceUrl'] ?? null,
                'thumbnailUrl'      => $v['thumbnailUrl'] ?? null,
                'cleanThumbnailUrl' => $v['cleanThumbnailUrl'] ?? null,
                'previewUrl'        => $v['previewUrl'] ?? null,
                'cleanPreviewUrl'   => $v['cleanPreviewUrl'] ?? null,
                'html'              => $v['html'] ?? null,
                'providerName'      => $v['providerName'] ?? null,
            ])->values()->toArray();

            // Thumbnail from keyPhoto
            $thumbnail = $activity['keyPhoto']['originalUrl'] ?? ($photos[0]['original'] ?? null);

            // Build full product data — all fields sourced from API only
            return [
                'status' => true,
                'data' => [
                    'provider'       => 'bokun',

                    // Core
                    'title'          => $activity['title'] ?? null,
                    'description'    => $activity['description'] ?? null,
                    'thumbnail'      => $thumbnail,
                    'media'          => [
                        'photos' => $photos,
                        'videos' => $videos,
                    ],

                    // Cancellation & Policy
                    'cancellationPolicy'    => $activity['cancellationPolicy'] ?? null,

                    // Configuration
                    'barcodeType'           => $activity['barcodeType'] ?? null,
                    'timeZone'              => $activity['timeZone'] ?? null,

                    // Contact & Customer Fields
                    'mainContactFields'     => $activity['mainContactFields'] ?? [],
                    'requiredCustomerFields' => $activity['requiredCustomerFields'] ?? [],

                    // Tags & Categories
                    'keywords'              => $activity['keywords'] ?? [],
                    'paymentCurrencies'     => $activity['paymentCurrencies'] ?? [],
                    'customFields'          => $activity['customFields'] ?? [],
                    'tagGroups'             => $activity['tagGroups'] ?? [],
                    'categories'            => $activity['categories'] ?? [],

                    // Vendor & Reviews
                    'vendor'                => $activity['vendor'] ?? null,
                    'reviewRating'          => $activity['reviewRating'] ?? null,
                    'reviewCount'           => $activity['reviewCount'] ?? null,
                    'tripadvisorReview'     => $activity['tripadvisorReview'] ?? null,

                    // Activity Classification
                    'activityType'          => $activity['activityType'] ?? null,
                    'bookingType'           => $activity['bookingType'] ?? null,
                    'scheduleType'          => $activity['scheduleType'] ?? null,
                    'activityCategories'    => $activity['activityCategories'] ?? [],
                    'activityAttributes'    => $activity['activityAttributes'] ?? [],

                    // Booking Cutoff & Limits
                    'cutoffType'                         => $activity['cutoffType'] ?? null,
                    'cutoffReferenceHour'                => $activity['cutoffReferenceHour'] ?? null,
                    'cutoffReferenceMinute'              => $activity['cutoffReferenceMinute'] ?? null,
                    'earlyBookingLimitType'              => $activity['earlyBookingLimitType'] ?? null,
                    'earlyBookingLimitSpecificDateTime'  => $activity['earlyBookingLimitSpecificDateTime'] ?? null,
                    'earlyBookingLimitDaysBefore'        => $activity['earlyBookingLimitDaysBefore'] ?? null,
                    'earlyBookingLimitMonthsBefore'      => $activity['earlyBookingLimitMonthsBefore'] ?? null,
                    'earlyBookingLimitTime'              => $activity['earlyBookingLimitTime'] ?? null,
                    'bookingCutoffMinutes'               => $activity['bookingCutoffMinutes'] ?? null,
                    'bookingCutoffHours'                 => $activity['bookingCutoffHours'] ?? null,
                    'bookingCutoffDays'                  => $activity['bookingCutoffDays'] ?? null,
                    'bookingCutoffWeeks'                 => $activity['bookingCutoffWeeks'] ?? null,
                    'requestDeadlineMinutes'             => $activity['requestDeadlineMinutes'] ?? null,
                    'requestDeadlineHours'               => $activity['requestDeadlineHours'] ?? null,
                    'requestDeadlineDays'                => $activity['requestDeadlineDays'] ?? null,
                    'requestDeadlineWeeks'               => $activity['requestDeadlineWeeks'] ?? null,
                    'reservationTimeout'                 => $activity['reservationTimeout'] ?? null,
                    'vendorReservationTimeout'           => $activity['vendorReservationTimeout'] ?? null,

                    // Capacity & Pass
                    'capacityType'          => $activity['capacityType'] ?? null,
                    'passExpiryType'        => $activity['passExpiryType'] ?? null,
                    'fixedPassExpiryDate'   => $activity['fixedPassExpiryDate'] ?? null,
                    'passCapacity'          => $activity['passCapacity'] ?? null,
                    'passValidForDays'      => $activity['passValidForDays'] ?? null,
                    'passesAvailable'       => $activity['passesAvailable'] ?? null,

                    // Meeting & Activity Type
                    'meetingType'           => $activity['meetingType'] ?? null,
                    'privateActivity'       => $activity['privateActivity'] ?? null,
                    'affiliateHubProduct'   => $activity['affiliateHubProduct'] ?? null,

                    // Requirements
                    'dressCode'                     => $activity['dressCode'] ?? null,
                    'passportRequired'              => $activity['passportRequired'] ?? null,
                    'supportedAccessibilityTypes'   => $activity['supportedAccessibilityTypes'] ?? [],

                    // Start Points & Booking Questions
                    'startPoints'           => $activity['startPoints'] ?? [],
                    'bookingQuestions'       => $activity['bookingQuestions'] ?? [],
                    'passengerFields'       => $activity['passengerFields'] ?? [],

                    // Content: Inclusions / Exclusions / Requirements
                    'inclusions'            => $this->parseHtmlList($activity['included'] ?? ''),
                    'included'              => $activity['included'] ?? null,
                    'exclusions'            => $this->parseHtmlList($activity['excluded'] ?? ''),
                    'excluded'              => $activity['excluded'] ?? null,
                    'requirements'          => $activity['requirements'] ?? null,
                    'knowBeforeYouGoItems'  => $activity['knowBeforeYouGoItems'] ?? [],
                    'attention'             => $activity['attention'] ?? null,

                    // Location
                    'locationCode'          => $activity['locationCode'] ?? null,
                    'googlePlace'           => $activity['googlePlace'] ?? null,

                    // Resources & Combos
                    'resourceSlots'              => $activity['resourceSlots'] ?? [],
                    'boxedActivityId'            => $activity['boxedActivityId'] ?? null,
                    'comboActivity'              => $activity['comboActivity'] ?? null,
                    'comboParts'                 => $activity['comboParts'] ?? [],
                    'returnProduct'              => $activity['returnProduct'] ?? null,
                    'offers'                     => $activity['offers'] ?? [],
                    'ticketPerComboComponent'    => $activity['ticketPerComboComponent'] ?? null,
                    'ticketComboComponents'      => $activity['ticketComboComponents'] ?? [],

                    // Pickup & Dropoff
                    'pickupActivityId'              => $activity['pickupActivityId'] ?? null,
                    'allowCustomizedBookings'       => $activity['allowCustomizedBookings'] ?? null,
                    'pickupService'                 => $activity['pickupService'] ?? null,
                    'pickupAllotment'               => $activity['pickupAllotment'] ?? null,
                    'pickupAllotmentType'            => $activity['pickupAllotmentType'] ?? null,
                    'useComponentPickupAllotments'   => $activity['useComponentPickupAllotments'] ?? null,
                    'pickupFlags'                   => $activity['pickupFlags'] ?? [],
                    'customPickupAllowed'            => $activity['customPickupAllowed'] ?? null,
                    'pickupMinutesBefore'            => $activity['pickupMinutesBefore'] ?? null,
                    'pickupTimeWindowInMinutes'      => $activity['pickupTimeWindowInMinutes'] ?? null,
                    'pickupTimeLocationBased'        => $activity['pickupTimeLocationBased'] ?? null,
                    'pickupTimeByLocations'          => $activity['pickupTimeByLocations'] ?? [],
                    'noPickupMsg'                    => $activity['noPickupMsg'] ?? null,
                    'ticketMsg'                      => $activity['ticketMsg'] ?? null,
                    'showGlobalPickupMsg'            => $activity['showGlobalPickupMsg'] ?? null,
                    'showNoPickupMsg'                => $activity['showNoPickupMsg'] ?? null,
                    'pickupPlaceGroups'              => $activity['pickupPlaceGroups'] ?? [],
                    'dropoffService'                => $activity['dropoffService'] ?? null,
                    'dropoffFlags'                  => $activity['dropoffFlags'] ?? [],
                    'customDropoffAllowed'           => $activity['customDropoffAllowed'] ?? null,
                    'useSameAsPickUpPlaces'          => $activity['useSameAsPickUpPlaces'] ?? null,
                    'dropoffPlaceGroups'             => $activity['dropoffPlaceGroups'] ?? [],

                    // Day-based Availability
                    'dayBasedAvailability'   => $activity['dayBasedAvailability'] ?? null,
                    'selectFromDayOptions'   => $activity['selectFromDayOptions'] ?? null,
                    'dayOptions'             => $activity['dayOptions'] ?? [],

                    // Guidance
                    'guidanceTypes'          => $activity['guidanceTypes'] ?? [],

                    // Rates & Pricing
                    'defaultRateId'          => $activity['defaultRateId'] ?? null,
                    'rates'                  => $activity['rates'] ?? [],
                    'ticketPerPerson'        => $activity['ticketPerPerson'] ?? null,
                    'pricingCategories'      => $activity['pricingCategories'] ?? [],

                    // Duration
                    'durationType'           => $activity['durationType'] ?? null,
                    'duration'               => $activity['duration'] ?? null,
                    'durationMinutes'        => $activity['durationMinutes'] ?? null,
                    'durationHours'          => $activity['durationHours'] ?? null,
                    'durationDays'           => $activity['durationDays'] ?? null,
                    'durationWeeks'          => $activity['durationWeeks'] ?? null,
                    'durationText'           => $activity['durationText'] ?? null,

                    // Age & Price
                    'minAge'                     => $activity['minAge'] ?? null,
                    'originalDefaultPrice'       => $activity['originalDefaultPrice'] ?? null,
                    'nextDefaultPriceAsText'     => $activity['nextDefaultPriceAsText'] ?? null,
                    'nextDefaultPrice'           => $activity['nextDefaultPrice'] ?? null,
                    'nextDefaultPriceMoney'      => $activity['nextDefaultPriceMoney'] ?? null,

                    // Difficulty
                    'difficultyLevel'        => $activity['difficultyLevel'] ?? null,

                    // Itinerary & Schedule
                    'agendaItems'            => $activity['agendaItems'] ?? [],
                    'startTimes'             => $activity['startTimes'] ?? [],
                    'bookableExtras'         => $activity['bookableExtras'] ?? [],
                    'route'                  => $activity['route'] ?? null,

                    // Widget & Labels
                    'widgetSettings'         => $activity['widgetSettings'] ?? null,
                    'bookingLabels'          => $activity['bookingLabels'] ?? null,

                    // Fetched from separate endpoints
                    'priceList'              => $priceList,
                    'availabilities'         => $availabilities,
                ]
            ];
        } catch (\Throwable $e) {
            Log::error("Bokun Product Data Error: " . $e->getMessage());
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Parse HTML string (br/li separated) into a clean string array.
     */
    private function parseHtmlList(string $html): array
    {
        if (empty($html)) return [];
        $raw = str_replace(['<br />', '<br/>', '</li>'], '|', $html);
        return array_values(array_filter(array_map('trim', array_map('strip_tags', explode('|', $raw)))));
    }

    public function getFullProductDataRaw(string $productId, string $language = 'en', string $currency = 'EUR'): array
    {
        try {
            // Fetch all relevant Bokun endpoints
            $activity = $this->makeRequest('GET', "/activity.json/{$productId}", [
                'lang' => $language,
                'currency' => $currency,
            ]);

            if (empty($activity) || (isset($activity['success']) && $activity['success'] === false)) {
                return ['status' => false, 'message' => 'Product not found', 'data' => []];
            }

            // Optional endpoints
            $priceList = [];
            try {
                $priceList = $this->makeRequest('GET', "/activity.json/{$productId}/price-list", ['currency' => $currency]);
            } catch (\Throwable $e) {
            }

            $availabilities = [];
            try {
                // Check full current year: start_date = Jan 1 of this year, end_date = Dec 31 of this year
                $yearStart = now()->startOfYear()->format('Y-m-d');
                $yearEnd   = now()->endOfYear()->format('Y-m-d');
                $availabilities = $this->makeRequest('GET', "/activity.json/{$productId}/availabilities", [
                    'start'          => $yearStart,
                    'end'            => $yearEnd,
                    'currency'       => $currency,
                    'lang'           => strtoupper($language),
                    'includeSoldOut' => false,
                ]);
            } catch (\Throwable $e) {
            }

            // Return raw data
            return [
                'status' => true,
                'data' => [
                    'activity' => $activity,
                    'priceList' => $priceList,
                    'availabilities' => $availabilities,
                ],
            ];
        } catch (\Throwable $e) {
            Log::error("Bokun Product Data Error: " . $e->getMessage());
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function getOfficialAvailability(array $payload): array
    {
        $activityId = $payload['id'];
        unset($payload['id']);

        $endpoint = "/activity.json/{$activityId}/availabilities";

        return $this->makeRequest('GET', $endpoint, $payload);
    }

    public function checkAvailability(string $activityApiId, string $startDate, string $endDate, ?string $currency = null, ?string $language = 'EN'): array
    {
        $currency = $currency ?? 'EUR';
        $language = $language ?? 'EN';
        
        $endpoint = "/activity.json/{$activityApiId}/availabilities";

        // Bokun supports multi-currency — always send the currency param so Bokun
        // returns prices already converted to the user's requested currency.
        $query = [
            'start'          => $startDate,
            'end'            => $endDate,
            'currency'       => strtoupper($currency),
            'lang'           => strtoupper($language),
            'includeSoldOut' => false,
        ];

        $rawResponse = $this->makeRequest('GET', $endpoint, $query);

        if (empty($rawResponse) || isset($rawResponse['success']) && !$rawResponse['success']) {
            return [
                'success' => false,
                'message' => $rawResponse['message'] ?? 'Failed to fetch availability.',
                'raw_response' => $rawResponse,
            ];
        }

        $availabilities = collect($rawResponse)->map(function ($item) {
            return [
                'availability_id' => $item['id'] ?? null,
                'date' => isset($item['date']) ? date('Y-m-d', $item['date'] / 1000) : null,
                'formatted_date' => isset($item['date']) ? \Carbon\Carbon::createFromTimestamp($item['date'] / 1000)->format('F j, Y') : null,
                'formatted_time' => isset($item['startTime']) ? \Carbon\Carbon::parse($item['startTime'])->format('g:i A') : null,
                'localized_date' => $item['localizedDate'] ?? null,
                'start_time' => $item['startTime'] ?? null,
                'availability_count' => $item['availabilityCount'] ?? null,
                'unlimited_availability' => $item['unlimitedAvailability'] ?? false,
                'sold_out' => $item['soldOut'] ?? false,
                'min_participants' => $item['minParticipants'] ?? null,
                'rates' => $item['rates'] ?? [],
                'prices_by_rate' => $item['pricesByRate'] ?? [],
                'currency' => $item['pricesByRate'][0]['pricePerCategoryUnit'][0]['amount']['currency'] ?? null,
            ];
        })->toArray();

        return [
            'success' => true,
            'availabilities' => $availabilities,
            'raw_response' => $rawResponse,
        ];
    }

    /**
     * Create a booking in Bokun
     */
    public function createBooking(string $activityApiId, string $date, int $pax, array $customer, array $extra = []): array
    {
        $endpoint = "/activity.json/{$activityApiId}/bookings";

        $payload = array_merge([
            'date' => $date,
            'pax' => $pax,
            'customer' => $customer,
        ], $extra);

        $rawResponse = $this->makeRequest('POST', $endpoint, [], $payload);

        $success = isset($rawResponse['bookingId']);
        $status = $rawResponse['status'] ?? ($success ? 'confirmed' : 'failed');

        return [
            'success' => $success,
            'external_booking_id' => $rawResponse['bookingId'] ?? null,
            'status' => $status,
            'total_price' => $rawResponse['total_price'] ?? null,
            'currency' => $rawResponse['currency'] ?? null,
            'raw_request' => $payload,
            'raw_response' => $rawResponse,
        ];
    }
    /**
     * Check if a Bokun product has any valid (non-sold-out) availability.
     * Fetches availability for the next year and checks for at least one valid slot.
     */
    // ==================== ACTIVITIES APIs ====================
    public function getAllActivities(string $language = 'EN', string $currency = 'EUR', int $page = 1, int $pageSize = 10, array $filters = []): array
    {
        $query = ['lang' => strtoupper($language), 'currency' => strtoupper($currency)];
        $body = array_merge(['page' => $page, 'size' => $pageSize], $filters);
        return $this->makeRequest('POST', '/activity.json/search', $query, $body);
    }

    public function getActiveActivityIds(): array
    {
        return $this->makeRequest('GET', '/activity.json/active-ids');
    }

    public function getActivity(int|string $activityId, string $language = 'EN', string $currency = 'EUR'): array
    {
        $query = ['lang' => strtoupper($language), 'currency' => strtoupper($currency)];
        return $this->makeRequest('GET', "/activity.json/{$activityId}", $query);
    }

    public function getAvailableDates(int|string $activityId, string $startDate, string $endDate, string $currency = 'EUR', string $language = 'EN'): array
    {
        // Bokun supports multi-currency — always send the currency param so Bokun
        // returns prices already converted to the user's requested currency.
        $query = [
            'start'          => $startDate,
            'end'            => $endDate,
            'currency'       => strtoupper($currency),
            'lang'           => strtoupper($language),
            'includeSoldOut' => false,
        ];

        return $this->makeRequest('GET', "/activity.json/{$activityId}/availabilities", $query);
    }

    public function getActivityPriceList(int|string $activityId): array
    {
        return $this->makeRequest('GET', "/activity.json/{$activityId}/price-list");
    }

    public function getPickupPlaces(int|string $activityId): array
    {
        return $this->makeRequest('GET', "/activity.json/{$activityId}/pickup-places");
    }
    public function hasAvailability(string $activityApiId): bool
    {
        try {
            // Check full current year: start_date = Jan 1, end_date = Dec 31
            $yearStart = now()->startOfYear()->format('Y-m-d');
            $yearEnd   = now()->endOfYear()->format('Y-m-d');

            $result = $this->checkAvailability(
                $activityApiId,
                $yearStart,
                $yearEnd
            );

            if (!($result['success'] ?? false) || empty($result['availabilities'])) {
                return false;
            }

            // Check if at least one slot is not sold out
            foreach ($result['availabilities'] as $avail) {
                $isSoldOut = (bool) ($avail['sold_out'] ?? false);
                $availCount = $avail['availability_count'] ?? 0;
                $isUnlimited = (bool) ($avail['unlimited_availability'] ?? false);

                if (!$isSoldOut && ($availCount > 0 || $isUnlimited)) {
                    return true; // Found at least one valid slot
                }
            }

            return false;
        } catch (\Exception $e) {
            Log::error("Bokun hasAvailability check failed for [{$activityApiId}]: " . $e->getMessage());
            return false;
        }
    }

    public function searchActivities(array $params): array
    {
        $query = [
            'lang' => $params['language'] ?? 'EN',
            'currency' => $params['currency'] ?? 'EUR',
        ];

        // Build the request body from ALL supported POST /activity.json/search params
        // Note: The Bokun API uses 'size' not 'pageSize' for pagination
        $body = [];

        // Pagination
        if (isset($params['page'])) {
            $body['page'] = (int) $params['page'];
        }
        // Use 'size' (the actual API parameter name) - map from 'pageSize' or 'size'
        if (isset($params['size'])) {
            $body['size'] = (int) $params['size'];
        } elseif (isset($params['pageSize'])) {
            $body['size'] = (int) $params['pageSize'];
        }

        // Legacy top-level keys (kept for backward compatibility)
        // Note: 'city' is NOT a valid API parameter - use textFilter instead
        foreach (['text', 'start', 'end'] as $key) {
            if (!empty($params[$key])) {
                $body[$key] = $params[$key];
            }
        }

        // Full textFilter object (preferred over legacy 'text')
        if (!empty($params['textFilter'])) {
            $body['textFilter'] = $params['textFilter'];
        }

        // Date range - use the API's actual parameter names
        if (!empty($params['startDate'])) {
            $body['startDate'] = $params['startDate'];
        }
        if (!empty($params['endDate'])) {
            $body['endDate'] = $params['endDate'];
        }

        // Sorting
        if (!empty($params['sortField'])) {
            $body['sortField'] = $params['sortField'];
        }
        if (!empty($params['sortOrder'])) {
            $body['sortOrder'] = $params['sortOrder'];
        }

        // Boolean flags
        if (isset($params['excludeBoxes'])) {
            $body['excludeBoxes'] = (bool) $params['excludeBoxes'];
        }
        if (isset($params['comboActivity'])) {
            $body['comboActivity'] = (bool) $params['comboActivity'];
        }

        // Vendor & external ID filters
        if (!empty($params['vendorId'])) {
            $body['vendorId'] = (int) $params['vendorId'];
        }
        if (!empty($params['externalId'])) {
            $body['externalId'] = $params['externalId'];
        }

        // Participant filters
        if (!empty($params['participants'])) {
            $body['participants'] = (int) $params['participants'];
        }
        if (!empty($params['participantsByAge']) && is_array($params['participantsByAge'])) {
            $body['participantsByAge'] = $params['participantsByAge'];
        }

        // Published since
        if (!empty($params['publishedSince'])) {
            $body['publishedSince'] = $params['publishedSince'];
        }

        // Facet filters (advanced)
        if (!empty($params['facetFilters'])) {
            $body['facetFilters'] = $params['facetFilters'];
        }

        // Geo location filters (advanced)
        if (!empty($params['agendaLocationFilters'])) {
            $body['agendaLocationFilters'] = $params['agendaLocationFilters'];
        }
        if (!empty($params['startPointLocationFilters'])) {
            $body['startPointLocationFilters'] = $params['startPointLocationFilters'];
        }

        // Legacy filters object (kept for backward compatibility)
        $filters = $params['filters'] ?? [];
        if (!empty($filters)) {
            $body['filters'] = $filters;
        }

        try {
            return $this->makeRequest('POST', '/activity.json/search', $query, $body);
        } catch (\Throwable $e) {
            Log::error('Bokun Search Error: ' . $e->getMessage());
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function getCheckoutOptions(array $bookingRequest, string $currency = 'EUR', string $language = 'en'): array
    {
        $query = ['currency' => strtoupper($currency), 'lang' => strtoupper($language)];
        return $this->makeRequest('POST', '/checkout.json/options/booking-request', $query, $bookingRequest);
    }

    public function submitDirectCheckout(array $payload, string $currency = 'EUR', string $language = 'en'): array
    {
        $payload['source'] = $payload['source'] ?? 'DIRECT_REQUEST';
        if (isset($payload['directBooking']) && is_array($payload['directBooking'])) {
            $payload['directBooking']['currency'] = strtoupper($currency);
        }
        $query = ['currency' => strtoupper($currency), 'lang' => strtoupper($language)];
        return $this->makeRequest('POST', '/checkout.json/submit', $query, $payload);
    }

    public function confirmReservedBooking(string $confirmationCode, array $body): array
    {
        return $this->makeRequest('POST', "/checkout.json/confirm-reserved/{$confirmationCode}", [], $body);
    }

    public function confirmBookingAfterPayment(string $confirmationCode, array $confirmPayload, bool $sendCustomerNotification, string $currency = 'EUR', string $lang = 'EN'): array
    {
        $query = [
            'currency' => strtoupper($currency),
            'lang'     => strtoupper($lang),
            'sendCustomerNotification' => $sendCustomerNotification ? 'true' : 'false',
        ];

        $response = $this->makeRawRequest('POST', "/booking.json/{$confirmationCode}/confirm", $query, $confirmPayload);

        $statusCode = $response['status_code'] ?? 0;
        $contentType = strtolower($response['content_type'] ?? '');
        $rawBody = $response['raw_body'] ?? '';

        // Reject non-JSON responses
        if (strpos($contentType, 'application/json') === false) {
            Log::error("Bokun confirmBookingAfterPayment returned non-JSON response", [
                'confirmation_code' => $confirmationCode,
                'status_code' => $statusCode,
                'content_type' => $contentType,
            ]);
            return [
                'success' => false,
                'message' => "Bokun API returned non-JSON content (Content-Type: {$contentType}) when confirming booking {$confirmationCode}.",
                'status_code' => $statusCode,
                'content_type' => $contentType,
            ];
        }

        // Decode JSON safely
        $decoded = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("Bokun confirmBookingAfterPayment returned malformed JSON", [
                'confirmation_code' => $confirmationCode,
                'json_error' => json_last_error_msg(),
            ]);
            return [
                'success' => false,
                'message' => 'Malformed JSON response from Bokun API: ' . json_last_error_msg(),
                'status_code' => $statusCode,
            ];
        }

        return $decoded;
    }

    public function abortReservedBooking(string $confirmationCode, bool $timeout = false): array
    {
        return $this->makeRequest('GET', "/booking.json/{$confirmationCode}/abort-reserved", [
            'timeout' => $timeout ? 'true' : 'false',
        ]);
    }

    public function markPaymentError(string $confirmationCode, array $payload): array
    {
        return $this->makeRequest('POST', "/booking.json/{$confirmationCode}/payment-error", [], $payload);
    }

    public function cancelBooking(string $confirmationCode, array $payload): array
    {
        return $this->makeRequest('POST', "/booking.json/cancel-booking/{$confirmationCode}", [], $payload);
    }

    public function cancelProductBooking(string $productConfirmationCode, array $payload): array
    {
        return $this->makeRequest('POST', "/booking.json/cancel-product-booking/{$productConfirmationCode}", [], $payload);
    }

    public function getBookingSummary(int|string $id): array
    {
        $response = $this->makeRawRequest('GET', "/booking.json/{$id}/summary");

        $statusCode = $response['status_code'] ?? 0;
        $contentType = strtolower($response['content_type'] ?? '');
        $rawBody = $response['raw_body'] ?? '';

        // Support PDF documents natively (Bokun often returns the summary invoice as PDF)
        if (strpos($contentType, 'application/pdf') !== false) {
            return [
                'success' => true,
                'raw_body' => $rawBody,
                'content_type' => $contentType,
                'status_code' => $statusCode,
            ];
        }

        // Reject other non-JSON responses (e.g. HTML error pages)
        if (strpos($contentType, 'application/json') === false) {
            Log::error("Bokun getBookingSummary returned non-JSON response", [
                'booking_id' => $id,
                'status_code' => $statusCode,
                'content_type' => $contentType,
            ]);
            return [
                'success' => false,
                'message' => "Bokun API returned non-JSON content (Content-Type: {$contentType}). The booking summary endpoint may not be available for this booking ID. Use the confirmation code (e.g. THI-{$id}) instead of the numeric booking ID, or try the /confirm endpoint.",
                'status_code' => $statusCode,
                'content_type' => $contentType,
            ];
        }

        // Decode JSON safely
        $decoded = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("Bokun getBookingSummary returned malformed JSON", [
                'booking_id' => $id,
                'json_error' => json_last_error_msg(),
            ]);
            return [
                'success' => false,
                'message' => 'Malformed JSON response from Bokun API: ' . json_last_error_msg(),
                'status_code' => $statusCode,
            ];
        }

        return $decoded;
    }

    public function getActivityBookingTicket(string $productConfirmationCode): array
    {
        return $this->makeRawRequest('GET', "/booking.json/activity-booking/{$productConfirmationCode}/ticket");
    }

    // ==================== SHOPPING CART ====================
    public function getCart(string $sessionId): array
    {
        return $this->makeRequest('GET', "/cart.json/{$sessionId}");
    }
    public function addActivityToCart(string $sessionId, array $bookingReq): array
    {
        return $this->makeRequest('POST', "/cart.json/{$sessionId}/activity", [], $bookingReq);
    }
    public function removeFromCart(string $sessionId, string $confCode): array
    {
        return $this->makeRequest('GET', "/cart.json/{$sessionId}/remove/{$confCode}");
    }
    public function applyPromoCodeToCart(string $sessionId, string $promo): array
    {
        return $this->makeRequest('GET', "/cart.json/{$sessionId}/apply-promo-code/{$promo}");
    }
    public function removePromoCodeFromCart(string $sessionId): array
    {
        return $this->makeRequest('GET', "/cart.json/{$sessionId}/remove-promo-code");
    }
    public function applyGiftCardToCart(string $sessionId, string $giftCard): array
    {
        return $this->makeRequest('GET', "/cart.json/{$sessionId}/apply-gift-card/{$giftCard}");
    }

    // ==================== CHECKOUT (CART) ====================
    public function getCheckoutOptionsForCart(string $cartUuid, string $currency = 'EUR', string $lang = 'EN'): array
    {
        return $this->makeRequest('GET', "/checkout.json/options/shopping-cart/{$cartUuid}", ['currency' => strtoupper($currency), 'lang' => $lang]);
    }
    public function submitShoppingCartCheckout(array $payload, string $currency = 'EUR', string $lang = 'EN'): array
    {
        return $this->makeRequest('POST', '/checkout.json/submit', ['currency' => strtoupper($currency), 'lang' => $lang], $payload);
    }
    public function confirmReservedCheckout(string $confCode, array $payload, string $currency = 'EUR', string $lang = 'EN'): array
    {
        return $this->makeRequest('POST', "/checkout.json/confirm-reserved/{$confCode}", ['currency' => strtoupper($currency), 'lang' => $lang], $payload);
    }

    // ==================== QUESTIONS ====================
    public function getCartQuestions(string $sessionId): array
    {
        return $this->makeRequest('GET', "/question.json/shopping-cart/{$sessionId}");
    }
    public function answerCartQuestions(string $sessionId, array $payload): array
    {
        return $this->makeRequest('POST', "/question.json/shopping-cart/{$sessionId}", [], $payload);
    }
    public function getBookingQuestions(int|string $bookingId): array
    {
        return $this->makeRequest('GET', "/question.json/booking/{$bookingId}");
    }
    public function answerBookingQuestions(int|string $bookingId, array $payload): array
    {
        return $this->makeRequest('POST', "/question.json/booking/{$bookingId}", [], $payload);
    }
    public function getActivityBookingQuestions(int|string $activityBookingId): array
    {
        return $this->makeRequest('GET', "/question.json/activity-booking/{$activityBookingId}");
    }
    public function answerActivityBookingQuestions(int|string $activityBookingId, array $payload): array
    {
        return $this->makeRequest('POST', "/question.json/activity-booking/{$activityBookingId}", [], $payload);
    }
}
