<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\BookingInfo;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponse;

class UserBookingController extends Controller
{
    use ApiResponse;
    /**
     * Get user bookings based on type (upcoming, past, cancelled, failed)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $email = $user->email;
        $type = strtolower((string) $request->query('type', 'upcoming'));
        $type = $type === 'new' ? 'upcoming' : $type;

        // Query product rows (those with total_price set) so that
        // details(), payment(), and product() relationships work correctly.
        $query = BookingInfo::select([
            'id', 'user_id', 'product_id', 'vendor_id', 'reference', 'booking_reference',
            'confirmation_code', 'booking_id', 'product_booking_id', 'product_confirmation_code',
            'provider_type', 'provider_status', 'status', 'admin_status', 'booking_channel',
            'booking_source', 'customer_name', 'customer_email', 'customer_phone', 'total_price',
            'currency', 'payment_gateway', 'payment_source', 'stripe_session_id', 'stripe_payment_intent_id',
            'paypal_order_id', 'paypal_capture_id', 'paid_at', 'confirmed_at', 'failure_stage',
            'failure_code', 'failure_reason', 'failed_at', 'cancelled_at', 'cancelled_by_type',
            'cancellation_reason', 'refund_status', 'refund_amount', 'refund_currency', 'refund_gateway',
            'refund_reference', 'refund_reason', 'refunded_at', 'e_ticket_url', 'globaltix_tickets',
            'created_at', 'updated_at'
        ])
            ->with([
                'details',
                'payment',
                'product.reviews' => fn ($q) => $q->where('status', 'Active')->with('users')->latest(),
                'product.productImages',
            ])
            ->where('total_price', '>', 0)
            ->where(function ($query) use ($user, $email): void {
                $query->where('user_id', $user->id)
                    ->orWhere('customer_email', $email);
            });

        if ($request->filled('status')) {
            $query->where('status', strtoupper((string) $request->query('status')));
        }

        if ($request->filled('provider_type')) {
            $query->where('provider_type', strtolower((string) $request->query('provider_type')));
        }

        if ($type === 'cancelled') {
            $query->where('status', 'CANCELLED');
        } elseif ($type === 'failed') {
            $query->where(function ($query): void {
                $query->where('status', 'FAILED')
                    ->orWhereNotNull('failure_reason')
                    ->orWhere('admin_status', 'needs_attention');
            });
        } elseif ($type === 'past') {
            $query->where('status', '!=', 'CANCELLED')
                ->where(function ($q) {
                    $q->whereHas('details', function ($q) {
                        $q->where('date', '<', now()->toDateString());
                    })->orWhereDoesntHave('details');
                });
        } else {
            // upcoming
            $query->where('status', '!=', 'CANCELLED')
                ->where(function ($q) {
                    $q->whereHas('details', function ($q) {
                        $q->where('date', '>=', now()->toDateString());
                    })->orWhereDoesntHave('details');
                });
        }

        $perPage = (int) $request->query('per_page', 10);
        $bookings = $query->latest('id')->paginate(max(1, min($perPage, 100)));

        return $this->success('Bookings fetched successfully.', [
            'bookings' => collect($bookings->items())->map(fn ($b) => (new BookingResource($b))->resolve($request))->all(),
            'meta'    => [
                'current_page' => $bookings->currentPage(),
                'last_page'    => $bookings->lastPage(),
                'per_page'     => $bookings->perPage(),
                'total'        => $bookings->total(),
            ],
        ]);
    }

    /**
     * Get details of a specific user booking
     *
     * @param string $reference
     * @return JsonResponse
     */
    public function show(string $reference): JsonResponse
    {
        $user = auth()->user();

        // First try to find a product row by reference/confirmation code
        // Product rows have total_price set and correct relationship bindings.
        $booking = BookingInfo::with([
                'details',
                'payment',
                'product.reviews' => fn ($q) => $q->where('status', 'Active')->with('users')->latest(),
                'product.productImages',
                'events',
            ])
            ->where('total_price', '>', 0)
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhere('customer_email', $user->email);
            })
            ->where(function ($query) use ($reference) {
                $query->where('reference', $reference)
                    ->orWhere('booking_reference', $reference)
                    ->orWhere('confirmation_code', $reference)
                    ->orWhere('product_confirmation_code', $reference);
            })
            ->first();

        // Fallback: try to find by any booking_info row (payment row)
        if (!$booking) {
            $booking = BookingInfo::with([
                'details',
                'payment',
                'product.reviews' => fn ($q) => $q->where('status', 'Active')->with('users')->latest(),
                'product.productImages',
                'events',
            ])
                ->where(function ($query) use ($user): void {
                    $query->where('user_id', $user->id)
                        ->orWhere('customer_email', $user->email);
                })
                ->where(function ($query) use ($reference) {
                    $query->where('reference', $reference)
                        ->orWhere('booking_reference', $reference)
                        ->orWhere('confirmation_code', $reference)
                        ->orWhere('id', $reference);
                })
                ->first();
        }

        if (!$booking) {
            return $this->error('Booking not found or does not belong to you.', 404);
        }

        return $this->success('Booking details fetched successfully.', (new BookingResource($booking))->resolve(request()));
    }
}
