<?php

namespace App\Http\Controllers\API\Booking;

use App\Http\Controllers\Controller;
use App\Models\BookingInfo;
use App\Services\Booking\BookingLifecycleService;
use App\Services\Bokun\BokunService;
use App\Services\Payments\StripeCheckoutService;
use App\Traits\AllTraits;
use App\Traits\ApiResponse;
use App\Traits\HasCurrency;
use App\Traits\HasLanguage;
use App\Traits\SyncBookingInfosTrait;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BokunBookingController extends Controller
{
    use AllTraits;
    use ApiResponse;
    use SyncBookingInfosTrait;
    use HasCurrency, HasLanguage;

    private const DEFAULT_LANG = 'EN';

    public function __construct(
        private readonly BokunService $bokunService,
        private readonly StripeCheckoutService $stripeService,
        private readonly BookingLifecycleService $bookingLifecycle,
    ) {}

    public function getCheckoutOptions(Request $request)
    {
        $request->validate([
            'currency' => 'sometimes|nullable|string',
            'lang' => 'sometimes|string|max:8',
        ]);

        $currency = $this->resolveCurrency();
        $lang = strtoupper($this->resolveLanguage());
        $bookingRequest = $this->resolveCheckoutOptionsBookingRequest($request);
        $bookingRequest['currency'] = $currency;

        try {
            $result = $this->bokunService->getCheckoutOptions(
                $bookingRequest,
                $currency,
                $lang
            );

            return $this->success('Checkout options retrieved successfully', [
                'requested' => ['currency' => $currency, 'lang' => $lang],
                'provider' => 'bokun',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Bokun checkout options failed', ['error' => $e->getMessage()]);
            return $this->error('Failed to retrieve checkout options: ' . $e->getMessage(), 400);
        }
    }

    public function submitCheckout(Request $request)
    {
        $currency   = $this->resolveCurrency();
        $lang       = strtoupper($this->resolveLanguage());
        $reference  = null;

        try {
            // ── 1. Normalize Bokun payload ────────────────────────────────────
            $normalized    = $this->normalizeCheckoutPayload($request->all(), $currency);
            $bokunPayload  = $normalized['payload'];
            $directBooking = $normalized['direct_booking'];

            $contextArgs = ['directBooking' => $directBooking];

            if ($request->has('product_id')) {
                $localProduct = \App\Models\Product::find($request->input('product_id'));
                if ($localProduct) {
                    $contextArgs['product_id'] = $localProduct->id;
                    $contextArgs['vendor_id']  = $localProduct->vendor_id;
                }
            }

            $bookingContext = $this->bookingLifecycle->contextAttributes($request, 'bokun', $contextArgs);

            // ── 2. Reserve booking on Bokun ───────────────────────────────────
            $providerResponse = $this->bokunService->submitDirectCheckout($bokunPayload, $currency, $lang);

            $confirmationCode = (string) data_get($providerResponse, 'booking.confirmationCode', '');
            $bookingHash      = (string) data_get($providerResponse, 'bookingHash', '');

            if ($confirmationCode === '') {
                throw new \RuntimeException('Bokun did not return booking.confirmationCode.');
            }

            $reference = $bookingHash !== '' ? $bookingHash : $confirmationCode;

            $providerAmount = (float) data_get(
                $providerResponse,
                'booking.totalDue',
                data_get($providerResponse, 'booking.totalPrice', 0)
            );
            if ($providerAmount <= 0) {
                throw new \RuntimeException('Could not determine payable amount from Bokun response.');
            }

            $providerCurrency = strtoupper((string) (
                data_get($providerResponse, 'booking.currency')
                ?: data_get($providerResponse, 'booking.invoice.currency')
                ?: $currency
            ));

            // ── 3. Create Stripe PaymentIntent ────────────────────────────────
            $paymentIntent = $this->stripeService->createPaymentIntent(
                $reference,
                $providerAmount,
                $providerCurrency,
                [
                    'confirmation_code' => $confirmationCode,
                    'provider'          => 'bokun',
                    'source'            => (string) $normalized['source'],
                ]
            );

            // ── 4. Extract provider metadata ──────────────────────────────────
            $primaryProductBooking = (array) (
                data_get($providerResponse, 'booking.productBookings.0')
                ?? data_get($providerResponse, 'booking.bookedProducts.0')
                ?? data_get($providerResponse, 'productBookings.0')
                ?? []
            );

            $productConfirmationCode = (string) (
                data_get($primaryProductBooking, 'productConfirmationCode')
                ?: data_get($providerResponse, 'travelDocuments.activityTickets.0.productConfirmationCode')
                ?: ''
            );

            $productBookingId = data_get($primaryProductBooking, 'bookingId')
                ?? data_get($primaryProductBooking, 'activityBookingId');

            $bookingId = data_get($providerResponse, 'booking.bookingId')
                ?? data_get($primaryProductBooking, 'parentBookingId')
                ?? data_get($providerResponse, 'travelDocuments.activityTickets.0.bookingId');

            $items     = data_get($providerResponse, 'booking.activityBookings')
                ?? data_get($providerResponse, 'booking.productBookings')
                ?? data_get($providerResponse, 'booking.bookedProducts')
                ?? [];
            $firstItem = !empty($items) && is_array($items) ? reset($items) : [];

            $title     = data_get($firstItem, 'title') ?? data_get($firstItem, 'productTitle');
            $type      = data_get($firstItem, 'type') ?? 'activity';
            $quantity  = (int) (data_get($firstItem, 'quantity') ?? 1);
            $startTime = data_get($firstItem, 'startTime');

            $activityId    = data_get($firstItem, 'activityId') ?? data_get($primaryProductBooking, 'activityId');
            $localProductId = null;
            if ($activityId) {
                $lp = \App\Models\Product::where('product_api_id', (string) $activityId)
                    ->where(function ($q) { $q->where('provider', 'bokun')->orWhere('platform', 'bokun'); })
                    ->first();
                $localProductId = $lp?->id;
            }

            $rawDate    = data_get($firstItem, 'date');
            $parsedDate = null;
            if ($rawDate) {
                if (is_numeric($rawDate)) {
                    $parsedDate = strlen((string) $rawDate) === 13
                        ? \Carbon\Carbon::createFromTimestampMs($rawDate)->format('Y-m-d H:i:s')
                        : \Carbon\Carbon::createFromTimestamp($rawDate)->format('Y-m-d H:i:s');
                } else {
                    try { $parsedDate = \Carbon\Carbon::parse($rawDate)->format('Y-m-d H:i:s'); } catch (\Throwable $ignored) {}
                }
            }

            $customer = $this->extractCustomer($directBooking);

            // ── 5. Persist booking row (RESERVED) ──────────────────────────────
            DB::beginTransaction();

            $providerStatus = strtoupper((string) (data_get($providerResponse, 'booking.status') ?: data_get($providerResponse, 'status') ?: 'RESERVED'));

            $booking = BookingInfo::updateOrCreate(
                ['reference' => $reference],
                $this->filterBookingInfoAttributes(array_merge($bookingContext, [
                    'booking_reference'          => $reference,
                    'confirmation_code'          => $confirmationCode,
                    'booking_id'                 => $bookingId !== null ? (string) $bookingId : null,
                    'product_booking_id'         => $productBookingId !== null ? (string) $productBookingId : null,
                    'product_confirmation_code'  => $productConfirmationCode !== '' ? $productConfirmationCode : null,
                    'provider_type'              => 'bokun',
                    'product_id'                 => $localProductId ?? data_get($bookingContext, 'product_id'),

                    // Customer
                    'customer_name'   => $customer['name'],
                    'customer_email'  => $customer['email'],
                    'customer_phone'  => $customer['phone'],

                    // Payment
                    'booking_currency'          => $providerCurrency,
                    'booking_amount'            => $providerAmount,
                    'amount'                    => round($providerAmount, 2),
                    'total_price'               => $providerAmount,
                    'currency'                  => $providerCurrency,
                    'payment_gateway'           => (string) $request->input('payment_gateway', 'stripe'),
                    'payment_source'            => (string) $normalized['source'],
                    'stripe_payment_intent_id'  => (string) data_get($paymentIntent, 'id'),
                    'status'                    => $providerStatus, // Dynamic provider status

                    // Product specifics
                    'title'      => $title,
                    'type'       => $type,
                    'date'       => $parsedDate,
                    'start_time' => $startTime,
                    'quantity'   => $quantity,

                    'bokun_submit_response' => $providerResponse,
                    'provider_raw_data'     => $providerResponse,
                ]))
            );

            DB::commit();

            return response()->json([
                'status'              => $providerStatus, // Dynamic provider status
                'reference'           => $reference,
                'confirmation_code'   => $confirmationCode,
                'confirmationCode'    => $confirmationCode,
                'bookingId'           => $bookingId,
                'productBookingId'    => $productBookingId,
                'productConfirmationCode' => $productConfirmationCode,
                'provider'            => 'bokun',
                'provider_amount'     => $providerAmount,
                'provider_currency'   => $providerCurrency,
                'payment' => [
                    'gateway'        => (string) $request->input('payment_gateway', 'stripe'),
                    'client_secret'  => data_get($paymentIntent, 'client_secret'),
                    'payment_intent_id' => data_get($paymentIntent, 'id'),
                    'amount'         => round($providerAmount, 2),
                    'currency'       => $providerCurrency,
                ],
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Bokun submit checkout failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($reference) {
                $this->bookingLifecycle->markFailure($reference, 'bokun_submit_checkout', $e->getMessage(), (string) $e->getCode());
            } else {
                $this->persistFailedSubmitCheckout($request, 'bokun', $e);
            }

            return $this->error('Submit checkout failed: ' . $e->getMessage(), 400);
        }
    }


    /**
     * @deprecated Stripe webhook is no longer used — direct card payment replaced it.
     *             Kept alive to avoid breaking existing Stripe dashboard webhook config.
     */
    public function stripeWebhook(Request $request)
    {
        // Direct card payment flow handles confirmation synchronously.
        // This webhook is no longer part of the payment lifecycle.
        Log::info('Bokun stripeWebhook hit (deprecated) — no action taken.');
        return response()->json(['status' => 'deprecated', 'message' => 'Stripe webhook is no longer used.'], 200);
    }

    /** @deprecated — kept as internal reference, not called in new flow */
    private function _stripeWebhookLegacy(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        try {
            $event = $this->stripeService->constructWebhookEvent($payload, $signature);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook verification failed', ['error' => $e->getMessage()]);
            return $this->error('Invalid webhook signature', 401);
        }

        $eventType = (string) data_get($event, 'type', '');
        if ($eventType !== 'checkout.session.completed') {
            return response()->json(['status' => 'received', 'event_type' => $eventType], 200);
        }

        $eventId = (string) data_get($event, 'id', '');
        $session = (array) data_get($event, 'data.object', []);
        $reference = (string) data_get($session, 'metadata.reference', '');
        $sessionId = (string) data_get($session, 'id', '');
        $paymentIntentId = (string) data_get($session, 'payment_intent', '');

        if ($reference === '') {
            return $this->error('Missing reference in Stripe metadata', 400);
        }

        DB::beginTransaction();

        try {
            $paymentQuery = BookingInfo::where('reference', $reference);
            if ($sessionId !== '' && $this->bookingPaymentsHasColumn('stripe_session_id')) {
                $paymentQuery->orWhere('stripe_session_id', $sessionId);
            }

            $payment = $paymentQuery->lockForUpdate()->first();

            if (!$payment) {
                DB::rollBack();
                Log::error('Stripe webhook booking reference not found', ['reference' => $reference]);
                return response()->json(['status' => 'ignored_not_found'], 200);
            }

            if ($payment->status === 'CONFIRMED' || ($eventId !== '' && $payment->stripe_last_webhook_event_id === $eventId)) {
                DB::rollBack();
                return response()->json(['status' => 'already_processed'], 200);
            }

            $confirmationCode = (string) $payment->confirmation_code;
            if ($confirmationCode === '') {
                DB::rollBack();
                return $this->error('Missing confirmation code for booking', 422);
            }

            $bookingCurrency = strtoupper((string) $payment->booking_currency);
            $bookingAmount = (float) $payment->booking_amount;
            if ($bookingCurrency === '' || $bookingAmount <= 0) {
                DB::rollBack();
                return $this->error('Missing booking amount/currency', 422);
            }

            $payment->update($this->filterBookingInfoAttributes([
                'status' => 'PAID',
                'paid_at' => now(),
                'payment_gateway' => 'stripe',
                'stripe_session_id' => $sessionId !== '' ? $sessionId : $payment->stripe_session_id,
                'stripe_payment_intent_id' => $paymentIntentId !== '' ? $paymentIntentId : $payment->stripe_payment_intent_id,
            ]));

            $confirmReservedPayload = [
                'externalBookingReference' => (string) $payment->reference,
                'externalBookingEntityName' => config('app.name', 'Royalloge'),
                'externalBookingEntityCode' => config('app.name', 'royalloge'),
                'amount' => $bookingAmount,
                'currency' => $bookingCurrency,
                'transactionDetails' => [
                    'transactionDate' => now()->toIso8601String(),
                    'transactionId' => $paymentIntentId !== '' ? $paymentIntentId : $sessionId,
                    'cardBrand' => 'STRIPE',
                    'last4' => 'N/A',
                ],
                'sendNotificationToMainContact' => true,
                'showPricesInNotification' => true,
            ];

            // Step 1: Confirm the reservation on Bokun
            $confirmReservedResponse = $this->bokunService->confirmReservedBooking($confirmationCode, $confirmReservedPayload);
            
            // Step 2: Confirm the booking after payment — official Bokun API
            $confirmPayload = [
                'externalBookingReference' => (string) $payment->reference,
                'externalBookingEntityName' => config('app.name', 'Royalloge'),
                'externalBookingEntityCode' => config('app.name', 'royalloge'),
                'bookingFields' => [
                    ['name' => 'STRIPE_SESSION_ID', 'value' => $sessionId],
                    ['name' => 'STRIPE_PAYMENT_INTENT_ID', 'value' => $paymentIntentId],
                    ['name' => 'PAYMENT_GATEWAY', 'value' => 'STRIPE'],
                    ['name' => 'PAYMENT_STATUS', 'value' => 'COMPLETED'],
                ],
                'payment' => [
                    'amount' => $bookingAmount,
                    'currency' => $bookingCurrency,
                    'paymentType' => 'WEB_PAYMENT',
                    'paymentProviderType' => 'STRIPE',
                    'paymentReferenceId' => $paymentIntentId !== '' ? $paymentIntentId : $sessionId,
                    'cardNumber' => '',
                    'transactionDate' => now()->toIso8601String(),
                ],
            ];

            $providerConfirmResponse = $this->bokunService->confirmBookingAfterPayment(
                $confirmationCode,
                $confirmPayload,
                true,
                $bookingCurrency,
                'EN'
            );

            // Extract all dynamic data from confirmBookingAfterPayment response
            $bokunStatus = strtoupper((string) data_get($providerConfirmResponse, 'status', 'CONFIRMED'));

            $confirmPrimaryProductBooking = (array) (
                data_get($providerConfirmResponse, 'activityBookings.0')
                ?? data_get($providerConfirmResponse, 'productBookings.0')
                ?? data_get($providerConfirmResponse, 'booking.productBookings.0')
                ?? data_get($providerConfirmResponse, 'booking.bookedProducts.0')
                ?? data_get($providerConfirmResponse, 'booking.activityBookings.0')
                ?? []
            );

            $confirmProductConfirmationCode = (string) (
                data_get($confirmPrimaryProductBooking, 'productConfirmationCode')
                ?: data_get($providerConfirmResponse, 'travelDocuments.activityTickets.0.productConfirmationCode')
                ?: ''
            );

            $confirmProductBookingId = data_get($confirmPrimaryProductBooking, 'bookingId')
                ?? data_get($confirmPrimaryProductBooking, 'activityBookingId')
                ?? data_get($confirmPrimaryProductBooking, 'productBookingId');

            $confirmBookingId = data_get($providerConfirmResponse, 'bookingId')
                ?? data_get($providerConfirmResponse, 'booking.bookingId')
                ?? data_get($confirmPrimaryProductBooking, 'parentBookingId')
                ?? data_get($providerConfirmResponse, 'travelDocuments.activityTickets.0.bookingId');

            $eTicketUrl = (string) data_get($providerConfirmResponse, 'travelDocuments.invoice', '');
            if ($eTicketUrl === '') {
                $eTicketUrl = (string) data_get($confirmReservedResponse, 'travelDocuments.invoice', '');
            }

            // Save confirmBookingAfterPayment response to main payment row
            $payment->update($this->filterBookingInfoAttributes([
                'status' => $bokunStatus,
                'confirmed_at' => now(),
                'stripe_last_webhook_event_id' => $eventId !== '' ? $eventId : $payment->stripe_last_webhook_event_id,
                'bokun_confirm_response' => $providerConfirmResponse,
                'provider_raw_data'      => $providerConfirmResponse,
                'booking_id' => $confirmBookingId !== null ? (string) $confirmBookingId : $payment->booking_id,
                'product_booking_id' => $confirmProductBookingId !== null ? (string) $confirmProductBookingId : $payment->product_booking_id,
                'product_confirmation_code' => $confirmProductConfirmationCode !== '' ? $confirmProductConfirmationCode : $payment->product_confirmation_code,
                'e_ticket_url' => $eTicketUrl ?: $payment->e_ticket_url,
            ]));

            // Update child booking rows with confirmBookingAfterPayment data
            $product = BookingInfo::where('booking_reference', $payment->reference)->first();
            if ($product) {
                $activityStatus = strtoupper((string) data_get($confirmPrimaryProductBooking, 'status', $bokunStatus));

                $product->update([
                    'status' => $bokunStatus,
                    'total_price' => $bookingAmount,
                    'currency' => $bookingCurrency,
                    'provider_type' => 'bokun',
                    'booking_id' => $confirmBookingId !== null ? (string) $confirmBookingId : $product->booking_id,
                    'product_booking_id' => $confirmProductBookingId !== null ? (string) $confirmProductBookingId : $product->product_booking_id,
                    'product_confirmation_code' => $confirmProductConfirmationCode !== '' ? $confirmProductConfirmationCode : $product->product_confirmation_code,
                    'e_ticket_url' => $eTicketUrl ?: $product->e_ticket_url,
                    'provider_raw_data' => $providerConfirmResponse,
                ]);

                $this->syncBookingInfosFromProvider($product, $providerConfirmResponse, $payment->id);
            }

            DB::commit();

            return response()->json([
                'status' => $bokunStatus,
                'reference' => $payment->reference,
                'confirmation_code' => $confirmationCode,
                'provider' => 'bokun',
                'provider_response' => $providerConfirmResponse,
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Stripe webhook processing failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error('Webhook processing failed: ' . $e->getMessage(), 500);
        }
    }

    public function abortReservedBooking(string $confirmationCode, Request $request)
    {
        try {

            $timeout = $request->boolean('timeout', false);

            $response = $this->bokunService->abortReservedBooking($confirmationCode, $timeout);

            BookingInfo::where('confirmation_code', $confirmationCode)
                ->where('status', '!=', 'CONFIRMED')
                ->update(['status' => 'CANCELLED']);

            return $this->success('Reserved booking aborted successfully', ['data' => $response]);
        } catch (RequestException $e) {
            Log::error('Abort reserved booking provider request failed', [
                'confirmation_code' => $confirmationCode,
                'error' => $e->getMessage(),
            ]);

            $providerStatus = $e->getResponse()?->getStatusCode() ?? 400;
            $providerBody = (string) ($e->getResponse()?->getBody() ?? '');
            $decoded = json_decode($providerBody, true);

            $providerMessage = strtolower(trim((string) data_get($decoded, 'message', '')));
            if ($providerMessage === 'booking is not reserved.') {
                $paymentStatus = BookingInfo::where('confirmation_code', $confirmationCode)->value('status');
                $productStatus = BookingInfo::where('confirmation_code', $confirmationCode)->value('status');

                return $this->success('Booking is already not reserved.', [
                    'confirmation_code' => $confirmationCode,
                    'already_not_reserved' => true,
                    'booking_payment_status' => $paymentStatus,
                    'booking_product_status' => $productStatus,
                    'provider_response' => is_array($decoded) ? $decoded : ['message' => 'Booking is not reserved.'],
                ]);
            }

            if (is_array($decoded)) {
                return response()->json($decoded, $providerStatus);
            }

            return $this->error('Abort reserved booking failed: ' . $e->getMessage(), $providerStatus);
        } catch (\Throwable $e) {
            Log::error('Abort reserved booking failed', [
                'confirmation_code' => $confirmationCode,
                'error' => $e->getMessage(),
            ]);
            return $this->error('Abort reserved booking failed: ' . $e->getMessage(), 400);
        }
    }

    public function paymentError(string $confirmationCode, Request $request)
    {
        $request->validate([
            'errorCode' => 'required|string',
            'errorDescription' => 'required|string',
        ]);

        try {
            $response = $this->bokunService->markPaymentError($confirmationCode, $request->only([
                'errorCode',
                'errorDescription',
            ]));

            $failureCode = (string) $request->input('errorCode');
            $failureDescription = (string) $request->input('errorDescription');

            BookingInfo::where('confirmation_code', $confirmationCode)->update([
                'status' => 'FAILED',
                'failure_stage' => 'payment_error',
                'failure_code' => $failureCode,
                'failure_reason' => $failureDescription,
                'failed_at' => now(),
                'admin_status' => 'needs_attention',
            ]);

            return $this->success('Payment error sent successfully', ['data' => $response]);
        } catch (\Throwable $e) {
            Log::error('Payment error endpoint failed', [
                'confirmation_code' => $confirmationCode,
                'error' => $e->getMessage(),
            ]);
            return $this->error('Payment error failed: ' . $e->getMessage(), 400);
        }
    }

    public function cancelBooking(string $confirmationCode, Request $request)
    {
        $request->validate([
            'note' => ['sometimes', 'string', 'max:1000'],
            'refundAmount' => ['sometimes', 'numeric', 'min:0'],
            'timeout' => [
                'sometimes',
                static function ($attribute, $value, $fail): void {
                    if (is_bool($value)) {
                        return;
                    }

                    $normalized = strtolower(trim((string) $value));
                    if (!in_array($normalized, ['true', 'false', '1', '0'], true)) {
                        $fail("The {$attribute} field must be true or false.");
                    }
                },
            ],
            'notify' => [
                'sometimes',
                static function ($attribute, $value, $fail): void {
                    if (is_bool($value)) {
                        return;
                    }

                    $normalized = strtolower(trim((string) $value));
                    if (!in_array($normalized, ['true', 'false', '1', '0'], true)) {
                        $fail("The {$attribute} field must be true or false.");
                    }
                },
            ],
            'refund' => [
                'sometimes',
                static function ($attribute, $value, $fail): void {
                    if (is_bool($value)) {
                        return;
                    }

                    $normalized = strtolower(trim((string) $value));
                    if (!in_array($normalized, ['true', 'false', '1', '0'], true)) {
                        $fail("The {$attribute} field must be true or false.");
                    }
                },
            ],
            'remainInvoiced' => [
                'sometimes',
                static function ($attribute, $value, $fail): void {
                    if (is_bool($value)) {
                        return;
                    }

                    $normalized = strtolower(trim((string) $value));
                    if (!in_array($normalized, ['true', 'false', '1', '0'], true)) {
                        $fail("The {$attribute} field must be true or false.");
                    }
                },
            ],
        ]);

        $identifiers = $this->parseBokunBookingIdentifiers($confirmationCode);

        if (empty($identifiers)) {
            return $this->error('No Bokun booking identifier provided.', 422);
        }

        if (count($identifiers) > 1) {
            $results = [];

            foreach ($identifiers as $identifier) {
                $results[] = $this->cancelSingleBokunBookingIdentifier($identifier, $request);
            }

            return $this->cancelMultipleResultsResponse($results);
        }

        $result = $this->cancelSingleBokunBookingIdentifier($identifiers[0], $request);

        return $this->cancelSingleResultResponse($result);
    }

    private function parseBokunBookingIdentifiers(string $identifiers): array
    {
        return collect(explode(',', $identifiers))
            ->map(fn(string $identifier): string => trim($identifier))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function cancelSingleBokunBookingIdentifier(string $identifier, Request $request): array
    {
        $resolvedBooking = $this->resolveBokunBookingIdentifier($identifier);
        $isProductConfirmationCode = $this->isBokunProductConfirmationCode($identifier);
        $resolvedConfirmationCode = (string) ($resolvedBooking['confirmation_code'] ?: $identifier);
        $resolvedProductConfirmationCode = (string) ($resolvedBooking['product_confirmation_code'] ?: $identifier);
        $resolvedStatus = strtoupper((string) ($resolvedBooking['status'] ?? ''));

        try {
            if ($isProductConfirmationCode && $resolvedStatus !== 'RESERVED') {
                $payload = $this->buildBokunProductCancellationPayload($request);
                $response = $this->bokunService->cancelProductBooking($resolvedProductConfirmationCode, $payload);

                if (!$this->isProviderCancellationResponseOk($response)) {
                    return [
                        'success' => false,
                        'status_code' => 400,
                        'identifier' => $identifier,
                        'type' => 'product_booking',
                        'message' => (string) data_get($response, 'message', 'Product booking cancellation failed.'),
                        'provider_response' => $response,
                    ];
                }

                $this->markBokunProductCancelled($resolvedProductConfirmationCode, $resolvedBooking);

                return [
                    'success' => true,
                    'status_code' => 200,
                    'identifier' => $identifier,
                    'type' => 'product_booking',
                    'message' => 'Product booking cancelled successfully',
                    'product_confirmation_code' => $resolvedProductConfirmationCode,
                    'confirmation_code' => $resolvedBooking['confirmation_code'],
                    'provider_response' => $response,
                ];
            }

            if ($resolvedStatus !== 'RESERVED') {
                $payload = $this->buildBokunCancellationPayload($request);
                $response = $this->bokunService->cancelBooking($resolvedConfirmationCode, $payload);

                if (!$this->isProviderCancellationResponseOk($response)) {
                    return [
                        'success' => false,
                        'status_code' => 400,
                        'identifier' => $identifier,
                        'type' => 'booking',
                        'message' => (string) data_get($response, 'message', 'Booking cancellation failed.'),
                        'provider_response' => $response,
                    ];
                }

                $this->markBokunReservationCancelled($resolvedConfirmationCode, $resolvedBooking);

                return [
                    'success' => true,
                    'status_code' => 200,
                    'identifier' => $identifier,
                    'type' => 'booking',
                    'message' => 'Booking cancelled successfully',
                    'confirmation_code' => $resolvedConfirmationCode,
                    'provider_response' => $response,
                ];
            }

            $timeout = $request->boolean('timeout', false);
            $response = $this->bokunService->abortReservedBooking($resolvedConfirmationCode, $timeout);

            if (!$this->isAbortReservedResponseOk($response)) {
                return [
                    'success' => false,
                    'status_code' => 400,
                    'identifier' => $identifier,
                    'type' => 'reserved_booking',
                    'message' => (string) data_get($response, 'message', 'Reserved booking cancellation failed.'),
                    'confirmation_code' => $resolvedConfirmationCode,
                    'provider_response' => $response,
                ];
            }

            $this->markBokunReservationCancelled($resolvedConfirmationCode, $resolvedBooking);

            return [
                'success' => true,
                'status_code' => 200,
                'identifier' => $identifier,
                'type' => 'reserved_booking',
                'message' => (string) data_get($response, 'message', 'Reserved booking cancelled successfully'),
                'confirmation_code' => $resolvedConfirmationCode,
                'provider_response' => $response,
            ];
        } catch (RequestException $e) {
            Log::error('Cancel booking provider request failed', [
                'identifier' => $identifier,
                'resolved_confirmation_code' => $resolvedConfirmationCode,
                'resolved_product_confirmation_code' => $resolvedProductConfirmationCode,
                'error' => $e->getMessage(),
            ]);

            $providerStatus = $e->getResponse()?->getStatusCode() ?? 400;
            $providerBody = (string) ($e->getResponse()?->getBody() ?? '');
            $decoded = json_decode($providerBody, true);

            // Persist failure reason/code to local booking rows so admins can inspect failures.
            try {
                $failurePayload = is_array($decoded) ? json_encode($decoded) : $providerBody;
                $failureCode = (string) $providerStatus;

                BookingInfo::where(function ($q) use ($resolvedConfirmationCode) {
                    $q->where('confirmation_code', $resolvedConfirmationCode)
                        ->orWhere('reference', $resolvedConfirmationCode)
                        ->orWhere('booking_reference', $resolvedConfirmationCode);
                })->update([
                    'status' => 'FAILED',
                    'failure_reason' => $failurePayload,
                    'failure_code' => $failureCode,
                ]);
            } catch (\Throwable $inner) {
                Log::error('Failed to persist booking failure info', ['identifier' => $identifier, 'error' => $inner->getMessage()]);
            }

            if (is_array($decoded)) {
                $providerMessage = (string) data_get($decoded, 'message', '');
                if (str_contains($providerMessage, 'NumberFormatException')) {
                    return [
                        'success' => false,
                        'status_code' => 422,
                        'identifier' => $identifier,
                        'type' => 'unknown',
                        'message' => 'Invalid Bokun booking identifier. Use a booking confirmation code for full booking cancellation, or a product confirmation code for product booking cancellation.',
                        'errors' => ['provider_message' => $providerMessage],
                    ];
                }

                return [
                    'success' => false,
                    'status_code' => $providerStatus,
                    'identifier' => $identifier,
                    'type' => 'provider_error',
                    'message' => (string) data_get($decoded, 'message', 'Cancel booking failed.'),
                    'provider_response' => $decoded,
                ];
            }

            return [
                'success' => false,
                'status_code' => $providerStatus,
                'identifier' => $identifier,
                'type' => 'provider_error',
                'message' => 'Cancel booking failed: ' . $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            Log::error('Cancel booking failed', [
                'identifier' => $identifier,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status_code' => 400,
                'identifier' => $identifier,
                'type' => 'server_error',
                'message' => 'Cancel booking failed: ' . $e->getMessage(),
            ];
        }
    }

    private function cancelSingleResultResponse(array $result): \Illuminate\Http\JsonResponse
    {
        if ($result['success'] ?? false) {
            if (($result['type'] ?? '') === 'product_booking') {
                return $this->success($result['message'], [
                    'product_confirmation_code' => $result['product_confirmation_code'],
                    'confirmation_code' => $result['confirmation_code'],
                    'provider_response' => $result['provider_response'],
                ]);
            }

            return response()->json($result['provider_response'], 200);
        }

        if (isset($result['provider_response'])) {
            return response()->json($result['provider_response'], (int) ($result['status_code'] ?? 400));
        }

        return $this->error(
            (string) $result['message'],
            (int) ($result['status_code'] ?? 400),
            (array) ($result['errors'] ?? [])
        );
    }

    private function cancelMultipleResultsResponse(array $results): \Illuminate\Http\JsonResponse
    {
        $failed = collect($results)->contains(fn(array $result): bool => !($result['success'] ?? false));

        return response()->json([
            'status' => $failed ? 'PARTIAL_FAILED' : 'CANCELLED',
            'message' => $failed
                ? 'One or more booking cancellations failed'
                : 'Booking cancellations processed successfully',
            'data' => $results,
        ], $failed ? 207 : 200);
    }

    private function resolveBokunBookingIdentifier(string $identifier): array
    {
        $payment = BookingInfo::where(function ($query) use ($identifier): void {
            $query->where('confirmation_code', $identifier)
                ->orWhere('reference', $identifier)
                ->orWhere('booking_reference', $identifier);
        })->first();

        if ($payment) {
            $product = BookingInfo::where('booking_reference', $payment->reference)->first();

            return [
                'payment' => $payment,
                'product' => $product,
                'detail' => null,
                'confirmation_code' => (string) $payment->confirmation_code,
                'product_confirmation_code' => (string) ($product?->product_confirmation_code ?? ''),
                'status' => (string) $payment->status,
            ];
        }

        $product = BookingInfo::where(function ($query) use ($identifier): void {
            $query->where('confirmation_code', $identifier)
                ->orWhere('booking_reference', $identifier)
                ->orWhere('product_confirmation_code', $identifier)
                ->orWhere('booking_id', $identifier)
                ->orWhere('product_booking_id', $identifier);
        })->first();

        if ($product) {
            $payment = BookingInfo::where(function ($query) use ($product): void {
                $query->where('reference', $product->booking_reference)
                    ->orWhere('booking_reference', $product->booking_reference);
            })->first();

            return [
                'payment' => $payment,
                'product' => $product,
                'detail' => null,
                'confirmation_code' => (string) ($product->confirmation_code ?: $payment?->confirmation_code),
                'product_confirmation_code' => (string) $product->product_confirmation_code,
                'status' => (string) ($product->status ?: $payment?->status),
            ];
        }

        $detail = BookingInfo::where(function ($query) use ($identifier): void {
            $query->where('product_confirmation_code', $identifier)
                ->orWhere('booking_id', $identifier)
                ->orWhere('product_booking_id', $identifier);
        })->first();

        if ($detail) {
            $product = BookingInfo::whereKey($detail->booking_product_id)->first();
            $payment = $detail->booking_payment_id
                ? BookingInfo::whereKey($detail->booking_payment_id)->first()
                : null;

            return [
                'payment' => $payment,
                'product' => $product,
                'detail' => $detail,
                'confirmation_code' => (string) ($product?->confirmation_code ?: $payment?->confirmation_code),
                'product_confirmation_code' => (string) ($detail->product_confirmation_code ?: $product?->product_confirmation_code),
                'status' => (string) ($detail->status ?: $product?->status ?: $payment?->status),
            ];
        }

        return [
            'payment' => null,
            'product' => null,
            'detail' => null,
            'confirmation_code' => '',
            'product_confirmation_code' => '',
            'status' => '',
        ];
    }

    private function isBokunProductConfirmationCode(string $identifier): bool
    {
        return (bool) preg_match('/^[A-Z0-9]+-T\d+$/i', $identifier);
    }

    private function buildBokunCancellationPayload(Request $request): array
    {
        return [
            'note' => (string) $request->input('note', 'Cancelled from API.'),
            'notify' => $request->boolean('notify', true),
            'refund' => $request->boolean('refund', true),
        ];
    }

    private function buildBokunProductCancellationPayload(Request $request): array
    {
        $payload = $this->buildBokunCancellationPayload($request);
        $payload['refundAmount'] = (float) $request->input('refundAmount', 0);

        if ($request->has('remainInvoiced')) {
            $payload['remainInvoiced'] = $request->boolean('remainInvoiced');
        }

        return $payload;
    }

    private function isProviderCancellationResponseOk(array $response): bool
    {
        if (array_key_exists('success', $response)) {
            return (bool) $response['success'];
        }

        $status = strtolower(trim((string) data_get($response, 'status', '')));
        if ($status !== '') {
            return in_array($status, ['ok', 'success', 'cancelled', 'canceled'], true);
        }

        $message = strtolower(trim((string) data_get($response, 'message', '')));
        if ($message !== '') {
            return !str_contains($message, 'error') && !str_contains($message, 'fail');
        }

        return !empty($response);
    }

    private function markBokunProductCancelled(string $productConfirmationCode, array $resolvedBooking): void
    {
        BookingInfo::where('product_confirmation_code', $productConfirmationCode)
            ->update(['status' => 'CANCELLED']);

        $product = $resolvedBooking['product'] ?? null;
        $payment = $resolvedBooking['payment'] ?? null;

        if ($product && $product->product_confirmation_code === $productConfirmationCode) {
            $product->update(['status' => 'CANCELLED']);
        }

        if ($payment) {
            $payment->update(['status' => 'CANCELLED']);
        }
    }

    private function markBokunReservationCancelled(string $confirmationCode, array $resolvedBooking): void
    {
        BookingInfo::where('confirmation_code', $confirmationCode)
            ->where('status', 'RESERVED')
            ->update(['status' => 'CANCELLED']);

        $productConfirmationCode = (string) ($resolvedBooking['product_confirmation_code'] ?? '');
        if ($productConfirmationCode !== '') {
            BookingInfo::where('product_confirmation_code', $productConfirmationCode)
                ->where('status', 'RESERVED')
                ->update(['status' => 'CANCELLED']);
        }
    }

    private function isAbortReservedResponseOk(array $response): bool
    {
        if (array_key_exists('success', $response)) {
            return (bool) $response['success'];
        }

        $status = strtolower(trim((string) data_get($response, 'status', '')));
        if ($status !== '') {
            return in_array($status, ['ok', 'success', 'aborted', 'cancelled', 'reserved_aborted'], true);
        }

        $message = strtolower(trim((string) data_get($response, 'message', '')));
        if ($message !== '') {
            if (str_contains($message, 'error') || str_contains($message, 'fail')) {
                return false;
            }

            return true;
        }

        return !empty($response);
    }

    public function bookingSummary(string $id)
    {
        try {
            $summary = $this->bokunService->getBookingSummary($id);

            // If it's a PDF or raw file, return it directly
            if (isset($summary['raw_body'], $summary['content_type']) && str_contains($summary['content_type'], 'application/pdf')) {
                return response($summary['raw_body'], 200)
                    ->header('Content-Type', (string) $summary['content_type'])
                    ->header('Content-Disposition', 'inline; filename="booking-summary-' . $id . '.pdf"');
            }

            return response()->json($summary, 200);
        } catch (\Throwable $e) {
            Log::error('Booking summary failed', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->error('Failed to get booking summary: ' . $e->getMessage(), 400);
        }
    }

    public function activityBookingTicket(string $productConfirmationCode)
    {
        try {
            $ticket = $this->bokunService->getActivityBookingTicket($productConfirmationCode);

            if (isset($ticket['raw_body'], $ticket['content_type'])) {
                return response($ticket['raw_body'], 200)
                    ->header('Content-Type', (string) $ticket['content_type']);
            }

            return response()->json($ticket, 200);
        } catch (\Throwable $e) {
            Log::error('Activity booking ticket failed', [
                'product_confirmation_code' => $productConfirmationCode,
                'error' => $e->getMessage(),
            ]);
            return $this->error('Failed to get activity booking ticket: ' . $e->getMessage(), 400);
        }
    }

    public function show(string $reference)
    {
        try {
            $payment = BookingInfo::where('reference', $reference)->firstOrFail();
            $product = BookingInfo::where('booking_reference', $payment->reference)->first();

            $providerResponse = $payment->bokun_confirm_response
                ?? data_get($product, 'provider_raw_data')
                ?? $payment->bokun_submit_response;

            return response()->json([
                'status' => $payment->status,
                'reference' => $payment->reference,
                'confirmation_code' => $payment->confirmation_code,
                'provider' => 'bokun',
                'provider_response' => $providerResponse,
                'paid_at' => $payment->paid_at,
                'confirmed_at' => $payment->confirmed_at,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Bokun booking show failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
            return $this->error('Booking not found.', 404);
        }
    }

    private function normalizeCheckoutPayload(array $input, string $currency): array
    {
        $checkoutOption = (string) ($input['checkoutOption'] ?? 'CUSTOMER_FULL_PAYMENT');
        $source = (string) ($input['source'] ?? 'DIRECT_REQUEST');
        $paymentMethod = 'RESERVE_FOR_EXTERNAL_PAYMENT';
        $directBooking = (array) ($input['directBooking'] ?? []);

        if (empty($directBooking)) {
            throw new \InvalidArgumentException('directBooking payload is required.');
        }

        if (!empty($directBooking['activityBookings']) && is_array($directBooking['activityBookings'])) {
            $activityBookings = $directBooking['activityBookings'];
        } elseif (!empty($directBooking['items']) && is_array($directBooking['items'])) {
            $activityBookings = $this->mapLegacyItemsToActivityBookings($directBooking['items']);
        } else {
            throw new \InvalidArgumentException('directBooking must include activityBookings or items.');
        }

        $normalizedDirectBooking = [
            'mainContactDetails' => (array) ($directBooking['mainContactDetails'] ?? []),
            'activityBookings' => $activityBookings,
            'accommodationBookings' => (array) ($directBooking['accommodationBookings'] ?? []),
            'rentalBookings' => (array) ($directBooking['rentalBookings'] ?? []),
            'externalBookingReference' => (string) ($directBooking['externalBookingReference'] ?? ''),
            'externalBookingEntityName' => (string) ($directBooking['externalBookingEntityName'] ?? ''),
            'externalBookingEntityCode' => (string) ($directBooking['externalBookingEntityCode'] ?? ''),
            'labels' => (array) ($directBooking['labels'] ?? []),
            'promoCode' => (string) ($directBooking['promoCode'] ?? ''),
            'giftCardCode' => (string) ($directBooking['giftCardCode'] ?? ''),
            'currency' => strtoupper($currency),
        ];

        $payload = [
            'checkoutOption' => $checkoutOption,
            'paymentMethod' => $paymentMethod,
            'source' => $source,
            'directBooking' => $normalizedDirectBooking,
            'sendNotificationToMainContact' => (bool) ($input['sendNotificationToMainContact'] ?? true),
            'showPricesInNotification' => (bool) ($input['showPricesInNotification'] ?? true),
            'checkoutOptionAnswers' => (array) ($input['checkoutOptionAnswers'] ?? []),
            'cardPaymentProviderAnswers' => (array) ($input['cardPaymentProviderAnswers'] ?? []),
        ];

        // This endpoint always reserves externally, so payment-capture fields
        // (amount/paymentToken/transactionDetails/...) are intentionally not forwarded.
        $optionalKeys = [
            'affiliateCode',
            'bookingReferralSource',
            'googleClickId',
        ];

        foreach ($optionalKeys as $key) {
            if (!array_key_exists($key, $input)) {
                continue;
            }

            $value = trim((string) $input[$key]);
            if ($value === '') {
                continue;
            }

            $payload[$key] = $value;
        }

        return [
            'payload' => $payload,
            'direct_booking' => $normalizedDirectBooking,
            'source' => $source,
        ];
    }

    private function mapLegacyItemsToActivityBookings(array $items): array
    {
        $mapped = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $activityId = data_get($item, 'activityId');
            if (!$activityId) {
                throw new \InvalidArgumentException('Legacy directBooking.items requires activityId for each item.');
            }

            $passengers = $item['passengers'] ?? [];
            if (empty($passengers)) {
                $defaultPricingCategoryId = (int) data_get($item, 'pricingCategoryId', 0);
                $defaultQuantity = (int) data_get($item, 'quantity', 1);
                $passengers = [[
                    'pricingCategoryId' => $defaultPricingCategoryId,
                    'groupSize' => max(1, $defaultQuantity),
                    'passengerDetails' => [],
                    'answers' => [],
                    'extras' => [],
                ]];
            }

            $mapped[] = array_merge([
                'activityId' => $activityId,
                'answers' => [],
                'pickupAnswers' => [],
                'dropoffAnswers' => [],
                'rateId' => 0,
                'startTimeId' => 0,
                'date' => '',
                'pickup' => false,
                'pickupPlaceId' => 0,
                'pickupDescription' => '',
                'dropoff' => false,
                'dropoffPlaceId' => 0,
                'dropoffDescription' => '',
                'checkedIn' => false,
                'note' => '',
                'customized' => false,
                'customizedTime' => '',
                'customizedPrice' => 0,
                'passengers' => $passengers,
            ], $item);
        }

        return $mapped;
    }

    private function buildStripeReturnUrls(Request $request, string $reference): array
    {
        $successUrl = (string) $request->input('successUrl', '');
        $cancelUrl = (string) $request->input('cancelUrl', '');

        if ($successUrl === '' || $cancelUrl === '') {
            $frontendBaseUrl = rtrim((string) config('app.frontend_url', ''), '/');
            if ($frontendBaseUrl === '') {
                throw new \RuntimeException('Provide successUrl/cancelUrl or configure FRONTEND_URL.');
            }

            if ($successUrl === '') {
                $successUrl = $frontendBaseUrl . '/payment/stripe/success';
            }
            if ($cancelUrl === '') {
                $cancelUrl = $frontendBaseUrl . '/payment/stripe/cancel';
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

    private function extractCustomer(array $directBooking): array
    {
        $name = null;
        $email = null;
        $phone = null;

        foreach ((array) ($directBooking['mainContactDetails'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $qid = strtolower((string) ($row['questionId'] ?? ''));
            $val = (string) data_get($row, 'values.0', '');
            if ($val === '') {
                continue;
            }

            if (!$email && (str_contains($qid, 'email') || filter_var($val, FILTER_VALIDATE_EMAIL))) {
                $email = $val;
            }

            if (!$name && (str_contains($qid, 'name') || str_contains($qid, 'full'))) {
                $name = $val;
            }

            if (!$phone && (str_contains($qid, 'phone') || str_contains($qid, 'mobile'))) {
                $phone = $val;
            }
        }

        return [
            'name' => $name ?: 'N/A',
            'email' => $email,
            'phone' => $phone,
        ];
    }

    private function persistFailedSubmitCheckout(Request $request, string $provider, \Throwable $e): void
    {
        try {
            $reference = 'FAILED-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6));
            $directBooking = (array) $request->input('directBooking', []);
            $customer = $this->extractCustomer($directBooking);
            $context = $this->bookingLifecycle->contextAttributes($request, $provider, [
                'directBooking' => $directBooking,
            ]);

            $attributes = $this->bookingLifecycle->filterBookingColumns(array_merge($context, [
                'reference' => $reference,
                'booking_reference' => $reference,
                'provider_type' => $provider,
                'customer_name' => $customer['name'],
                'customer_email' => $customer['email'],
                'customer_phone' => $customer['phone'],
                'status' => 'FAILED',
                'payment_gateway' => (string) $request->input('payment_gateway', 'stripe'),
                'payment_source' => (string) $request->input('source', 'DIRECT_REQUEST'),
                'failure_stage' => $provider . '_submit_checkout',
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
                    'payload' => ['stage' => $provider . '_submit_checkout'],
                ]);
            }
        } catch (\Throwable $inner) {
            Log::error('Failed to persist failed Bokun checkout', ['error' => $inner->getMessage()]);
        }
    }

    private function resolveCheckoutOptionsBookingRequest(Request $request): array
    {
        $wrapped = $request->input('bookingRequest');
        if (is_array($wrapped)) {
            if (!empty($wrapped['items']) && empty($wrapped['activityBookings'])) {
                $wrapped['activityBookings'] = $this->mapLegacyItemsToActivityBookings($wrapped['items']);
            }
            return $wrapped;
        }

        $raw = $request->all();
        unset($raw['currency'], $raw['lang']);

        if (!is_array($raw) || empty($raw)) {
            throw new \InvalidArgumentException('bookingRequest payload is required.');
        }

        if (!empty($raw['items']) && empty($raw['activityBookings'])) {
            $raw['activityBookings'] = $this->mapLegacyItemsToActivityBookings($raw['items']);
        }

        return $raw;
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
}
