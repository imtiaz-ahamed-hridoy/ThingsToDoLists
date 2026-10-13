<?php

namespace App\Http\Controllers\API\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessGlobalTixBookingWebhookJob;
use App\Jobs\ProcessGlobalTixProductWebhookJob;
use App\Models\AvailabilityCategoryPrice;
use App\Models\AvailabilityCheck;
use App\Models\BookingInfo;
use App\Models\Location;
use App\Models\OptionPricingCategory;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProviderWebhookLog;
use App\Models\TempProduct;
use App\Models\User;
use App\Notifications\AdminSystemNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * GlobalTix Webhook Controller
 *
 * Official docs: https://app.swaggerhub.com/apis-docs/GlobalTix-GT/Webhook/1.0.0
 *
 * Each event type has its own Notification URL + Notification Secret
 * configured in GlobalTix Dashboard (Settings → Webhook).
 *
 * Signature: HmacSHA256("eventType|timestamp", notificationSecret)
 *
 * BOOKING tab:
 *   /booking-ticket-update       → BOOKING_TICKET_UPDATE
 *   /booking-transaction-update  → BOOKING_TRANSACTION_UPDATE
 *   /booking-ticket-redeem       → TICKET_REDEEM
 *   /booking-ticket-revoke       → TICKET_REVOKE
 *   /booking-ticket-expired      → TICKET_EXPIRED
 *
 * PRODUCT tab:
 *   /product-info-update         → PRODUCT_INFO_UPDATE
 *   /ticket-type-price-update    → PRICE_UPDATE
 */
class GlobalTixWebhookController extends Controller
{
    private const EVENT_BOOKING_TICKET_UPDATE = 'BOOKING_TICKET_UPDATE';
    private const EVENT_BOOKING_TRANSACTION_UPDATE = 'BOOKING_TRANSACTION_UPDATE';
    private const EVENT_TICKET_REDEEM = 'TICKET_REDEEM';
    private const EVENT_TICKET_REVOKE = 'TICKET_REVOKE';
    private const EVENT_TICKET_EXPIRED = 'TICKET_EXPIRED';
    private const EVENT_PRODUCT_INFO_UPDATE = 'PRODUCT_INFO_UPDATE';
    private const EVENT_PRICE_UPDATE = 'PRICE_UPDATE';

    private const EVENT_ALIASES = [
        'BOOKING_TICKET_UPDATE' => self::EVENT_BOOKING_TICKET_UPDATE,
        'BOOKING_TRANSACTION_UPDATE' => self::EVENT_BOOKING_TRANSACTION_UPDATE,
        'TICKET_REDEEM' => self::EVENT_TICKET_REDEEM,
        'BOOKING_TICKET_REDEEM' => self::EVENT_TICKET_REDEEM,
        'TICKET_REVOKE' => self::EVENT_TICKET_REVOKE,
        'BOOKING_TICKET_REVOKE' => self::EVENT_TICKET_REVOKE,
        'TICKET_EXPIRED' => self::EVENT_TICKET_EXPIRED,
        'BOOKING_TICKET_EXPIRED' => self::EVENT_TICKET_EXPIRED,
        'PRODUCT_INFO_UPDATE' => self::EVENT_PRODUCT_INFO_UPDATE,
        'PRICE_UPDATE' => self::EVENT_PRICE_UPDATE,
        'TICKET_TYPE_PRICE_UPDATE' => self::EVENT_PRICE_UPDATE,
        'TICKET_PRICE_UPDATE' => self::EVENT_PRICE_UPDATE,
    ];

    private const TERMINAL_BOOKING_STATUSES = [
        'REDEEMED',
        'REVOKED',
        'EXPIRED',
        'REJECTED',
    ];
    // ─── BOOKING Tab Endpoints ───────────────────────────────────────

    /**
     * POST /webhook/globaltix/booking-ticket-update
     * eventType: BOOKING_TICKET_UPDATE
     * data: { referenceNumber, eTicketUrl, tickets[{ code, status }] }
     */
    public function bookingTicketUpdate(Request $request): JsonResponse
    {
        return $this->handleEvent($request, self::EVENT_BOOKING_TICKET_UPDATE);
    }

    /**
     * POST /webhook/globaltix/booking-transaction-update
     * eventType: BOOKING_TRANSACTION_UPDATE
     * data: { referenceNumber, eTicketUrl, isTicketsReady, completedTime }
     */
    public function bookingTransactionUpdate(Request $request): JsonResponse
    {
        return $this->handleEvent($request, self::EVENT_BOOKING_TRANSACTION_UPDATE);
    }

    /**
     * POST /webhook/globaltix/booking-ticket-redeem
     * eventType: TICKET_REDEEM
     * data: { referenceNumber, tickets[{ id, code, quantity }] }
     */
    public function bookingTicketRedeem(Request $request): JsonResponse
    {
        return $this->handleEvent($request, self::EVENT_TICKET_REDEEM);
    }

    /**
     * POST /webhook/globaltix/booking-ticket-revoke
     * eventType: TICKET_REVOKE
     * data: { referenceNumber, id, code, timestamp, remarks }
     */
    public function bookingTicketRevoke(Request $request): JsonResponse
    {
        return $this->handleEvent($request, self::EVENT_TICKET_REVOKE);
    }

    /**
     * POST /webhook/globaltix/booking-ticket-expired
     * eventType: TICKET_EXPIRED
     * data: { referenceNumber, id, code, expiredDate }
     */
    public function bookingTicketExpired(Request $request): JsonResponse
    {
        return $this->handleEvent($request, self::EVENT_TICKET_EXPIRED);
    }

    // ─── PRODUCT Tab Endpoints ───────────────────────────────────────

    /**
     * POST /webhook/globaltix/product-info-update
     * eventType: PRODUCT_INFO_UPDATE
     * data: { productId, countryId, cityId, name, lastUpdated }
     */
    public function productInfoUpdate(Request $request): JsonResponse
    {
        return $this->handleEvent($request, self::EVENT_PRODUCT_INFO_UPDATE);
    }

    /**
     * POST /webhook/globaltix/ticket-type-price-update
     * eventType: PRICE_UPDATE
     * data: { productId, optionId, ticketTypeId, newNettPrice, currency }
     */
    public function ticketTypePriceUpdate(Request $request): JsonResponse
    {
        return $this->handleEvent($request, self::EVENT_PRICE_UPDATE);
    }

    /**
     * Legacy catch-all: POST /webhook/globaltix
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        return $this->handleEvent($request, 'UNKNOWN');
    }

    // ─── Core Handler ────────────────────────────────────────────────

    protected function handleEvent(Request $request, string $expectedEventType): JsonResponse
    {
        $payload = $request->json()->all();
        if (!is_array($payload) || $payload === []) {
            $payload = $request->all();
        }

        $payloadEventRaw = strtoupper(trim((string) ($payload['eventType'] ?? '')));
        $payloadEvent = $this->canonicalizeEventType($payloadEventRaw);
        $expectedEvent = strtoupper(trim($expectedEventType));
        $expectedCanonical = $expectedEvent === 'UNKNOWN'
            ? null
            : $this->canonicalizeEventType($expectedEvent);

        $eventType = $payloadEvent ?: ($expectedCanonical ?: $payloadEventRaw ?: 'UNKNOWN');

        $webhookLog = ProviderWebhookLog::create([
            'provider' => 'globaltix',
            'event'    => $eventType,
            'status'   => 'received',
            'headers'  => $request->headers->all(),
            'payload'  => $payload,
        ]);

        if ($payloadEventRaw === '' || $payloadEvent === null) {
            $message = 'Missing or unsupported eventType in GlobalTix webhook payload.';

            $webhookLog->update([
                'status'        => 'rejected',
                'error_message' => $message,
                'skipped'       => 1,
                'result'        => [
                    'expected_event' => $expectedCanonical,
                    'payload_event'  => $payloadEventRaw ?: null,
                ],
            ]);

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        if ($expectedCanonical !== null && $payloadEvent !== $expectedCanonical) {
            $message = "Webhook eventType mismatch. Expected {$expectedCanonical}, received {$payloadEvent}.";

            $webhookLog->update([
                'status'        => 'rejected',
                'error_message' => $message,
                'skipped'       => 1,
                'result'        => [
                    'expected_event' => $expectedCanonical,
                    'payload_event'  => $payloadEvent,
                ],
            ]);

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        if (!$this->verifySignature($payload, $payloadEvent)) {
            Log::channel('daily')->warning("GlobalTix UNAUTHORIZED [{$eventType}]", [
                'requestId' => $payload['requestId'] ?? null,
                'ip'        => $request->ip(),
            ]);

            $webhookLog->update([
                'status'        => 'unauthorized',
                'error_message' => "Signature verification failed for {$payloadEvent}.",
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized webhook request.',
            ], 401);
        }

        if (in_array($payloadEvent, [self::EVENT_PRODUCT_INFO_UPDATE, self::EVENT_PRICE_UPDATE], true)) {
            $webhookLog->update([
                'status' => 'queued',
                'result' => [
                    'message' => 'GlobalTix product webhook queued for background processing.',
                ],
            ]);

            ProcessGlobalTixProductWebhookJob::dispatch(
                $webhookLog->id,
                $payloadEvent,
                (array) ($payload['data'] ?? [])
            );



            return response()->json([
                'success' => true,
                'event'   => $payloadEvent,
                'message' => "Webhook [{$payloadEvent}] queued for background processing.",
            ], 202);
        }

        if (in_array($payloadEvent, [
            self::EVENT_BOOKING_TICKET_UPDATE,
            self::EVENT_BOOKING_TRANSACTION_UPDATE,
            self::EVENT_TICKET_REDEEM,
            self::EVENT_TICKET_REVOKE,
            self::EVENT_TICKET_EXPIRED,
        ], true)) {
            $webhookLog->update([
                'status' => 'queued',
                'result' => [
                    'message' => 'GlobalTix booking webhook queued for background processing.',
                ],
            ]);

            ProcessGlobalTixBookingWebhookJob::dispatch(
                $webhookLog->id,
                $payloadEvent,
                (array) ($payload['data'] ?? [])
            );

  
            // Notify admins
            $admins = User::where('role', 'Admin')->get();
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new AdminSystemNotification(
                    'GlobalTix Booking Webhook Queued',
                    "Booking Webhook [{$payloadEvent}] is queued for background processing.",
                    'info'
                ));
            }

            return response()->json([
                'success' => true,
                'event'   => $payloadEvent,
                'message' => "Webhook [{$payloadEvent}] queued for background processing.",
            ], 202);
        }

        try {
            $summary = $this->processEventData($payloadEvent, (array) ($payload['data'] ?? []));

            $status = $summary['failed'] > 0
                ? 'error'
                : (($summary['processed'] > 0 || $summary['updated'] > 0 || $summary['created'] > 0) ? 'processed' : 'skipped');

            $webhookLog->update([
                'status'    => $status,
                'processed' => (int) ($summary['processed'] ?? 0),
                'created'   => (int) ($summary['created'] ?? 0),
                'updated'   => (int) ($summary['updated'] ?? 0),
                'skipped'   => (int) ($summary['skipped'] ?? 0),
                'failed'    => (int) ($summary['failed'] ?? 0),
                'result'    => $summary['result'] ?? [],
            ]);

            return response()->json([
                'success' => true,
                'event'   => $payloadEvent,
                'message' => "Webhook [{$payloadEvent}] processed.",
                'summary' => $summary,
            ], 200);
        } catch (Throwable $e) {
            Log::channel('daily')->error("GlobalTix Process Error [{$payloadEvent}]", [
                'error' => $e->getMessage(),
            ]);

            $webhookLog->update([
                'status'        => 'error',
                'error_message' => $e->getMessage(),
                'failed'        => 1,
            ]);

            return response()->json([
                'success' => false,
                'event'   => $payloadEvent,
                'message' => 'Webhook processing failed. Please retry.',
            ], 500);
        }
    }

    public function processEventData(string $eventType, array $data): array
    {
        return match ($eventType) {
            self::EVENT_BOOKING_TICKET_UPDATE,
            self::EVENT_BOOKING_TRANSACTION_UPDATE => $this->handleBookingUpdate($eventType, $data),
            self::EVENT_TICKET_REDEEM => $this->handleBookingStatus($eventType, $data, 'REDEEMED'),
            self::EVENT_TICKET_REVOKE => $this->handleBookingStatus($eventType, $data, 'REVOKED'),
            self::EVENT_TICKET_EXPIRED => $this->handleBookingStatus($eventType, $data, 'EXPIRED'),
            self::EVENT_PRODUCT_INFO_UPDATE => $this->handleProductInfoUpdate($data),
            self::EVENT_PRICE_UPDATE => $this->handlePriceUpdate($data),
            default => $this->summary(
                skipped: 1,
                result: ['reason' => "Unsupported event type: {$eventType}"],
            ),
        };
    }

    protected function handleBookingUpdate(string $eventType, array $data): array
    {
        $reference = trim((string) ($data['referenceNumber'] ?? ''));
        if ($reference === '') {
            return $this->summary(
                skipped: 1,
                result: ['reason' => 'Missing referenceNumber for booking update'],
            );
        }

        $booking = $this->findBookingByReference($reference);
        if (!$booking) {
            return $this->summary(
                skipped: 1,
                result: ['reason' => 'Booking not found', 'referenceNumber' => $reference],
            );
        }

        $didChange = false;
        $changes = [];

        DB::transaction(function () use ($eventType, $data, $booking, &$didChange, &$changes): void {
            $eTicketUrl = trim((string) ($data['eTicketUrl'] ?? ''));
            if ($eTicketUrl !== '' && $booking->e_ticket_url !== $eTicketUrl) {
                $booking->e_ticket_url = $eTicketUrl;
                $didChange = true;
                $changes[] = 'e_ticket_url';
            }

            $incomingTickets = is_array($data['tickets'] ?? null) ? $data['tickets'] : [];
            if ($incomingTickets !== []) {
                $mergedTickets = $this->mergeTicketsByCode(
                    is_array($booking->globaltix_tickets) ? $booking->globaltix_tickets : [],
                    $incomingTickets
                );

                if ($mergedTickets !== (is_array($booking->globaltix_tickets) ? $booking->globaltix_tickets : [])) {
                    $booking->globaltix_tickets = $mergedTickets;
                    $didChange = true;
                    $changes[] = 'globaltix_tickets';
                }
            }

            $candidateStatus = null;
            if ($eventType === self::EVENT_BOOKING_TRANSACTION_UPDATE) {
                if (!empty($data['isTicketsReady'])) {
                    $candidateStatus = 'READY';
                }
            } else {
                $candidateStatus = $this->inferStatusFromTicketUpdate($data);
            }

            if ($candidateStatus !== null) {
                $statusChanged = $this->applyBookingStatus(
                    $booking,
                    $candidateStatus,
                    in_array($candidateStatus, ['REDEEMED', 'REVOKED', 'EXPIRED', 'REJECTED'], true)
                );

                if ($statusChanged) {
                    $didChange = true;
                    $changes[] = 'status';
                }
            }

            $rawChanged = $this->appendWebhookSnapshot($booking, $eventType, $data);
            if ($rawChanged) {
                $didChange = true;
                $changes[] = 'provider_raw_data';
            }

            if ($didChange) {
                $booking->save();
            }
        });

        return $this->summary(
            processed: $didChange ? 1 : 0,
            updated: $didChange ? 1 : 0,
            skipped: $didChange ? 0 : 1,
            result: [
                'referenceNumber' => $reference,
                'status'          => $booking->status,
                'changes'         => array_values(array_unique($changes)),
            ],
        );
    }

    protected function handleBookingStatus(string $eventType, array $data, string $status): array
    {
        $reference = trim((string) ($data['referenceNumber'] ?? ''));
        if ($reference === '') {
            return $this->summary(
                skipped: 1,
                result: ['reason' => "Missing referenceNumber for {$eventType}"],
            );
        }

        $booking = $this->findBookingByReference($reference);
        if (!$booking) {
            return $this->summary(
                skipped: 1,
                result: ['reason' => 'Booking not found', 'referenceNumber' => $reference],
            );
        }

        DB::transaction(function () use ($eventType, $data, $booking, $status): void {
            $ticketUpdates = $this->extractTicketUpdatesForStatusEvent($data, $status);
            if ($ticketUpdates !== []) {
                $booking->globaltix_tickets = $this->mergeTicketsByCode(
                    is_array($booking->globaltix_tickets) ? $booking->globaltix_tickets : [],
                    $ticketUpdates
                );
            }

            $this->applyBookingStatus($booking, $status, true);
            $this->appendWebhookSnapshot($booking, $eventType, $data);
            $booking->save();
        });

        return $this->summary(
            processed: 1,
            updated: 1,
            result: [
                'referenceNumber' => $reference,
                'status'          => $status,
            ],
        );
    }

    protected function handleProductInfoUpdate(array $data): array
    {
        $productId = trim((string) ($data['productId'] ?? ''));
        if ($productId === '') {
            return $this->summary(
                skipped: 1,
                result: ['reason' => 'Missing productId for PRODUCT_INFO_UPDATE'],
            );
        }

        $title = trim((string) ($data['name'] ?? ''));
        $countryCode = trim((string) ($data['countryCode'] ?? ''));
        $cityId = trim((string) ($data['cityId'] ?? ''));

        $updated = 0;
        $result = [
            'productId' => $productId,
        ];

        if ($title !== '') {
            $tempUpdated = TempProduct::where('product_api_id', $productId)
                ->where('api_platform', 'globaltix')
                ->update(['title' => $title]);

            $productUpdated = Product::where('product_api_id', $productId)
                ->where('provider', 'globaltix')
                ->update([
                    'title' => $title,
                    'last_synced_at' => now(),
                ]);

            $updated += $tempUpdated + $productUpdated;
            $result['title'] = $title;
            $result['temp_products_updated'] = $tempUpdated;
            $result['products_updated'] = $productUpdated;
        }

        if ($countryCode !== '' || $cityId !== '') {
            $productIds = Product::where('product_api_id', $productId)
                ->where('provider', 'globaltix')
                ->pluck('id');

            if ($productIds->isNotEmpty()) {
                $locationUpdates = [];
                if ($countryCode !== '') {
                    $locationUpdates['countryCode'] = strtoupper($countryCode);
                }
                if ($cityId !== '') {
                    $locationUpdates['city_id'] = $cityId;
                }

                if ($locationUpdates !== []) {
                    $locationUpdated = Location::whereIn('product_id', $productIds)->update($locationUpdates);
                    $updated += $locationUpdated;
                    $result['locations_updated'] = $locationUpdated;
                }
            }
        }

        if ($updated === 0) {
            return $this->summary(
                skipped: 1,
                result: array_merge($result, ['reason' => 'No matching product records updated']),
            );
        }

        return $this->summary(
            processed: $updated,
            updated: $updated,
            result: $result,
        );
    }

    protected function handlePriceUpdate(array $data): array
    {
        $productId = trim((string) ($data['productId'] ?? ''));
        $newNettPrice = $data['newNettPrice'] ?? null;

        if ($productId === '' || !is_numeric($newNettPrice)) {
            return $this->summary(
                skipped: 1,
                result: ['reason' => 'Missing productId or numeric newNettPrice for PRICE_UPDATE'],
            );
        }

        $optionExternalId = trim((string) ($data['optionId'] ?? ''));
        $ticketTypeExternalId = trim((string) ($data['ticketTypeId'] ?? ''));
        $currency = strtoupper(trim((string) ($data['currency'] ?? '')));
        $nettPrice = round((float) $newNettPrice, 2);

        $productIds = Product::where('provider', 'globaltix')
            ->where('product_api_id', $productId)
            ->pluck('id');

        if ($productIds->isEmpty()) {
            return $this->summary(
                skipped: 1,
                result: ['reason' => 'No matching Product rows found', 'productId' => $productId],
            );
        }

        $optionIds = collect();
        if ($optionExternalId !== '') {
            $optionIds = ProductOption::whereIn('product_id', $productIds)
                ->where('provider', 'globaltix')
                ->where('external_id', $optionExternalId)
                ->pluck('id');

            if ($optionIds->isEmpty()) {
                return $this->summary(
                    skipped: 1,
                    result: [
                        'reason'   => 'No matching ProductOption rows found for optionId',
                        'productId' => $productId,
                        'optionId' => $optionExternalId,
                    ],
                );
            }
        }

        $categoryIds = collect();
        if ($ticketTypeExternalId !== '') {
            $categoryQuery = OptionPricingCategory::where('external_id', $ticketTypeExternalId);
            if ($optionIds->isNotEmpty()) {
                $categoryQuery->whereIn('product_option_id', $optionIds);
            } else {
                $categoryQuery->whereIn(
                    'product_option_id',
                    ProductOption::whereIn('product_id', $productIds)
                        ->where('provider', 'globaltix')
                        ->select('id')
                );
            }

            $categoryIds = $categoryQuery->pluck('id');

            if ($categoryIds->isEmpty()) {
                return $this->summary(
                    skipped: 1,
                    result: [
                        'reason'       => 'No matching pricing category found for ticketTypeId',
                        'productId'    => $productId,
                        'ticketTypeId' => $ticketTypeExternalId,
                    ],
                );
            }
        }

        $priceUpdate = ['nett_price' => $nettPrice];
        if ($currency !== '') {
            $priceUpdate['currency'] = $currency;
        }

        $priceQuery = Price::where('provider', 'globaltix')
            ->whereIn('product_id', $productIds);

        if ($optionIds->isNotEmpty()) {
            $priceQuery->whereIn('product_option_id', $optionIds);
        }
        if ($categoryIds->isNotEmpty()) {
            $priceQuery->whereIn('pricing_category_id', $categoryIds);
        }

        $updatedPriceRows = $priceQuery->update($priceUpdate);

        $availabilityPriceUpdate = ['nett_price' => $nettPrice];
        if ($currency !== '') {
            $availabilityPriceUpdate['currency'] = $currency;
        }

        $updatedAvailabilityCategoryRows = 0;
        if ($categoryIds->isNotEmpty()) {
            $updatedAvailabilityCategoryRows = AvailabilityCategoryPrice::whereIn('pricing_category_id', $categoryIds)
                ->update($availabilityPriceUpdate);
        } else {
            $availabilityQuery = AvailabilityCheck::whereIn('product_id', $productIds)
                ->where('provider', 'globaltix');

            if ($optionIds->isNotEmpty()) {
                $availabilityQuery->whereIn('product_option_id', $optionIds);
            }

            $availabilityIds = $availabilityQuery->pluck('id');
            if ($availabilityIds->isNotEmpty()) {
                $updatedAvailabilityCategoryRows = AvailabilityCategoryPrice::whereIn('availability_id', $availabilityIds)
                    ->update($availabilityPriceUpdate);
            }
        }

        $totalUpdated = $updatedPriceRows + $updatedAvailabilityCategoryRows;
        if ($totalUpdated === 0) {
            return $this->summary(
                skipped: 1,
                result: [
                    'reason'       => 'No matching price rows found to update',
                    'productId'    => $productId,
                    'optionId'     => $optionExternalId ?: null,
                    'ticketTypeId' => $ticketTypeExternalId ?: null,
                ],
            );
        }

        return $this->summary(
            processed: $totalUpdated,
            updated: $totalUpdated,
            result: [
                'productId'                          => $productId,
                'optionId'                           => $optionExternalId ?: null,
                'ticketTypeId'                       => $ticketTypeExternalId ?: null,
                'newNettPrice'                       => $nettPrice,
                'currency'                           => $currency ?: null,
                'price_rows_updated'                 => $updatedPriceRows,
                'availability_category_rows_updated' => $updatedAvailabilityCategoryRows,
            ],
        );
    }

    // ─── Signature Verification ──────────────────────────────────────

    protected function verifySignature(array $payload, string $eventType): bool
    {
        $secret = $this->resolveWebhookSecret($eventType);
        $strict = (bool) config('services.globaltix.webhook_require_signature', false);

        if (empty($secret)) {
            Log::channel('daily')->warning("No GlobalTix webhook secret configured for [{$eventType}]");
            return !$strict;
        }

        $signature = trim((string) ($payload['signature'] ?? ''));
        $payloadEventRaw = trim((string) ($payload['eventType'] ?? ''));
        $timestamp = trim((string) ($payload['timestamp'] ?? ''));

        if ($signature === '' || $payloadEventRaw === '' || $timestamp === '') {
            Log::channel('daily')->warning("Missing signature fields for [{$eventType}] verification", [
                'has_signature' => $signature !== '',
                'has_eventType' => $payloadEventRaw !== '',
                'has_timestamp' => $timestamp !== '',
            ]);
            return false;
        }

        $expected = hash_hmac('sha256', "{$payloadEventRaw}|{$timestamp}", $secret);
        $isValid = hash_equals(strtolower($expected), strtolower($signature));

        if (!$isValid) {
            Log::channel('daily')->warning("Signature mismatch for [{$eventType}]", [
                'expected_signature' => $expected,
                'received_signature' => $signature,
                'formula'            => "{$payloadEventRaw}|{$timestamp}",
            ]);
        }

        return $isValid;
    }

    protected function resolveWebhookSecret(string $eventType): ?string
    {
        $eventSecrets = config('services.globaltix.webhook_secrets', []);
        if (is_array($eventSecrets)) {
            $eventSecret = trim((string) ($eventSecrets[$eventType] ?? ''));
            if ($eventSecret !== '') {
                return $eventSecret;
            }
        }

        $fallback = trim((string) config('services.globaltix.webhook_secret', ''));
        return $fallback !== '' ? $fallback : null;
    }

    protected function canonicalizeEventType(string $eventType): ?string
    {
        $normalized = strtoupper(trim($eventType));
        if ($normalized === '') {
            return null;
        }

        return self::EVENT_ALIASES[$normalized] ?? null;
    }

    protected function findBookingByReference(string $reference): ?BookingInfo
    {
        return BookingInfo::where(function ($query) use ($reference): void {
            $query->where('booking_reference', $reference)
                ->orWhere('reference', $reference);
        })->first();
    }

    protected function inferStatusFromTicketUpdate(array $data): ?string
    {
        $tickets = is_array($data['tickets'] ?? null) ? $data['tickets'] : [];
        $statuses = [];

        foreach ($tickets as $ticket) {
            if (!is_array($ticket)) {
                continue;
            }

            $status = strtoupper(trim((string) ($ticket['status'] ?? '')));
            if ($status !== '') {
                $statuses[] = $status;
            }
        }

        if (in_array('REDEEMED', $statuses, true)) {
            return 'REDEEMED';
        }
        if (in_array('REVOKED', $statuses, true)) {
            return 'REVOKED';
        }
        if (in_array('EXPIRED', $statuses, true)) {
            return 'EXPIRED';
        }
        if (in_array('REJECTED', $statuses, true)) {
            return 'REJECTED';
        }

        if (!empty($data['eTicketUrl']) || !empty($tickets)) {
            return 'READY';
        }

        return null;
    }

    protected function applyBookingStatus(BookingInfo $booking, string $nextStatus, bool $force = false): bool
    {
        $next = strtoupper(trim($nextStatus));
        if ($next === '') {
            return false;
        }

        $current = strtoupper(trim((string) $booking->status));

        if (!$force && in_array($current, self::TERMINAL_BOOKING_STATUSES, true) && $current !== $next) {
            return false;
        }

        if ($current === $next) {
            return false;
        }

        $booking->status = $next;
        return true;
    }

    protected function appendWebhookSnapshot(BookingInfo $booking, string $eventType, array $data): bool
    {
        $raw = is_array($booking->provider_raw_data) ? $booking->provider_raw_data : [];
        $webhooks = is_array($raw['webhooks'] ?? null) ? $raw['webhooks'] : [];

        $webhooks[$eventType] = [
            'received_at' => now()->toISOString(),
            'data'        => $data,
        ];

        $raw['webhooks'] = $webhooks;
        $booking->provider_raw_data = $raw;

        return true;
    }

    protected function extractTicketUpdatesForStatusEvent(array $data, string $status): array
    {
        $updates = [];
        $tickets = is_array($data['tickets'] ?? null) ? $data['tickets'] : [];

        foreach ($tickets as $ticket) {
            if (!is_array($ticket)) {
                continue;
            }

            $code = trim((string) ($ticket['code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $ticket['status'] = $status;
            $updates[] = $ticket;
        }

        if ($updates !== []) {
            return $updates;
        }

        $singleCode = trim((string) ($data['code'] ?? ''));
        if ($singleCode !== '') {
            $updates[] = [
                'id'     => $data['id'] ?? null,
                'code'   => $singleCode,
                'status' => $status,
            ];
        }

        return $updates;
    }

    protected function mergeTicketsByCode(array $existing, array $incoming): array
    {
        $indexed = [];
        $withoutCode = [];

        foreach ($existing as $ticket) {
            if (!is_array($ticket)) {
                continue;
            }

            $code = strtoupper(trim((string) ($ticket['code'] ?? '')));
            if ($code === '') {
                $withoutCode[] = $ticket;
                continue;
            }

            $indexed[$code] = $ticket;
        }

        foreach ($incoming as $ticket) {
            if (!is_array($ticket)) {
                continue;
            }

            $code = strtoupper(trim((string) ($ticket['code'] ?? '')));
            if ($code === '') {
                $withoutCode[] = $ticket;
                continue;
            }

            $ticket['code'] = $ticket['code'] ?? $code;
            $indexed[$code] = array_merge($indexed[$code] ?? [], $ticket);
        }

        return array_values(array_merge($indexed, $withoutCode));
    }

    protected function summary(
        int $processed = 0,
        int $created = 0,
        int $updated = 0,
        int $skipped = 0,
        int $failed = 0,
        array $result = []
    ): array {
        return [
            'processed' => max(0, $processed),
            'created'   => max(0, $created),
            'updated'   => max(0, $updated),
            'skipped'   => max(0, $skipped),
            'failed'    => max(0, $failed),
            'result'    => $result,
        ];
    }
}
