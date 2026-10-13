<?php

namespace App\Http\Controllers\API\Booking;

use App\Http\Controllers\Controller;
use App\Models\BookingInfo;
use App\Services\Booking\BookingLifecycleService;
use App\Services\Payments\StripeCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManualBookingController extends Controller
{
    public function __construct(
        private readonly StripeCheckoutService $stripeService,
        private readonly BookingLifecycleService $bookingLifecycle,
    ) {}

    /**
     * Reserve a Manual booking and create a Stripe Checkout Session.
     */
    public function reserveBooking(Request $request)
    {
        $request->validate([
            'customerName'     => 'sometimes|string|max:255',
            'email'            => 'sometimes|nullable|email',
            'mobileNumber'     => 'sometimes|nullable|string|max:50',
            'visitDate'        => 'sometimes|date_format:Y-m-d',
            'ticketTypes'               => 'required|array|min:1',
            'ticketTypes.*.id'          => 'required|integer',
            'ticketTypes.*.quantity'    => 'required|integer|min:1',
            'ticketTypes.*.price'       => 'required|numeric|min:0',
            'ticketTypes.*.title'       => 'sometimes|string',
            'currency'       => 'sometimes|nullable|string',
            'product_id'     => 'required|integer|exists:products,id',
            'provider'       => 'sometimes|string|in:manual,supplier',
        ]);

        $provider = $request->input('provider', 'supplier');
        $reference = 'MANUAL-' . now()->format('YmdHis') . '-' . strtoupper(str()->random(6));

        try {
            $bookingContext = $this->bookingLifecycle->contextAttributes($request, $provider, $request->all());

            $providerAmount = 0;
            foreach ($request->input('ticketTypes') as $ticket) {
                $providerAmount += ((float)$ticket['price'] * (int)$ticket['quantity']);
            }

            if ($providerAmount <= 0) {
                throw new \RuntimeException('Booking amount must be greater than zero.');
            }

            $providerCurrency = strtoupper(app()->bound('user.currency') ? app('user.currency') : $request->input('currency', 'EUR'));
            $source = (string) $request->input('source', strtoupper($provider) . '_DIRECT');

            $localProduct = \App\Models\Product::find($request->input('product_id'));
            if ($localProduct) {
                $bookingContext['product_id'] = $localProduct->id;
                $bookingContext['vendor_id']  = $localProduct->vendor_id;
            }

            // ── Create Stripe PaymentIntent ────────────────────────────────
            $paymentIntent = $this->stripeService->createPaymentIntent(
                $reference,
                $providerAmount,
                $providerCurrency,
                [
                    'provider' => $provider,
                    'source'   => $source,
                ]
            );

            // ── Persist booking row (RESERVED) ─────────────────────────────
            DB::beginTransaction();

            $bookingAttributes = $this->filterBookingInfoAttributes(array_merge($bookingContext, [
                'reference'              => $reference,
                'booking_reference'      => $reference,
                'confirmation_code'      => $reference,
                'provider_type'          => $provider,
                'status'                 => 'RESERVED',
                'customer_name'          => $request->input('customerName'),
                'customer_email'         => $request->input('email'),
                'customer_phone'         => $request->input('mobileNumber'),
                'booking_currency'       => $providerCurrency,
                'booking_amount'         => $providerAmount,
                'amount'                 => round($providerAmount, 2),
                'total_price'            => $providerAmount,
                'currency'               => $providerCurrency,
                'payment_gateway'        => (string) $request->input('payment_gateway', 'stripe'),
                'payment_source'         => $source,
                'stripe_payment_intent_id' => (string) data_get($paymentIntent, 'id'),
                'provider_raw_data'      => $request->all(),
            ]));

            $booking = BookingInfo::create($bookingAttributes);

            // Sync child ticket types
            foreach ($request->input('ticketTypes') as $ticket) {
                $childAttrs = [
                    'booking_product_id' => $booking->id,
                    'reference'          => $reference . '-T-' . $ticket['id'],
                    'booking_reference'  => $reference,
                    'provider_type'      => $provider,
                    'product_id'         => $booking->product_id,
                    'type'               => 'ticket',
                    'title'              => data_get($ticket, 'title', 'Ticket'),
                    'quantity'           => $ticket['quantity'],
                    'price'              => $ticket['price'],
                    'total_price'        => (float)$ticket['price'] * (int)$ticket['quantity'],
                    'currency'           => $providerCurrency,
                    'date'               => $request->input('visitDate'),
                    'status'             => 'RESERVED',
                    'provider_raw_data'  => $ticket,
                ];
                BookingInfo::create($childAttrs);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Booking reserved successfully.',
                'data' => [
                    'referenceNumber' => $reference,
                    'amount' => $providerAmount,
                    'currency' => $providerCurrency,
                    'stripe_client_secret' => data_get($paymentIntent, 'client_secret'),
                ],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to reserve booking: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Release/Cancel a reserved booking locally
     */
    public function releaseBooking(Request $request)
    {
        $request->validate([
            'referenceNumber' => 'required|string',
            'reason'          => 'sometimes|string',
        ]);

        $reference = $request->input('referenceNumber');

        $booking = BookingInfo::where('reference', $reference)
            ->orWhere('booking_reference', $reference)
            ->first();

        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        if (strtoupper($booking->status) !== 'RESERVED') {
            return response()->json(['success' => false, 'message' => 'Only RESERVED bookings can be released via this endpoint.'], 422);
        }

        $booking->update([
            'status' => 'CANCELLED',
            'cancellation_reason' => $request->input('reason', 'User abandoned checkout or failed payment.'),
            'cancelled_at' => now(),
        ]);

        // Cancel children
        BookingInfo::where('booking_product_id', $booking->id)
            ->whereNull('event_type')
            ->update([
                'status' => 'CANCELLED',
                'cancelled_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Booking released successfully.',
        ]);
    }

    /**
     * Show booking details
     */
    public function show(string $reference)
    {
        $booking = BookingInfo::with(['details', 'payment', 'product'])
            ->whereNull('event_type')
            ->where(function ($q) use ($reference) {
                $q->where('booking_reference', $reference)
                  ->orWhere('reference', $reference)
                  ->orWhere('confirmation_code', $reference);
            })
            ->first();

        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $booking,
        ]);
    }

    private function filterBookingInfoAttributes(array $attributes): array
    {
        $fillable = (new BookingInfo())->getFillable();
        return array_intersect_key($attributes, array_flip($fillable));
    }
}
