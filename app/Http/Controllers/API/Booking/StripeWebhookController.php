<?php

namespace App\Http\Controllers\API\Booking;

use App\Http\Controllers\Controller;
use App\Models\BookingInfo;
use App\Services\Booking\BookingLifecycleService;
use App\Services\Bokun\BokunService;
use App\Services\GlobalTix\GlobalTixBookingService;
use App\Services\Payments\StripeCheckoutService;
use App\Traits\ApiResponse;
use App\Traits\SyncBookingInfosTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Stripe\Event;

class StripeWebhookController extends Controller
{
    use ApiResponse, SyncBookingInfosTrait;
    private const DEFAULT_BOKUN_LANG = 'EN';

    public function __construct(
        private readonly StripeCheckoutService $stripeService,
        private readonly BokunService $bokunService,
        private readonly GlobalTixBookingService $globalTixBookingService,
        private readonly BookingLifecycleService $bookingLifecycle,
        private readonly GlobalTixBookingController $globalTixBookingController
    ) {}

    public function handle(Request $request)
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
        
        if (!in_array($eventType, ['checkout.session.completed', 'payment_intent.succeeded', 'payment_intent.payment_failed'], true)) {
            return response()->json(['status' => 'received', 'event_type' => $eventType], 200);
        }

        $session = (array) data_get($event, 'data.object', []);
        
        $sessionId = '';
        $paymentIntentId = '';
        $reference = '';
        
        if ($eventType === 'checkout.session.completed') {
            $sessionId = (string) data_get($session, 'id', '');
            $paymentIntentId = (string) data_get($session, 'payment_intent', '');
            $reference = (string) data_get($session, 'metadata.reference', '');
        } elseif ($eventType === 'payment_intent.succeeded' || $eventType === 'payment_intent.payment_failed') {
            $paymentIntentId = (string) data_get($session, 'id', '');
            $reference = (string) data_get($session, 'metadata.reference', '');
            // Also try to find confirmation_code, provider etc from metadata if needed, 
            // but resolveProvider handles it.
        }

        $provider = $this->resolveProvider($session, $reference, $sessionId);

        // Try to look up reference if missing
        if ($reference === '') {
            if ($paymentIntentId !== '') {
                $sessionPayment = BookingInfo::where('stripe_payment_intent_id', $paymentIntentId)->first();
                $reference = (string) ($sessionPayment?->reference ?: $sessionPayment?->booking_reference ?: '');
            }
            if ($reference === '' && $sessionId !== '') {
                $sessionPayment = BookingInfo::where('stripe_session_id', $sessionId)->first();
                $reference = (string) ($sessionPayment?->reference ?: $sessionPayment?->booking_reference ?: '');
            }
        }

        if ($reference === '') {
            return $this->error('Missing reference in Stripe metadata', 400);
        }

        // Dynamically determine the exact payment method used (card, paypal, google_pay, etc.)
        $paymentMethodType = '';
        if ($eventType === 'payment_intent.succeeded' || $eventType === 'payment_intent.payment_failed') {
            $paymentMethodType = (string) data_get($event, 'data.object.latest_charge.payment_method_details.type');
            if (empty($paymentMethodType)) {
                $paymentMethodType = (string) data_get($event, 'data.object.charges.data.0.payment_method_details.type', 'stripe');
            }
        } else {
            $paymentMethodType = 'stripe';
        }

        $paymentStatus = 'PAID';
        if ($eventType === 'payment_intent.payment_failed') {
            $paymentStatus = 'FAILED';
        }

        $failureReason = '';
        if ($paymentStatus === 'FAILED') {
            $failureReason = (string) data_get($event, 'data.object.last_payment_error.message', 'Payment failed');
        }

        $cardBrand = strtoupper((string) data_get($event, 'data.object.latest_charge.payment_method_details.card.brand', $paymentMethodType));
        if (empty($cardBrand)) $cardBrand = 'STRIPE';
        
        $last4 = (string) data_get($event, 'data.object.latest_charge.payment_method_details.card.last4', 'N/A');

        // Standardize data for processing functions
        $sessionData = [
            'id' => $sessionId,
            'payment_intent' => $paymentIntentId,
            'payment_method_type' => $paymentMethodType,
            'payment_status' => $paymentStatus,
            'failure_reason' => $failureReason,
            'card_brand' => $cardBrand,
            'last4' => $last4,
        ];

        if ($provider === 'globaltix') {
            return $this->processGlobalTixCheckoutCompleted($event, $sessionData, $reference);
        }
        if (in_array($provider, ['manual', 'supplier'], true)) {
            return $this->processManualCheckoutCompleted($event, $sessionData, $reference);
        }
        return $this->processBokunCheckoutCompleted($event, $sessionData, $reference);
    }

    private function processManualCheckoutCompleted(array $event, array $session, string $reference)
    {
        $eventId = (string) data_get($event, 'id', '');
        $sessionId = (string) data_get($session, 'id', '');
        $paymentIntentId = (string) data_get($session, 'payment_intent', '');
        
        $paymentMethodType = (string) data_get($session, 'payment_method_type', 'stripe');
        $gateway = ($paymentMethodType !== 'stripe' && !empty($paymentMethodType)) ? 'stripe_' . $paymentMethodType : 'stripe';
        
        $paymentStatus = (string) data_get($session, 'payment_status', 'PAID');
        $failureReason = (string) data_get($session, 'failure_reason', '');
        $cardBrand = (string) data_get($session, 'card_brand', 'STRIPE');
        $last4 = (string) data_get($session, 'last4', 'N/A');

        DB::beginTransaction();

        try {
            $paymentQuery = $this->paymentByReferenceQuery($reference);
            if ($sessionId !== '' && $this->bookingPaymentsHasColumn('stripe_session_id')) {
                $paymentQuery->orWhere('stripe_session_id', $sessionId);
            }

            $payment = $paymentQuery->lockForUpdate()->first();

            if (!$payment) {
                DB::rollBack();
                Log::error('Stripe webhook manual booking reference not found', ['reference' => $reference]);
                return response()->json(['status' => 'ignored_not_found'], 200);
            }

            if ($payment->status === 'CONFIRMED' || ($eventId !== '' && $payment->stripe_last_webhook_event_id === $eventId)) {
                DB::rollBack();
                return response()->json(['status' => 'already_processed'], 200);
            }

            if ($paymentStatus === 'FAILED') {
                $failedUpdates = $this->filterBookingInfoAttributes([
                    'status' => 'FAILED',
                    'failed_at' => now(),
                    'failure_stage' => 'payment_failed',
                    'failure_reason' => $failureReason ?: 'Payment failed',
                    'payment_gateway' => $gateway,
                    'stripe_session_id' => $sessionId !== '' ? $sessionId : $payment->stripe_session_id,
                    'stripe_payment_intent_id' => $paymentIntentId !== '' ? $paymentIntentId : $payment->stripe_payment_intent_id,
                    'stripe_last_webhook_event_id' => $eventId,
                ]);

                $payment->update($failedUpdates);
                BookingInfo::where('booking_product_id', $payment->id)
                    ->whereNull('event_type')
                    ->update($failedUpdates);

                DB::commit();
                return response()->json(['status' => 'FAILED', 'reference' => $payment->reference, 'reason' => $failureReason], 200);
            }

            $successUpdates = $this->filterBookingInfoAttributes([
                'status' => 'CONFIRMED',
                'paid_at' => now(),
                'confirmed_at' => now(),
                'payment_gateway' => $gateway,
                'stripe_session_id' => $sessionId !== '' ? $sessionId : $payment->stripe_session_id,
                'stripe_payment_intent_id' => $paymentIntentId !== '' ? $paymentIntentId : $payment->stripe_payment_intent_id,
                'stripe_last_webhook_event_id' => $eventId,
            ]);

            $payment->update($successUpdates);
            BookingInfo::where('booking_product_id', $payment->id)
                ->whereNull('event_type')
                ->update($successUpdates);

            DB::commit();

            return response()->json(['status' => 'SUCCESS', 'reference' => $payment->reference], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Stripe webhook manual error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    private function resolveProvider(array $session, string $reference, string $sessionId): string
    {
        $provider = strtolower(trim((string) data_get($session, 'metadata.provider', '')));
        if ($provider !== '') {
            return $provider;
        }

        if ($reference !== '') {
            $provider = strtolower(trim((string) ($this->paymentByReferenceQuery($reference)->value('provider_type') ?? '')));
            if ($provider !== '') {
                return $provider;
            }
        }

        if ($sessionId !== '' && $this->bookingPaymentsHasColumn('stripe_session_id')) {
            $provider = strtolower(trim((string) (BookingInfo::where('stripe_session_id', $sessionId)->value('provider_type') ?? '')));
            if ($provider !== '') {
                return $provider;
            }
        }

        return 'bokun';
    }

    private function processBokunCheckoutCompleted(array $event, array $session, string $reference)
    {
        $eventId = (string) data_get($event, 'id', '');
        $sessionId = (string) data_get($session, 'id', '');
        $paymentIntentId = (string) data_get($session, 'payment_intent', '');

        $paymentMethodType = (string) data_get($session, 'payment_method_type', 'stripe');
        if ($paymentMethodType !== 'stripe' && !empty($paymentMethodType)) {
            $gateway = 'stripe_' . $paymentMethodType; // e.g. stripe_paypal, stripe_google_pay
        } else {
            $gateway = 'stripe';
        }
        
        $paymentStatus = (string) data_get($session, 'payment_status', 'PAID');
        $failureReason = (string) data_get($session, 'failure_reason', '');
        $cardBrand = (string) data_get($session, 'card_brand', 'STRIPE');
        $last4 = (string) data_get($session, 'last4', 'N/A');

        DB::beginTransaction();

        try {
            $paymentQuery = $this->paymentByReferenceQuery($reference);
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

            if ($paymentStatus === 'FAILED') {
                $failedUpdates = $this->filterBookingInfoAttributes([
                    'status' => 'FAILED',
                    'failed_at' => now(),
                    'failure_stage' => 'payment_failed',
                    'failure_reason' => $failureReason ?: 'Payment failed',
                    'payment_gateway' => $gateway,
                    'stripe_session_id' => $sessionId !== '' ? $sessionId : $payment->stripe_session_id,
                    'stripe_payment_intent_id' => $paymentIntentId !== '' ? $paymentIntentId : $payment->stripe_payment_intent_id,
                ]);

                $payment->update($failedUpdates);
                BookingInfo::where('booking_reference', $payment->reference)->update($failedUpdates);

                DB::commit();
                return response()->json(['status' => 'FAILED', 'reference' => $payment->reference, 'reason' => $failureReason], 200);
            }

            $payment->update([
                'status' => 'PAID',
                'paid_at' => now(),
                'payment_gateway' => $gateway,
                'stripe_session_id' => $sessionId !== '' ? $sessionId : $payment->stripe_session_id,
                'stripe_payment_intent_id' => $paymentIntentId !== '' ? $paymentIntentId : $payment->stripe_payment_intent_id,
            ]);

            $confirmReservedPayload = [
                'externalBookingReference' => (string) $payment->reference,
                'externalBookingEntityName' => config('app.name', 'Royalloge'),
                'externalBookingEntityCode' => config('app.name', 'royalloge'),
                'amount' => $bookingAmount,
                'currency' => $bookingCurrency,
                'transactionDetails' => [
                    'transactionDate' => now()->toIso8601String(),
                    'transactionId' => $paymentIntentId !== '' ? $paymentIntentId : $sessionId,
                    'cardBrand' => $cardBrand,
                    'last4' => $last4,
                ],
                'sendNotificationToMainContact' => true,
                'showPricesInNotification' => true,
            ];

            // 1. Confirm the reservation
            $confirmReservedResponse = $this->bokunService->confirmReservedBooking($confirmationCode, $confirmReservedPayload);
            
            // 2. Confirm the booking (after payment) — matches official Bokun API spec
            $confirmPayload = [
                'externalBookingReference' => (string) $payment->reference,
                'externalBookingEntityName' => config('app.name', 'Royalloge'),
                'externalBookingEntityCode' => config('app.name', 'royalloge'),
                'bookingFields' => [
                    ['name' => 'STRIPE_SESSION_ID', 'value' => $sessionId],
                    ['name' => 'STRIPE_PAYMENT_INTENT_ID', 'value' => $paymentIntentId],
                    ['name' => 'PAYMENT_GATEWAY', 'value' => strtoupper($gateway)],
                    ['name' => 'PAYMENT_STATUS', 'value' => 'COMPLETED'],
                ],
                'payment' => [
                    'amount' => $bookingAmount,
                    'currency' => $bookingCurrency,
                    'paymentType' => 'WEB_PAYMENT',
                    'paymentProviderType' => strtoupper($gateway),
                    'paymentReferenceId' => $paymentIntentId !== '' ? $paymentIntentId : $sessionId,
                    'cardNumber' => $last4 !== 'N/A' ? $last4 : '',
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

            // Extract confirmed status from Bokun response
            $bokunStatus = strtoupper((string) data_get($providerConfirmResponse, 'status', 'CONFIRMED'));

            $eTicketUrl = (string) data_get($providerConfirmResponse, 'travelDocuments.invoice', '');
            if ($eTicketUrl === '') {
                $eTicketUrl = (string) data_get($confirmReservedResponse, 'travelDocuments.invoice', '');
            }

            $payment->update($this->filterBookingInfoAttributes([
                'status' => $bokunStatus,
                'confirmed_at' => now(),
                'stripe_last_webhook_event_id' => $eventId !== '' ? $eventId : $payment->stripe_last_webhook_event_id,
                'bokun_confirm_response' => $providerConfirmResponse,
                'provider_raw_data' => $providerConfirmResponse,
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
            $this->safeRollback();
            Log::error('Stripe webhook processing failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            try {
                $paidUpdates = $this->filterBookingInfoAttributes([
                    'status' => 'PAID',
                    'paid_at' => now(),
                    'payment_gateway' => $gateway,
                    'stripe_session_id' => $sessionId,
                    'stripe_payment_intent_id' => $paymentIntentId,
                ]);

                BookingInfo::where('reference', $reference)->update($paidUpdates);
                BookingInfo::where('booking_reference', $reference)->update($paidUpdates);
                $this->bookingLifecycle->markNeedsAttention(
                    $reference,
                    'bokun_confirm_after_payment',
                    $e->getMessage(),
                    (string) $e->getCode()
                );
            } catch (\Throwable $inner) {
                Log::error('Failed to persist booking failure info', ['reference' => $reference, 'error' => $inner->getMessage()]);
            }

            return $this->error('Webhook processing failed: ' . $e->getMessage(), 500);
        }
    }

    private function processGlobalTixCheckoutCompleted(array $event, array $session, string $reference)
    {
        $eventId = (string) data_get($event, 'id', '');
        $sessionId = (string) data_get($session, 'id', '');
        $paymentIntentId = (string) data_get($session, 'payment_intent', '');

        $paymentMethodType = (string) data_get($session, 'payment_method_type', 'stripe');
        if ($paymentMethodType !== 'stripe' && !empty($paymentMethodType)) {
            $gateway = 'stripe_' . $paymentMethodType;
        } else {
            $gateway = 'stripe';
        }

        $paymentStatus = (string) data_get($session, 'payment_status', 'PAID');
        $failureReason = (string) data_get($session, 'failure_reason', '');

        $bookingContext = null;

        DB::beginTransaction();
        try {
            $paymentQuery = $this->paymentByReferenceQuery($reference);
            if ($sessionId !== '' && $this->bookingPaymentsHasColumn('stripe_session_id')) {
                $paymentQuery->orWhere('stripe_session_id', $sessionId);
            }

            $payment = $paymentQuery->lockForUpdate()->first();
            if (!$payment) {
                DB::rollBack();
                Log::error('GlobalTix Stripe webhook booking reference not found', ['reference' => $reference]);
                return response()->json(['status' => 'ignored_not_found'], 200);
            }

            if ($payment->status === 'CONFIRMED' || ($eventId !== '' && $payment->stripe_last_webhook_event_id === $eventId)) {
                DB::rollBack();
                return response()->json(['status' => 'already_processed'], 200);
            }

            $bookingReference = (string) ($payment->reference ?: $reference);
            $confirmationCode = (string) ($payment->confirmation_code ?: $bookingReference);
            $bookingCurrency = strtoupper((string) ($payment->booking_currency ?: $payment->paypal_currency ?: 'EUR'));
            $bookingAmount = (float) ($payment->booking_amount ?: $payment->amount ?: 0);

            if ($confirmationCode === '') {
                DB::rollBack();
                return $this->error('Missing confirmation code for booking', 422);
            }

            if ($bookingAmount <= 0 || $bookingCurrency === '') {
                DB::rollBack();
                return $this->error('Missing booking amount/currency', 422);
            }

            if ($paymentStatus === 'FAILED') {
                $failedUpdates = $this->filterBookingInfoAttributes([
                    'status' => 'FAILED',
                    'failed_at' => now(),
                    'failure_stage' => 'payment_failed',
                    'failure_reason' => $failureReason ?: 'Payment failed',
                    'payment_gateway' => $gateway,
                    'stripe_session_id' => $sessionId !== '' ? $sessionId : $payment->stripe_session_id,
                    'stripe_payment_intent_id' => $paymentIntentId !== '' ? $paymentIntentId : $payment->stripe_payment_intent_id,
                ]);

                $payment->update($failedUpdates);
                BookingInfo::where('booking_reference', $payment->reference)->update($failedUpdates);

                DB::commit();
                return response()->json(['status' => 'FAILED', 'reference' => $payment->reference, 'reason' => $failureReason], 200);
            }

            $payment->update($this->filterBookingInfoAttributes([
                'status' => 'PAID',
                'paid_at' => now(),
                'payment_gateway' => $gateway,
                'stripe_session_id' => $sessionId !== '' ? $sessionId : $payment->stripe_session_id,
                'stripe_payment_intent_id' => $paymentIntentId !== '' ? $paymentIntentId : $payment->stripe_payment_intent_id,
            ]));

            $bookingContext = [
                'reference' => $bookingReference,
                'confirmation_code' => $confirmationCode,
                'amount' => $bookingAmount,
                'currency' => $bookingCurrency,
            ];

            DB::commit();
        } catch (\Throwable $e) {
            $this->safeRollback();
            Log::error('GlobalTix Stripe webhook paid-marking failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            try {
                // Payment was captured by Stripe — preserve PAID status, mark for admin attention
                $paidUpdates = $this->filterBookingInfoAttributes([
                    'status' => 'PAID',
                    'paid_at' => now(),
                    'payment_gateway' => $gateway,
                    'stripe_session_id' => $sessionId,
                    'stripe_payment_intent_id' => $paymentIntentId,
                ]);

                BookingInfo::where('reference', $reference)->update($paidUpdates);
                BookingInfo::where('booking_reference', $reference)->update($paidUpdates);
                $this->bookingLifecycle->markNeedsAttention(
                    $reference,
                    'globaltix_paid_marking',
                    $e->getMessage(),
                    (string) $e->getCode()
                );
            } catch (\Throwable $inner) {
                Log::error('Failed to persist booking payment info', ['reference' => $reference, 'error' => $inner->getMessage()]);
            }

            return $this->error('Webhook processing failed', 500);
        }

        try {
            // Stamp the webhook event ID first so the idempotency guard inside
            // confirmAfterPayment() can detect duplicate webhook deliveries.
            if ($eventId !== '') {
                $this->filterAndUpdateBooking($bookingContext['reference'], $sessionId, [
                    'stripe_last_webhook_event_id' => $eventId,
                ]);
            }

            // Delegate all confirm + DB-sync logic to the canonical helper.
            // This is the SINGLE place where GlobalTix post-payment confirmation
            // is implemented — no duplication anywhere else.
            $result = $this->globalTixBookingController->confirmAfterPayment(
                $bookingContext['confirmation_code'],
                "Payment captured successfully via " . ucfirst($gateway) . "."
            );

            return response()->json(array_merge($result, [
                'status'   => data_get($result, 'status', 'CONFIRMED'),
                'provider' => 'globaltix',
            ]), 200);

        } catch (\Throwable $e) {
            Log::error('GlobalTix Stripe webhook confirm-after-payment failed', [
                'reference' => $bookingContext['reference'] ?? $reference,
                'error'     => $e->getMessage(),
            ]);

            try {
                $failedReference = (string) ($bookingContext['reference'] ?? $reference);
                if ($failedReference !== '') {
                    $this->bookingLifecycle->markNeedsAttention(
                        $failedReference,
                        'globaltix_confirm_after_payment',
                        $e->getMessage(),
                        (string) $e->getCode()
                    );
                }
            } catch (\Throwable $inner) {
                Log::error('Failed to persist GlobalTix confirmation failure info', [
                    'reference' => $bookingContext['reference'] ?? $reference,
                    'error'     => $inner->getMessage(),
                ]);
            }

            return $this->error('Payment recorded but GlobalTix confirmation failed. Retry webhook or reconcile manually.', 500);
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
