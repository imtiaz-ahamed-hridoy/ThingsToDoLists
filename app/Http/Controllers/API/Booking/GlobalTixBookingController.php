<?php

namespace App\Http\Controllers\API\Booking;

use App\Http\Controllers\Controller;
use App\Models\BookingInfo;
use App\Services\Booking\BookingLifecycleService;
use App\Services\CurrencyService;
use App\Services\GlobalTix\GlobalTixBookingService;
use App\Services\GlobalTix\GlobalTixPriceSyncService;
use App\Services\GlobalTix\GlobalTixProductService;
use App\Services\Payments\StripeCheckoutService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class GlobalTixBookingController extends Controller
{
    public function __construct(
        private readonly GlobalTixBookingService $bookingService,
        private readonly StripeCheckoutService $stripeService,
        private readonly BookingLifecycleService $bookingLifecycle,
        private readonly GlobalTixProductService $productService,
    ) {}

    /**
     * Reserve a GlobalTix booking and create a Stripe Checkout Session.
     */
    public function reserveBooking(Request $request)
    {
        $request->validate([
            'alternateEmail'   => 'sometimes|nullable|email',
            'customerName'     => 'sometimes|string|max:255',
            'email'            => 'sometimes|nullable|email',
            'mobileNumber'     => 'sometimes|nullable|string|max:50',
            'mobilePrefix'     => 'sometimes|nullable|string|max:20',
            'passportNumber'   => 'sometimes|nullable|string|max:100',
            'paymentMethod'    => 'sometimes|string|max:50',
            'visitDate'        => 'sometimes|date_format:Y-m-d',
            'ticketTypes'               => 'required|array|min:1',
            'ticketTypes.*.id'          => 'required|integer',
            'ticketTypes.*.quantity'    => 'required|integer|min:1',
            'ticketTypes.*.visitDate'   => 'sometimes|date_format:Y-m-d',
            'ticketTypes.*.visitTime'   => 'sometimes|nullable|string|max:50',
            'ticketTypes.*.index'       => 'sometimes|integer|min:0',
            'ticketTypes.*.questionList'=> 'sometimes|array',
            'otherInfo'                         => 'sometimes|array',
            'otherInfo.partnerReference'        => 'sometimes|nullable|string|max:255',
            'remarks'        => 'sometimes|nullable|string',
            'source'         => 'sometimes|string|max:64',
            'currency'       => 'sometimes|nullable|string',
            'successUrl'     => 'sometimes|url',
            'errorUrl'       => 'sometimes|url',
            'cancelUrl'      => 'sometimes|url',
            'product_id'     => 'sometimes|integer|exists:products,id',
        ]);

        $reference = null;

        try {
            // ── 1. Build GlobalTix payload (strip internal fields) ────────────
            $internalFields = [
                'successUrl', 'errorUrl', 'cancelUrl',
                'success_url', 'error_url', 'cancel_url',
                'source', 'currency', 'product_id',
            ];
            $payload = $this->normalizeReservePayload($request->except($internalFields));

            $bookingContext = $this->bookingLifecycle->contextAttributes($request, 'globaltix', $payload);

            // ── 2. Reserve booking on GlobalTix ───────────────────────────────
            $reserveResponse = $this->bookingService->reserveBooking($payload);

            if (isset($reserveResponse['success']) && $reserveResponse['success'] === false) {
                $errorMsg = data_get($reserveResponse, 'error.message')
                    ?? data_get($reserveResponse, 'message')
                    ?? data_get($reserveResponse, 'error')
                    ?? 'Unknown GlobalTix API error.';
                if (is_array($errorMsg)) {
                    $errorMsg = json_encode($errorMsg);
                }
                throw new \RuntimeException('GlobalTix Error: ' . $errorMsg);
            }

            $reference = (string) data_get($reserveResponse, 'data.referenceNumber', '');
            if ($reference === '') {
                throw new \RuntimeException(
                    'GlobalTix did not return data.referenceNumber. Response: ' . json_encode($reserveResponse)
                );
            }

            $providerAmount = (float) data_get($reserveResponse, 'data.amount', 0);
            if ($providerAmount <= 0) {
                throw new \RuntimeException('GlobalTix reserve did not return a valid payable amount.');
            }

            $providerCurrency = strtoupper(
                (string) data_get($reserveResponse, 'data.currency',
                    app()->bound('user.currency') ? app('user.currency') : $request->input('currency', 'EUR')
                )
            ) ?: 'EUR';

            $source = (string) $request->input('source', 'GLOBALTIX_DIRECT');

            $customer = $this->extractCustomer($payload);

            // Resolve product_id
            if ($request->has('product_id')) {
                $localProduct = \App\Models\Product::find($request->input('product_id'));
                if ($localProduct) {
                    $bookingContext['product_id'] = $localProduct->id;
                    $bookingContext['vendor_id']  = $localProduct->vendor_id;
                }
            }

            $firstTicketId = data_get($payload, 'ticketTypes.0.id');
            if (empty($bookingContext['product_id']) && $firstTicketId) {
                $category = \App\Models\OptionPricingCategory::where('provider', 'globaltix')
                    ->where('external_id', (string) $firstTicketId)
                    ->with('productOption.product')
                    ->first();

                if ($category && $category->productOption && $category->productOption->product) {
                    $lp = $category->productOption->product;
                    $bookingContext['product_id'] = $lp->id;
                    $bookingContext['vendor_id']  = $lp->vendor_id;
                } else {
                    $option = \App\Models\ProductOption::where('provider', 'globaltix')
                        ->where('external_id', (string) $firstTicketId)
                        ->with('product')
                        ->first();

                    if ($option && $option->product) {
                        $lp = $option->product;
                        $bookingContext['product_id'] = $lp->id;
                        $bookingContext['vendor_id']  = $lp->vendor_id;
                    } else {
                        $prod = \App\Models\Product::where('provider', 'globaltix')
                            ->where('product_api_id', (string) $firstTicketId)
                            ->first();
                    }
                }
            }

            if (!empty($bookingContext['product_id'])) {
                $bookingProduct = \App\Models\Product::find($bookingContext['product_id']);
                if ($bookingProduct) {
                    app(GlobalTixPriceSyncService::class)->syncProductLivePrice($bookingProduct);
                }
            }

            // ─── X-Currency Conversion ─────────────────────────────────────────────
            $xCurrency = app()->bound('user.currency') ? app('user.currency') : strtoupper($request->query('currency', 'EUR'));
            $checkoutCurrency = $xCurrency;
            $checkoutAmount = $providerAmount;

            if ($checkoutCurrency !== $providerCurrency) {
                $currencyService = app(CurrencyService::class);
                $checkoutAmount = $currencyService->convert($providerAmount, $providerCurrency, $checkoutCurrency) ?? $providerAmount;
            }

            // ── 3. Create Stripe PaymentIntent ────────────────────────────────
            $paymentIntent = $this->stripeService->createPaymentIntent(
                $reference,
                $checkoutAmount,
                $checkoutCurrency,
                [
                    'provider' => 'globaltix',
                    'source'   => $source,
                ]
            );

            // ── 4. Persist booking row (RESERVED) ─────────────────────────────
            DB::beginTransaction();

            $booking = BookingInfo::where('reference', $reference)
                ->lockForUpdate()
                ->first();

            $providerStatus = strtoupper((string) (data_get($reserveResponse, 'data.status') ?: data_get($reserveResponse, 'status') ?: 'RESERVED'));

            $bookingAttributes = $this->filterBookingInfoAttributes(array_merge($bookingContext, [
                'reference'              => $reference,
                'booking_reference'      => $reference,
                'confirmation_code'      => $reference,
                'provider_type'          => 'globaltix',
                'status'                 => $providerStatus, // Dynamic provider status
                'customer_name'          => $customer['name'],
                'customer_email'         => $customer['email'],
                'customer_phone'         => $customer['phone'],
                'booking_currency'       => $providerCurrency,
                'booking_amount'         => $providerAmount,
                'amount'                 => $checkoutAmount,
                'total_price'            => $checkoutAmount,
                'currency'               => $checkoutCurrency,
                'payment_gateway'        => (string) $request->input('payment_gateway', 'stripe'),
                'payment_source'         => $source,
                'stripe_payment_intent_id' => (string) data_get($paymentIntent, 'id'),
                'provider_raw_data'      => $this->sanitizeProviderPayload($reserveResponse),
            ]));

            if ($booking) {
                $booking->fill($bookingAttributes)->save();
            } else {
                $booking = BookingInfo::create($bookingAttributes);
            }

            $this->syncBookingInfosFromTicketTypes(
                $booking,
                (array) ($payload['ticketTypes'] ?? []),
                $booking->id,
                $providerCurrency
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'data'    => [
                    'status'            => $providerStatus, // Dynamic provider status
                    'reference'         => $reference,
                    'confirmation_code' => $reference,
                    'provider'          => 'globaltix',
                    'customer' => [
                        'name'  => $customer['name'],
                        'email' => $customer['email'],
                        'phone' => $customer['phone'],
                    ],
                    'amount' => [
                        'gateway_amount'    => $checkoutAmount,
                        'gateway_currency'  => $checkoutCurrency,
                    ],
                    'payment' => [
                        'gateway'           => (string) $request->input('payment_gateway', 'stripe'),
                        'client_secret'     => data_get($paymentIntent, 'client_secret'),
                        'payment_intent_id' => data_get($paymentIntent, 'id'),
                        'amount'            => $checkoutAmount,
                        'currency'          => $checkoutCurrency,
                    ],
                    'tickets'           => array_map(static fn($t) => [
                        'id'        => data_get($t, 'id'),
                        'quantity'  => data_get($t, 'quantity'),
                        'visitDate' => data_get($t, 'visitDate'),
                        'visitTime' => data_get($t, 'visitTime') ?: '00:00:00',
                    ], (array) ($payload['ticketTypes'] ?? [])),
                    'provider_response' => $reserveResponse,
                ],
                'error' => null,
                'size'  => null,
            ], 200);

        } catch (\Throwable $e) {
            $this->safeRollback();
            Log::error('GlobalTix reserve booking failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($reference) {
                $this->bookingLifecycle->markFailure($reference, 'globaltix_reserve', $e->getMessage(), (string) $e->getCode());
            } else {
                $this->persistFailedReserveBooking($request, $e);
            }

            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Confirm a GlobalTix booking and sync the full provider state to the DB.
     *
     * This is an INTERNAL helper — not exposed via any public route.
     * It is called exclusively by:
     *   - The Stripe webhook (StripeWebhookController::processGlobalTixCheckoutCompleted)
     *     after Stripe captures payment.
     *   - The admin retry flow if a webhook delivery failed.
     *
     * The confirmation flow is:
     *   1. Call GlobalTix POST /api/booking/confirm
     *   2. Fetch full booking details (GET /api/booking/details) for snapshot + tickets
     *   3. Update parent BookingInfo row (CONFIRMED, confirmed_at, e_ticket_url, provider snapshot)
     *   4. Sync child product row + child ticket rows from provider response
     *
     * @internal
     */
    public function confirmAfterPayment(
        string $reference,
        string $remarks = 'Payment confirmed via external gateway.'
    ): array {
        // ── 1. Call GlobalTix /api/booking/confirm ───────────────────────────
        $confirmResponse = $this->bookingService->confirmBooking($reference, $remarks);
        $confirmSuccess  = (bool) data_get($confirmResponse, 'success', false);

        // ── 2. Fetch full booking details for snapshot + tickets ─────────────
        $detailsResponse = [];
        try {
            $detailsResponse = $this->bookingService->getBookingDetails($reference);
        } catch (\Throwable $detailsError) {
            Log::warning('GlobalTix confirmAfterPayment: getBookingDetails failed (non-fatal)', [
                'reference' => $reference,
                'error'     => $detailsError->getMessage(),
            ]);
        }

        $detailsSuccess = (bool) data_get($detailsResponse, 'success', false);
        $detailsStatus  = strtoupper((string) data_get($detailsResponse, 'data.status', ''));

        if (!$confirmSuccess && !($detailsSuccess && in_array($detailsStatus, ['CONFIRMED', 'READY', 'REDEEMED'], true))) {
            throw new \RuntimeException(
                (string) (data_get($confirmResponse, 'error') ?: data_get($confirmResponse, 'message') ?: 'GlobalTix confirmation failed.')
            );
        }

        // Prefer full details snapshot; fall back to confirm response
        $providerSnapshot = $detailsSuccess ? $detailsResponse : $confirmResponse;
        $providerSnapshot = $this->sanitizeProviderPayload((array) $providerSnapshot);

        $providerStatus = strtoupper((string) data_get($providerSnapshot, 'data.status', 'CONFIRMED'));
        if ($providerStatus === '') {
            $providerStatus = 'CONFIRMED';
        }

        $eTicketUrl = (string) data_get($providerSnapshot, 'data.eTicketUrl', '');
        $tickets    = data_get($providerSnapshot, 'data.tickets');

        // ── 3. Persist CONFIRMED state to parent row ─────────────────────────
        DB::beginTransaction();
        try {
            $payment = $this->paymentByReferenceQuery($reference)->lockForUpdate()->first();

            if (!$payment) {
                DB::rollBack();
                Log::error('GlobalTix confirmAfterPayment: parent row not found', ['reference' => $reference]);
                return ['success' => false, 'error' => 'Booking record not found.'];
            }

            // Idempotency — never overwrite a CONFIRMED row
            if ($payment->status === 'CONFIRMED') {
                DB::rollBack();
                return ['success' => true, 'already_confirmed' => true, 'status' => 'CONFIRMED'];
            }

            $payment->update($this->filterBookingInfoAttributes([
                'status'                => $providerStatus,
                'confirmed_at'          => now(),
                'paid_at'               => $payment->paid_at ?? now(),
                'bokun_confirm_response'=> $providerSnapshot,
                'provider_raw_data'     => $providerSnapshot,
                'e_ticket_url'          => $eTicketUrl ?: $payment->e_ticket_url,
            ]));

            // ── 4. Sync child product row ────────────────────────────────────
            $product = $this->productByReferenceQuery($reference)->first();
            if ($product) {
                $product->fill([
                    'status'            => $providerStatus,
                    'provider_type'     => 'globaltix',
                    'provider_raw_data' => $providerSnapshot,
                ]);

                if ($eTicketUrl !== '') {
                    $product->e_ticket_url = $eTicketUrl;
                }

                if (is_array($tickets)) {
                    $product->globaltix_tickets = $tickets;
                }

                $product->save();

                // Sync child ticket rows
                if (is_array($tickets) && !empty($tickets)) {
                    $currency = strtoupper((string) ($payment->booking_currency ?: $product->currency ?: 'EUR'));
                    $this->syncBookingInfosFromTickets($product, $tickets, $payment->id, $currency);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            $this->safeRollback();
            throw $e;
        }

        Log::info('GlobalTix confirmAfterPayment: confirmed successfully', [
            'reference' => $reference,
            'status'    => $providerStatus,
        ]);

        return [
            'success'           => true,
            'reference'         => $reference,
            'confirmation_code' => $reference,
            'provider'          => 'globaltix',
            'status'            => $providerStatus,
            'e_ticket_url'      => $eTicketUrl ?: null,
            'provider_response' => $providerSnapshot,
        ];
    }

    /**
     * Release a booking
     */
    public function releaseBooking(Request $request)
    {
        $request->validate([
            'referenceNumber' => 'required|string',
            'remarks' => 'nullable|string',
        ]);

        try {
            $reference = (string) $request->input('referenceNumber');
            $response = $this->bookingService->releaseBooking(
                $reference,
                $request->input('remarks', '')
            );

            if ((bool) data_get($response, 'success', false)) {
                $this->paymentByReferenceQuery($reference)
                    ->where('status', '!=', 'CONFIRMED')
                    ->update($this->filterBookingInfoAttributes([
                        'status' => 'RELEASED',
                    ]));

                $this->productByReferenceQuery($reference)
                    ->where('status', '!=', 'CONFIRMED')
                    ->update([
                        'status' => 'RELEASED',
                    ]);
            } else {
                $errorCode = data_get($response, 'error.code');
                $originalMessage = data_get($response, 'error.message');

                $friendlyMessage = 'Failed to release the booking.';

                if ($errorCode === 'error.transaction.payment.not.pending') {
                    $friendlyMessage = 'This booking cannot be released or cancelled because it has already been confirmed or processed.';
                } elseif (!empty($originalMessage)) {
                    $friendlyMessage = $originalMessage;
                } elseif (!empty($errorCode)) {
                    $friendlyMessage = 'GlobalTix Error: ' . $errorCode;
                }

                return response()->json([
                    'success' => false,
                    'error' => $friendlyMessage,
                    'error_code' => $errorCode,
                    'provider_response' => $response
                ], 400);
            }

            return response()->json($response);
        } catch (\Throwable $e) {
            Log::error('GlobalTix Release Booking Controller Error', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get booking details
     */
    public function getBookingInfos(Request $request)
    {
        $request->validate([
            'referenceNumber' => 'required|string',
            'partnerReferenceNumber' => 'nullable|string',
        ]);

        try {
            $reference = (string) $request->query('referenceNumber');
            $response = $this->bookingService->getBookingDetails(
                $reference,
                $request->query('partnerReferenceNumber')
            );

            if ((bool) data_get($response, 'success', false)) {
                $this->syncLocalBookingFromDetails($reference, $response);
            }

            return response()->json($response);
        } catch (\Throwable $e) {
            Log::error('GlobalTix Booking Details Controller Error', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Show local booking status + latest provider snapshot.
     */
    public function show(string $reference)
    {
        try {
            $payment = $this->paymentByReferenceQuery($reference)->firstOrFail();
            $product = $this->productByReferenceQuery((string) $payment->reference)->first();

            $providerResponse = $payment->bokun_confirm_response
                ?? data_get($product, 'provider_raw_data')
                ?? $payment->bokun_submit_response;

            return response()->json([
                'status' => $payment->status,
                'reference' => $payment->reference,
                'confirmation_code' => $payment->confirmation_code,
                'provider' => 'globaltix',
                'provider_response' => $providerResponse,
                'e_ticket_url' => data_get($product, 'e_ticket_url'),
                'tickets' => data_get($product, 'globaltix_tickets'),
                'paid_at' => $payment->paid_at,
                'confirmed_at' => $payment->confirmed_at,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('GlobalTix booking show failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Booking not found.',
            ], 404);
        }
    }

    /**
     * @deprecated Stripe webhook is no longer used — direct card payment replaced it.
     */
    public function stripeWebhook(Request $request)
    {
        Log::info('GlobalTix stripeWebhook hit (deprecated) — no action taken.');
        return response()->json(['status' => 'deprecated', 'message' => 'Stripe webhook is no longer used.'], 200);
    }

    private function normalizeReservePayload(array $payload): array
    {
        // Only these fields are accepted by the GlobalTix reserve API.
        // All internal/payment fields must be stripped before sending.
        $globaltixAllowedFields = [
            'customerName', 'email', 'alternateEmail',
            'mobileNumber', 'mobilePrefix', 'passportNumber',
            'paymentMethod', 'ticketTypes', 'remarks', 'otherInfo',
        ];
        $payload = array_intersect_key($payload, array_flip($globaltixAllowedFields));

        // Default payment method to CREDIT (agency/API credit)
        if (empty($payload['paymentMethod'])) {
            $payload['paymentMethod'] = 'CREDIT';
        }

        $ticketTypes = (array) ($payload['ticketTypes'] ?? []);

        foreach ($ticketTypes as $index => $ticketType) {
            if (!is_array($ticketType)) {
                continue;
            }

            // Ensure id is integer
            if (!empty($ticketType['id'])) {
                $ticketType['id'] = (int) $ticketType['id'];
            }

            // GlobalTix expects visitTime in HH:MM:SS (24-hour) format
            $ticketType['visitTime'] = $this->normalizeVisitTime(
                data_get($ticketType, 'visitTime')
            );

            $ticketType['index'] = array_key_exists('index', $ticketType)
                ? (int) $ticketType['index']
                : $index;

            $ticketType['questionList'] = (array) ($ticketType['questionList'] ?? []);

            // Only send ticket-level fields GlobalTix understands
            $ticketTypes[$index] = array_intersect_key($ticketType, array_flip([
                'id', 'quantity', 'visitDate', 'visitTime', 'index', 'questionList',
            ]));
        }

        $payload['ticketTypes'] = $ticketTypes;

        return $payload;
    }

    private function normalizeVisitTime(mixed $visitTime): string
    {
        if ($visitTime === null || trim((string) $visitTime) === '') {
            return '00:00:00';
        }

        $visitTime = trim((string) $visitTime);

        // Already HH:MM:SS — return as-is
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $visitTime) === 1) {
            return $visitTime;
        }

        // Already HH:MM (24-hour, no seconds) — append seconds
        if (preg_match('/^\d{2}:\d{2}$/', $visitTime) === 1) {
            return $visitTime . ':00';
        }

        // Handle all 12-hour AM/PM variants:
        // "10:00 AM", "10:00AM", "10:00 am", "10:00am"
        // "1:30 PM", "1:30PM", "01:30pm", etc.
        if (preg_match('/^(\d{1,2}):(\d{2})\s*(am|pm)$/i', $visitTime, $matches)) {
            $hour   = (int) $matches[1];
            $minute = (int) $matches[2];
            $period = strtolower($matches[3]);

            if ($period === 'pm' && $hour !== 12) {
                $hour += 12;
            } elseif ($period === 'am' && $hour === 12) {
                $hour = 0;
            }

            return sprintf('%02d:%02d:00', $hour, $minute);
        }

        // Fallback: let Carbon handle any other format
        try {
            return Carbon::parse($visitTime)->format('H:i:s');
        } catch (\Throwable) {
            return '00:00:00';
        }
    }

    private function buildStripeReturnUrls(Request $request, string $reference): array
    {
        $successUrl = (string) ($request->input('successUrl') ?: $request->input('success_url') ?: '');
        $cancelUrl = (string) ($request->input('cancelUrl') ?: $request->input('cancel_url') ?: '');

        if ($successUrl === '' || $cancelUrl === '') {
            $frontendBaseUrl = rtrim((string) config('app.frontend_url', ''), '/');
            if ($frontendBaseUrl === '') {
                throw new \RuntimeException('Provide successUrl/cancelUrl or configure FRONTEND_URL.');
            }

            if ($successUrl === '') {
                $successUrl = $frontendBaseUrl . '/globaltix/payment/success';
            }
            if ($cancelUrl === '') {
                $cancelUrl = $frontendBaseUrl . '/globaltix/payment/cancel';
            }
        }

        $successUrl = $this->appendQuery($successUrl, [
            'reference' => $reference,
            'session_id' => '{CHECKOUT_SESSION_ID}',
        ]);
        $cancelUrl = $this->appendQuery($cancelUrl, [
            'reference' => $reference,
        ]);

        return [
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ];
    }

    private function appendQuery(string $url, array $query): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';
        $queryString = http_build_query($query);
        $queryString = str_replace('%7BCHECKOUT_SESSION_ID%7D', '{CHECKOUT_SESSION_ID}', $queryString);

        return $url . $separator . $queryString;
    }

    private function extractCustomer(array $payload): array
    {
        return [
            'name' => (string) ($payload['customerName'] ?? 'N/A'),
            'email' => (string) ($payload['email'] ?? ''),
            'phone' => (string) ($payload['mobileNumber'] ?? ''),
        ];
    }

    private function persistFailedReserveBooking(Request $request, \Throwable $e): void
    {
        try {
            $reference = 'FAILED-' . now()->format('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $payload = $this->normalizeReservePayload($request->all());
            $customer = $this->extractCustomer($payload);
            $context = $this->bookingLifecycle->contextAttributes($request, 'globaltix', $payload);

            $attributes = $this->bookingLifecycle->filterBookingColumns(array_merge($context, [
                'reference' => $reference,
                'booking_reference' => $reference,
                'confirmation_code' => $reference,
                'provider_type' => 'globaltix',
                'customer_name' => $customer['name'],
                'customer_email' => $customer['email'],
                'customer_phone' => $customer['phone'],
                'status' => 'FAILED',
                'payment_gateway' => (string) $request->input('payment_gateway', 'stripe'),
                'payment_source' => (string) ($request->input('source', 'GLOBALTIX_DIRECT')),
                'failure_stage' => 'globaltix_reserve',
                'failure_reason' => $e->getMessage(),
                'failure_code' => (string) $e->getCode(),
                'failed_at' => now(),
                'admin_status' => 'needs_attention',
            ]));

            $payment = BookingInfo::create($attributes);
            $booking = BookingInfo::whereKey($payment->id)->first();
            if ($booking) {
                $this->bookingLifecycle->recordEvent($booking, 'booking_failed', [
                    'new_status' => 'FAILED',
                    'message' => $e->getMessage(),
                    'payload' => ['stage' => 'globaltix_reserve'],
                ]);
            }
        } catch (\Throwable $inner) {
            Log::error('Failed to persist failed GlobalTix reservation', ['error' => $inner->getMessage()]);
        }
    }

    private function syncBookingInfosFromTicketTypes(BookingInfo $product, array $ticketTypes, ?int $paymentId, string $currency): void
    {
        if (!Schema::hasTable((new BookingInfo())->getTable())) {
            return;
        }

        if (empty($ticketTypes)) {
            return;
        }

        $product->details()->delete();

        foreach ($ticketTypes as $item) {
            if (!is_array($item)) {
                continue;
            }

            $visitDate = (string) data_get($item, 'visitDate', '');
            if ($visitDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $visitDate) !== 1) {
                $visitDate = '';
            }

            $visitTime = $this->normalizeVisitTime(data_get($item, 'visitTime'));
            $ticketId = (string) data_get($item, 'id');

            // Attempt to find real ticket name and price
            $ticketTitle = 'GlobalTix Ticket #' . ($ticketId ?: 'N/A');
            $ticketPrice = 0.0;

            if ($ticketId !== '') {
                $category = \App\Models\OptionPricingCategory::where('provider', 'globaltix')
                    ->where('external_id', $ticketId)
                    ->first();

                if ($category) {
                    $ticketTitle = (string) ($category->category_label ?: $ticketTitle);
                    $ticketPrice = (float) ($category->selling_price ?? 0.0);
                } else {
                    $option = \App\Models\ProductOption::where('provider', 'globaltix')
                        ->where('external_id', $ticketId)
                        ->first();

                    if ($option) {
                        $ticketTitle = (string) ($option->name ?: $ticketTitle);
                        // Options don't have a direct selling_price, so leave at 0 or fetch first category price
                        $firstCat = $option->pricingCategories()->first();
                        if ($firstCat) {
                            $ticketPrice = (float) ($firstCat->selling_price ?? 0.0);
                        }
                    } else {
                        $prod = \App\Models\Product::where('provider', 'globaltix')
                            ->where('product_api_id', $ticketId)
                            ->first();

                        if ($prod) {
                            $ticketTitle = (string) ($prod->title ?: $ticketTitle);
                            $ticketPrice = (float) ($prod->from_price ?? 0.0);
                        }
                    }
                }
            }

            BookingInfo::create([
                'booking_product_id' => $product->id,
                'booking_payment_id' => $paymentId,
                'user_id' => $product->user_id,
                'product_id' => $product->product_id,
                'vendor_id' => $product->vendor_id,
                'product_booking_id' => $ticketId,
                'provider_type' => 'globaltix',
                'status' => $product->status,
                'type' => 'ticket',
                'title' => $ticketTitle,
                'price' => $ticketPrice,
                'currency' => $currency,
                'date' => $visitDate !== '' ? $visitDate : null,
                'start_time' => $visitTime,
                'quantity' => (int) data_get($item, 'quantity', 1),
                'provider_raw_data' => $item,
            ]);
        }
    }

    private function syncBookingInfosFromTickets(BookingInfo $product, array $tickets, ?int $paymentId, string $currency): void
    {
        if (!Schema::hasTable((new BookingInfo())->getTable())) {
            return;
        }

        if (empty($tickets)) {
            return;
        }

        $product->details()->delete();

        foreach ($tickets as $ticket) {
            if (!is_array($ticket)) {
                continue;
            }

            $eventTime = (string) data_get($ticket, 'eventTime', '');
            $date = null;
            $time = null;

            if ($eventTime !== '') {
                try {
                    $eventDateTime = Carbon::parse($eventTime);
                    $date = $eventDateTime->format('Y-m-d');
                    $time = $eventDateTime->format('H:i:s');
                } catch (\Throwable) {
                    $date = null;
                    $time = null;
                }
            }

            BookingInfo::create([
                'booking_product_id' => $product->id,
                'booking_payment_id' => $paymentId,
                'user_id' => $product->user_id,
                'product_id' => $product->product_id,
                'vendor_id' => $product->vendor_id,
                'booking_id' => (string) data_get($ticket, 'transaction.referenceNumber', ''),
                'product_booking_id' => (string) data_get($ticket, 'product.id', ''),
                'product_confirmation_code' => (string) data_get($ticket, 'code', ''),
                'provider_type' => 'globaltix',
                'status' => $product->status,
                'type' => 'ticket',
                'title' => (string) data_get($ticket, 'name', 'GlobalTix Ticket'),
                'price' => (float) data_get($ticket, 'paidAmount', data_get($ticket, 'checkoutPrice', 0)),
                'currency' => $currency,
                'date' => $date,
                'start_time' => $time,
                'quantity' => (int) data_get($ticket, 'quantity_total', 1),
                'provider_raw_data' => $ticket,
            ]);
        }
    }

    private function syncLocalBookingFromDetails(string $reference, array $detailsResponse): void
    {
        $snapshot = $this->sanitizeProviderPayload($detailsResponse);
        $status = strtoupper((string) data_get($snapshot, 'data.status', ''));
        $amount = (float) data_get($snapshot, 'data.amount', 0);
        $currency = strtoupper((string) data_get($snapshot, 'data.currency', ''));
        $eTicketUrl = (string) data_get($snapshot, 'data.eTicketUrl', '');
        $tickets = data_get($snapshot, 'data.tickets');

        $payment = $this->paymentByReferenceQuery($reference)->first();
        if ($payment) {
            $paymentUpdates = [];

            if ($status !== '') {
                $paymentUpdates['status'] = $status;
                if ($status === 'CONFIRMED') {
                    $paymentUpdates['confirmed_at'] = $payment->confirmed_at ?: now();
                }
            }

            if ($amount > 0) {
                $paymentUpdates['booking_amount'] = $amount;
                $paymentUpdates['amount'] = round($amount, 2);
            }

            if ($currency !== '') {
                $paymentUpdates['booking_currency'] = $currency;
            }

            if (!empty($paymentUpdates)) {
                $payment->update($this->filterBookingInfoAttributes($paymentUpdates));
            }
        }

        $product = $this->productByReferenceQuery($reference)->first();
        if ($product) {
            $product->fill([
                'provider_type' => 'globaltix',
                'provider_raw_data' => $snapshot,
            ]);

            if ($status !== '') {
                $product->status = $status;
            }

            if ($amount > 0) {
                $product->total_price = $amount;
            }

            if ($currency !== '') {
                $product->currency = $currency;
            }

            if ($eTicketUrl !== '') {
                $product->e_ticket_url = $eTicketUrl;
            }

            if (is_array($tickets)) {
                $product->globaltix_tickets = $tickets;
            }

            $product->save();

            if (is_array($tickets) && !empty($tickets)) {
                $paymentId = $payment?->id;
                $this->syncBookingInfosFromTickets($product, $tickets, $paymentId, $currency !== '' ? $currency : (string) $product->currency);
            }
        }
    }

    private function sanitizeProviderPayload(array $payload): array
    {
        array_walk_recursive($payload, static function (&$value, $key): void {
            if (!is_string($value)) {
                return;
            }

            $lowerKey = strtolower((string) $key);

            if ($lowerKey === 'qrcode' && strlen($value) > 2048) {
                $value = '[TRUNCATED_QRCODE_BASE64]';
                return;
            }

            if (strlen($value) > 10000) {
                $value = substr($value, 0, 10000) . '...[TRUNCATED]';
            }
        });

        return $payload;
    }

    private function paymentByReferenceQuery(string $reference): EloquentBuilder
    {
        return BookingInfo::where(function (EloquentBuilder $query) use ($reference): void {
            $query->where('reference', $reference)
                ->orWhere('booking_reference', $reference);
        });
    }

    private function productByReferenceQuery(string $reference): EloquentBuilder
    {
        return BookingInfo::where(function (EloquentBuilder $query) use ($reference): void {
            $query->where('booking_reference', $reference)
                ->orWhere('reference', $reference);
        });
    }

    private function filterBookingInfoAttributes(array $attributes): array
    {
        return array_intersect_key($attributes, $this->bookingPaymentColumns());
    }

    private function bookingPaymentsHasColumn(string $column): bool
    {
        return array_key_exists($column, $this->bookingPaymentColumns());
    }

    private function bookingPaymentColumns(): array
    {
        static $columns = null;

        if ($columns === null) {
            $model = new BookingInfo();
            $listing = $model->getConnection()->getSchemaBuilder()->getColumnListing($model->getTable());
            $columns = array_fill_keys($listing, true);
        }

        return $columns;
    }

    private function safeRollback(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
}
