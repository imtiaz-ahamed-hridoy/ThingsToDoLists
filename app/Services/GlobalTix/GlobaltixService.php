<?php

namespace App\Services\GlobalTix;

use App\Services\Provider\ProviderInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GlobaltixService implements ProviderInterface
{
    protected GlobalTixAuthService $authService;
    protected GlobalTixBookingService $bookingService;
    protected GlobalTixProductService $productService;

    public function __construct(
        GlobalTixAuthService $authService,
        GlobalTixBookingService $bookingService,
        ?GlobalTixProductService $productService = null
    ) {
        $this->authService = $authService;
        $this->bookingService = $bookingService;
        // Allow tests to omit the third dependency and resolve from container when not provided
        $this->productService = $productService ?? app(GlobalTixProductService::class);
    }

    public function searchActivities(array $params): array
    {
        return $this->productService->searchActivities([
            'lang' => $params['language'] ?? 'en',
            'currency' => $params['currency'] ?? 'EUR',
            'page' => $params['page'] ?? 1,
            'pageSize' => $params['pageSize'] ?? 10,
            'filters' => $params['filters'] ?? []
        ]);
    }

    /**
     * Get full product/activity details from GlobalTix.
     *
     * @param string $activityApiId
     * @param string $language
     * @param string $currency
     * @return array
     * @throws \Exception
     */
    public function getFullProductData(string $activityApiId, string $language = 'en', string $currency = 'EUR'): array
    {
        try {
            $productInfo = $this->productService->getProductInfo($activityApiId);

            if (
                !array_key_exists('data', $productInfo)
                || empty($productInfo['data'])
                || !is_array($productInfo['data'])
            ) {
                $providerMessage = $productInfo['message']
                    ?? data_get($productInfo, 'error.message')
                    ?? data_get($productInfo, 'error.code')
                    ?? 'Product not found or not accessible for this GlobalTix account.';

                throw new \Exception(
                    "GlobalTix product [{$activityApiId}] import failed: {$providerMessage} "
                        . "If this is a Bokun ID, switch provider to Bokun before importing."
                );
            }

            $activity = $productInfo['data'];

            $optionsResponse = $this->productService->getProductOptions($activityApiId);
            $activityOptions = $optionsResponse['data'] ?? [];

            $dateFrom = now()->toDateString();
            $dateTo = now()->addMonths(3)->toDateString();
            $availabilityResponse = $this->productService->getActivityAvailability($activityApiId, $dateFrom, $dateTo, $currency);
            $availability = $availabilityResponse['data'] ?? [];

            $thumbnailId = $activity['image'] ?? null;
            $thumbnailUrl = null;
            $photos = [];
            foreach (($activity['media'] ?? []) as $m) {
                // Ensure there is some absolute/relative path indicator
                $path = $m['path'] ?? null;
                if (!$path) continue;
                $url = 'https://res.globaltix.com/image/' . $path;
                $photo = [
                    'original' => $url,
                    'large' => $url,
                    'preview' => $url,
                    'name' => $m['name'] ?? null,
                ];
                $photos[] = $photo;
                if ($path === $thumbnailId) {
                    $thumbnailUrl = $url;
                }
            }
            if (!$thumbnailUrl && !empty($photos)) $thumbnailUrl = $photos[0]['original'];

            $rates = [];
            $pricingCategories = [];
            $uniqueTickets = [];

            // Build rates and pricing categories intelligently
            foreach ($activityOptions as $option) {
                $rates[] = [
                    'id' => $option['id'] ?? null,
                    'title' => $option['name'] ?? null,
                    'rateCode' => (string) ($option['id'] ?? ''),
                    'description' => $option['description'] ?? null,
                    'ticketValidity' => $option['ticketValidity'] ?? null,
                ];

                foreach (($option['ticketType'] ?? []) as $tt) {
                    if (!isset($uniqueTickets[$tt['id']])) {
                        $uniqueTickets[$tt['id']] = true;
                        $pricingCategories[] = [
                            'id' => $tt['id'] ?? null,
                            'title' => $tt['name'] ?? null,
                            'ticketCategory' => $tt['name'] ?? null,
                            'minAge' => $tt['ageFrom'] ?? null,
                            'maxAge' => $tt['ageTo'] ?? null,
                            'originalPrice' => $tt['originalPrice'] ?? null,
                            'recommendedSellingPrice' => $tt['recommendedSellingPrice'] ?? null,
                        ];
                    }
                }
            }

            // Unify inclusions/exclusions from roots and primary options
            $inclusions = array_filter(array_map(function ($inc) {
                return is_array($inc) ? ($inc['value'] ?? null) : $inc;
            }, $activity['inclusions'] ?? []));

            $exclusions = array_filter(array_map(function ($exc) {
                return is_array($exc) ? ($exc['value'] ?? null) : $exc;
            }, $activity['exclusions'] ?? []));

            $country = $activity['country'] ?? null;
            $city = $activity['city'] ?? null;
            $allTicketCandidates = !empty($availability) ? array_merge($pricingCategories, $availability) : $pricingCategories;
            $resolvedPrices = \App\Services\Sync\PriceCalculator::resolveProductDisplayPrices($allTicketCandidates, $activity['fromPrice'] ?? null, $activity['originalPrice'] ?? null, $country, $city);
            $resolvedFromPrice = $resolvedPrices['from_price'] ?? $activity['fromPrice'] ?? null;
            $resolvedOrigPrice = $resolvedPrices['original_price'] ?? $activity['originalPrice'] ?? null;

            $data = [
                'provider' => 'globaltix',

                // Core
                'title' => $activity['name'] ?? null,
                'description' => $activity['description'] ?? null,
                'thumbnail' => $thumbnailUrl,
                'media' => [
                    'photos' => $photos,
                    'videos' => [],
                ],

                // Configuration
                'timeZone' => $activity['timezoneOffset'] ?? null,
                'currency' => $activity['currency'] ?? $currency,

                // Tags & Categories
                'keywords' => explode(',', $activity['keywords'] ?? ''),
                'categories' => [$activity['category'] ?? null],

                // Vendor & Reviews
                'vendor' => $activity['merchant'] ?? null,
                'reviewRating' => $activity['rating'] ?? null,
                'reviewCount' => $activity['reviewCount'] ?? null,

                // Booking Cutoff & Limits
                'isInstantConfirmation' => $activity['isInstantConfirmation'] ?? false,
                'isCancellable' => $activity['isCancellable'] ?? false,

                // Content
                'inclusions' => array_values($inclusions),
                'exclusions' => array_values($exclusions),
                'highlights' => $activity['highlights'] ?? [],
                'thingsToNote' => $activity['thingsToNote'] ?? [],
                'whatToExpect' => $activity['whatToExpect'] ?? null,

                // Location
                'location' => [
                    'address' => $activity['addressLine'] ?? null,
                    'city' => $activity['city'] ?? null,
                    'country' => $activity['country'] ?? null,
                    'latitude' => $activity['latitude'] ?? null,
                    'longitude' => $activity['longitude'] ?? null,
                ],

                // Rates & Pricing
                'rates' => $rates,
                'pricingCategories' => $pricingCategories,

                // Keep raw available logic cleanly formatted
                'from_price' => $resolvedFromPrice,
                'fromPrice' => $resolvedFromPrice,
                'original_price' => $resolvedOrigPrice,
                'originalPrice' => $resolvedOrigPrice,
                'price' => $resolvedFromPrice,
                'originalDefaultPrice' => $resolvedOrigPrice,
                'nextDefaultPriceAsText' => isset($resolvedFromPrice) && $resolvedFromPrice !== null ? (string) $resolvedFromPrice : null,
                'nextDefaultPrice' => $resolvedFromPrice,

                // Availabilities array passed efficiently exactly like Bokun does for its frontend processor
                'availabilities' => $availability,

                // Raw payload retention (if frontend specifically requests specific things inside raw payload later)
                'activityOptions_raw' => $activityOptions,
            ];

            return [
                'status' => true,
                'data'   => $data,
            ];
        } catch (\Exception $e) {
            Log::error('GlobalTix getFullProductData Exception', [
                'message' => $e->getMessage(),
                'activity_id' => $activityApiId,
            ]);
            return [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Infer ticket category (ADULT/CHILD/INFANT) from ticket name.
     */
    private function inferTicketCategory(string $name): string
    {
        $lower = strtolower($name);
        if (str_contains($lower, 'family')) return \Illuminate\Support\Str::upper(\Illuminate\Support\Str::slug($name, '_'));
        if (str_contains($lower, 'child') || str_contains($lower, 'kid')) return 'CHILD';
        if (str_contains($lower, 'infant') || str_contains($lower, 'baby')) return 'INFANT';
        if (str_contains($lower, 'senior') || str_contains($lower, 'elder')) return 'SENIOR';
        if (str_contains($lower, 'youth') || str_contains($lower, 'teen')) return 'YOUTH';
        if (str_contains($lower, 'adult')) return 'ADULT';
        return \Illuminate\Support\Str::upper(\Illuminate\Support\Str::slug($name, '_'));
    }

    /**
     * Extract simple string array from GlobalTix value-object arrays.
     */
    private function extractValueArray(array|string|null $raw): array
    {
        if (empty($raw)) return [];
        if (is_string($raw)) return [strip_tags($raw)];

        $result = [];
        foreach ($raw as $item) {
            if (is_array($item)) {
                $value = $item['value'] ?? $item['label'] ?? $item['name'] ?? null;
                if ($value !== null) {
                    $result[] = strip_tags((string) $value);
                }
            } elseif (is_string($item)) {
                $result[] = strip_tags($item);
            }
        }
        return array_values(array_filter($result));
    }

    public function getOfficialAvailability(array $payload): array
    {
        // Re-map the generic 'id' field from our validated payload to 'ticketTypeID' for GlobalTix
        if (isset($payload['id'])) {
            $payload['ticketTypeID'] = $payload['id'];
            unset($payload['id']);
        }

        return $this->productService->checkOfficialAvailability($payload);
    }


    public function checkAvailability(string $activityApiId, string $startDate, string $endDate, ?string $currency = null, ?string $language = 'EN', bool $isRetry = false): array
    {
        try {
            $response = $this->productService->getActivityAvailability($activityApiId, $startDate, $endDate, $currency ?? 'EUR');

            return [
                'success' => $response['status'] ?? false,
                'availabilities' => $response['data'] ?? [],
                'raw_request' => [
                    'activityId' => $activityApiId,
                    'dateFrom' => $startDate,
                    'dateTo' => $endDate,
                    'currency' => $currency ?? 'EUR',
                ],
                'raw_response' => $response,
            ];
        } catch (\Exception $e) {
            Log::error('GlobalTix checkAvailability error', [
                'activity_id' => $activityApiId,
                'message' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'availabilities' => [],
                'raw_request' => ['activityId' => $activityApiId],
                'raw_response' => null,
            ];
        }
    }

    public function createBooking(string $activityApiId, string $date, int $pax, array $customer, array $extra = []): array
    {
        try {
            $ticketTypeId = $extra['ticket_type_id'] ?? null;
            if (!$ticketTypeId) {
                throw new \Exception('ticket_type_id is required in extra data for GlobalTix booking');
            }

            // Build ticketTypes array per GlobalTix API docs:
            // visitDate and visitTime must be inside each ticketTypes entry
            $visitTime = $extra['visit_time'] ?? '00:00:00';
            $ticketTypes = [
                [
                    'id' => (int)$ticketTypeId,
                    'quantity' => $pax,
                    'visitDate' => $date,
                    'visitTime' => $visitTime,
                    'index' => 0,
                    'questionList' => $extra['questionList'] ?? [],
                ]
            ];

            // Build the full reservation payload per GlobalTix API docs
            $reservePayload = [
                'customerName' => $customer['name'] ?? $customer['customerName'] ?? 'Guest',
                'email' => $customer['email'] ?? '',
                'mobileNumber' => $customer['mobileNumber'] ?? $customer['phone'] ?? '',
                'mobilePrefix' => $customer['mobilePrefix'] ?? '',
                'passportNumber' => $customer['passportNumber'] ?? '',
                'paymentMethod' => $extra['paymentMethod'] ?? 'CREDIT',
                'alternateEmail' => $customer['alternateEmail'] ?? '',
                'otherInfo' => [
                    'partnerReference' => $extra['partnerReference'] ?? '',
                ],
                'ticketTypes' => $ticketTypes,
                'remarks' => $extra['remarks'] ?? '',
            ];

            // 1. Reserve
            $reserveResponse = $this->bookingService->reserveBooking($reservePayload);

            if (!isset($reserveResponse['data']['referenceNumber'])) {
                throw new \Exception($reserveResponse['message'] ?? $reserveResponse['error'] ?? 'Reserve failed');
            }

            $referenceNumber = $reserveResponse['data']['referenceNumber'];

            // 2. Confirm immediately for createBooking
            $confirmResponse = $this->bookingService->confirmBooking($referenceNumber, $extra['remarks'] ?? '');

            $success = ($confirmResponse['status'] ?? false) === true || ($confirmResponse['success'] ?? false) === true;

            return [
                'success' => $success,
                'external_booking_id' => $referenceNumber,
                'status' => $success ? 'confirmed' : 'failed',
                'total_price' => (float)($reserveResponse['data']['amount'] ?? 0),
                'currency' => $reserveResponse['data']['currency'] ?? 'EUR',
                'raw_request' => $reservePayload,
                'raw_response' => $confirmResponse,
            ];
        } catch (\Exception $e) {
            Log::error('GlobalTix createBooking error', ['message' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function getFullProductDataRaw(string $activityApiId, string $language = 'en', string $currency = 'EUR'): array
    {
        try {
            // Use correct GlobalTix API endpoints via the product service
            $activity = $this->productService->getProductInfo($activityApiId, $language, $currency);
            $activityOptions = $this->productService->getProductOptions($activityApiId);

            // Check full current year: start_date = Jan 1, end_date = Dec 31
            $dateFrom = now()->startOfYear()->toDateString();
            $dateTo   = now()->endOfYear()->toDateString();
            $availability = $this->productService->getActivityAvailability($activityApiId, $dateFrom, $dateTo, $currency);

            return [
                'status' => true,
                'data' => [
                    'activity' => $activity,
                    'activityOptions' => $activityOptions,
                    'availability' => $availability,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('GlobalTix getFullProductDataRaw error', [
                'activity_id' => $activityApiId,
                'message' => $e->getMessage(),
            ]);
            return [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if a GlobalTix product has any valid (non-sold-out) availability.
     * Delegates to GlobalTixProductService::hasAvailability().
     */
    public function hasAvailability(string $activityApiId): bool
    {
        try {
            return $this->productService->hasAvailability($activityApiId);
        } catch (\Exception $e) {
            Log::error("GlobalTix hasAvailability check failed for [{$activityApiId}]: " . $e->getMessage());
            return false;
        }
    }


    public function getCheckoutOptions(array $bookingRequest, string $currency = 'EUR', string $language = 'en'): array
    {
        return [
            'success' => true,
            'paymentMethods' => [
                [
                    'type' => 'RESERVE_FOR_EXTERNAL_PAYMENT',
                    'title' => 'PayPal / Credit Card',
                ]
            ],
            'currency' => $currency,
        ];
    }

    public function submitDirectCheckout(array $payload, string $currency = 'EUR', string $language = 'en'): array
    {
        try {
            $directBooking = $payload['directBooking'] ?? [];
            $items = $directBooking['items'] ?? [];

            if (empty($items)) {
                throw new \Exception('No items provided for booking');
            }

            // Build ticketTypes array per GlobalTix API docs:
            // Each ticket must have visitDate + visitTime inside the entry
            $ticketTypes = [];
            foreach ($items as $index => $item) {
                $ticketTypeId = $item['ticket_type_id'] ?? $item['rateId'] ?? null;
                if (!$ticketTypeId) {
                    throw new \Exception('ticket_type_id is required for GlobalTix booking');
                }

                $visitDate = $item['date'] ?? null;
                if (!$visitDate) {
                    throw new \Exception('visitDate is required for GlobalTix booking');
                }

                $ticketTypes[] = [
                    'id' => (int)$ticketTypeId,
                    'quantity' => (int)($item['quantity'] ?? 1),
                    'visitDate' => $visitDate,
                    'visitTime' => $item['startTime'] ?? $item['visitTime'] ?? '00:00:00',
                    'index' => $index,
                    'questionList' => $item['questionList'] ?? [],
                ];
            }

            // Build the full reservation payload per GlobalTix API docs
            $customer = $directBooking['customer'] ?? $payload['customer'] ?? [];
            $reservePayload = [
                'customerName' => $customer['name'] ?? $customer['customerName'] ?? 'Guest',
                'email' => $customer['email'] ?? '',
                'mobileNumber' => $customer['mobileNumber'] ?? $customer['phone'] ?? '',
                'mobilePrefix' => $customer['mobilePrefix'] ?? '',
                'passportNumber' => $customer['passportNumber'] ?? '',
                'paymentMethod' => $directBooking['paymentMethod'] ?? 'CREDIT',
                'alternateEmail' => $customer['alternateEmail'] ?? '',
                'otherInfo' => [
                    'partnerReference' => $directBooking['partnerReference'] ?? '',
                ],
                'ticketTypes' => $ticketTypes,
                'remarks' => $directBooking['remarks'] ?? '',
            ];

            // Call GlobalTix Reserve API
            $reserveResponse = $this->bookingService->reserveBooking($reservePayload);

            if (!isset($reserveResponse['data']['referenceNumber'])) {
                Log::error('GlobalTix Reserve failed', ['response' => $reserveResponse]);
                throw new \Exception($reserveResponse['message'] ?? $reserveResponse['error'] ?? 'Reserve failed');
            }

            $confirmationCode = $reserveResponse['data']['referenceNumber'];
            $bookingHash = sha1($confirmationCode . time());

            // Build items for consistency with DirectBookingController's expectations
            $resultItems = [];
            foreach ($items as $item) {
                $resultItems[] = [
                    'type'         => 'activity',
                    'activityId'   => $item['activityId'] ?? null,
                    'title'        => $item['productTitle'] ?? 'GlobalTix Activity',
                    'productTitle' => $item['productTitle'] ?? 'GlobalTix Activity',
                    'totalPrice'   => $item['totalPrice'] ?? 0,
                    'date'         => $item['date'] ?? null,
                    'startTime'    => $item['startTime'] ?? null,
                    'quantity'     => $item['quantity'] ?? 1,
                ];
            }

            return [
                'success' => true,
                'bookingHash' => $bookingHash,
                'booking' => [
                    'confirmationCode' => $confirmationCode,
                    'totalPrice'       => $reserveResponse['data']['amount'] ?? ($directBooking['totalPrice'] ?? 0),
                    'totalDue'         => $reserveResponse['data']['amount'] ?? ($directBooking['totalPrice'] ?? 0),
                    'currency'         => $reserveResponse['data']['currency'] ?? $currency,
                    'status'           => $reserveResponse['data']['status'] ?? 'RESERVED',
                    'productBookings'  => $resultItems,
                ],
                'productBookings' => $resultItems,
                'meetingPoint' => [
                    'title' => 'GlobalTix Meeting Point',
                    'address' => 'See voucher for details',
                    'city' => '',
                    'directions' => 'Report at the GlobalTix counter situated at the main entrance.'
                ],
                'redemptionInstructions' => 'Present this digital voucher at the counter for entry.',
                'cancellationPolicy' => 'Cancellation rules as per GlobalTix policy.'
            ];
        } catch (\Exception $e) {
            Log::error('GlobalTix submitDirectCheckout Error', ['message' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function confirmReservedBooking(string $confirmationCode, array $body): array
    {
        // For GlobalTix, we do the actual confirmation in confirmBookingAfterPayment.
        // This method is kept as a success no-op for compatibility with DirectBookingController's flow.
        return ['success' => true];
    }

    public function confirmBookingAfterPayment(string $confirmationCode, array $confirmPayload, bool $sendCustomerNotification, string $currency = 'EUR', string $lang = 'EN'): array
    {
        try {
            // Call GlobalTix Confirm API with remarks
            $remarks = $confirmPayload['remarks'] ?? '';
            $response = $this->bookingService->confirmBooking($confirmationCode, $remarks);

            $success = ($response['success'] ?? false) === true;

            // Map the response using actual GlobalTix confirm response fields
            $data = $response['data'] ?? [];

            return [
                'success' => $success,
                'message' => $success ? 'GlobalTix booking finalized.' : ($response['error'] ?? $response['message'] ?? 'Confirmation failed'),
                'confirmationCode' => $data['referenceNumber'] ?? $confirmationCode,
                'booking' => [
                    'confirmationCode' => $data['referenceNumber'] ?? $confirmationCode,
                    'status'           => $data['status'] ?? ($success ? 'CONFIRMED' : 'FAILED'),
                    'confirmTime'      => $data['confirmTime'] ?? null,
                    'customer'         => $data['customer'] ?? null,
                    'email'            => $data['email'] ?? null,
                    'productBookings'  => data_get($confirmPayload, 'items') ?? [],
                    'meetingPoint' => [
                        'title' => 'GlobalTix Meeting Point',
                        'address' => 'See voucher for details',
                        'city' => '',
                        'directions' => 'Report at the GlobalTix counter situated at the main entrance.'
                    ],
                ],
                'redemptionInstructions' => 'Present this digital voucher at the counter for entry.',
                'raw_response' => $response,
            ];
        } catch (\Exception $e) {
            Log::error('GlobalTix confirmBookingAfterPayment Error', ['message' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function abortReservedBooking(string $confirmationCode): array
    {
        try {
            // Call GlobalTix Release API
            $response = $this->bookingService->releaseBooking($confirmationCode, 'Aborted by user');

            return ['success' => $response['status'] ?? true];
        } catch (\Exception $e) {
            Log::error('GlobalTix abortReservedBooking Error', ['message' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
