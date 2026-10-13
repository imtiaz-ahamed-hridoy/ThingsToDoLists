<?php

namespace App\Services\Booking;

use App\Models\BookingInfo;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class BookingLifecycleService
{
    public function findByReference(string $reference, array $with = []): ?BookingInfo
    {
        return BookingInfo::with($with)->where(function (Builder $query) use ($reference): void {
            $query->where('booking_reference', $reference)
                ->orWhere('reference', $reference)
                ->orWhere('confirmation_code', $reference)
                ->orWhere('booking_id', $reference)
                ->orWhere('product_booking_id', $reference)
                ->orWhere('product_confirmation_code', $reference);
        })->first();
    }

    public function contextAttributes(Request $request, string $provider, array $payload = []): array
    {
        $user = $request->user('sanctum') ?: $request->user();
        $product = $this->resolveProduct($provider, array_replace_recursive($request->all(), $payload));

        return $this->filterBookingColumns([
            'user_id' => $user instanceof User ? $user->id : null,
            'product_id' => $product?->id,
            'vendor_id' => $product?->vendor_id,
            'booking_channel' => $request->input('booking_channel', 'web'),
            'booking_source' => $user instanceof User ? 'auth_user' : 'guest',
        ]);
    }

    public function markFailure(
        string $reference,
        string $stage,
        string $reason,
        ?string $code = null,
        array $payload = []
    ): void {
        $updates = $this->filterBookingColumns([
            'status' => 'FAILED',
            'failure_stage' => $stage,
            'failure_reason' => $reason,
            'failure_code' => $code,
            'failed_at' => now(),
            'admin_status' => 'needs_attention',
        ]);

        BookingInfo::where(function (Builder $query) use ($reference): void {
            $query->where('reference', $reference)
                ->orWhere('booking_reference', $reference)
                ->orWhere('confirmation_code', $reference);
        })->update($updates);

        BookingInfo::where(function (Builder $query) use ($reference): void {
            $query->where('booking_reference', $reference)
                ->orWhere('reference', $reference)
                ->orWhere('confirmation_code', $reference);
        })->update($updates);

        $booking = $this->findByReference($reference);
        if ($booking) {
            $this->recordEvent($booking, 'booking_failed', [
                'old_status' => $booking->getOriginal('status'),
                'new_status' => 'FAILED',
                'message' => $reason,
                'payload' => array_merge($payload, [
                    'failure_stage' => $stage,
                    'failure_code' => $code,
                ]),
            ]);
        }
    }

    public function markNeedsAttention(
        string $reference,
        string $stage,
        string $reason,
        ?string $code = null,
        array $payload = []
    ): void {
        $updates = $this->filterBookingColumns([
            'failure_stage' => $stage,
            'failure_reason' => $reason,
            'failure_code' => $code,
            'failed_at' => now(),
            'admin_status' => 'needs_attention',
        ]);

        BookingInfo::where(function (Builder $query) use ($reference): void {
            $query->where('reference', $reference)
                ->orWhere('booking_reference', $reference);
        })->update($updates);

        BookingInfo::where(function (Builder $query) use ($reference): void {
            $query->where('booking_reference', $reference)
                ->orWhere('reference', $reference);
        })->update($updates);

        $booking = $this->findByReference($reference);
        if ($booking) {
            $this->recordEvent($booking, 'admin_attention_required', [
                'message' => $reason,
                'payload' => array_merge($payload, [
                    'failure_stage' => $stage,
                    'failure_code' => $code,
                ]),
            ]);
        }
    }

    public function clearFailure(BookingInfo $booking): void
    {
        $updates = $this->filterBookingColumns([
            'failure_stage' => null,
            'failure_reason' => null,
            'failure_code' => null,
            'failed_at' => null,
            'admin_status' => 'open',
        ]);

        BookingInfo::where('reference', $booking->resolved_reference)->update($updates);
        BookingInfo::where('booking_reference', $booking->resolved_reference)->update($updates);
    }

    public function recordEvent(BookingInfo $booking, string $eventType, array $attributes = []): void
    {
        $event = [
            'event_type' => $eventType,
            'old_status' => $attributes['old_status'] ?? null,
            'new_status' => $attributes['new_status'] ?? null,
            'actor_type' => $attributes['actor_type'] ?? null,
            'actor_id' => $attributes['actor_id'] ?? null,
            'message' => $attributes['message'] ?? null,
            'event_payload' => $attributes['payload'] ?? null,
            'created_at' => now()->toIso8601String(),
        ];

        $logs = $booking->events_log ?? [];
        $logs[] = $event;
        $booking->events_log = $logs;
        $booking->save();
    }

    public function filterBookingColumns(array $attributes): array
    {
        static $columns = null;

        if ($columns === null) {
            $columns = array_fill_keys(Schema::getColumnListing((new BookingInfo())->getTable()), true);
        }

        return array_intersect_key($attributes, $columns);
    }

    private function resolveProduct(string $provider, array $payload): ?Product
    {
        foreach (['product_id', 'productId', 'product.id', 'directBooking.product_id', 'bookingRequest.product_id'] as $key) {
            $value = data_get($payload, $key);
            if ($value && is_numeric($value)) {
                $product = Product::find((int) $value);
                if ($product) {
                    return $product;
                }
            }
        }

        foreach ([
            'product_api_id',
            'productApiId',
            'activityId',
            'activity.id',
            'activityApiId',
            'directBooking.activityBookings.0.activityId',
            'directBooking.items.0.activityId',
            'bookingRequest.activityId',
            'bookingRequest.activityBookings.0.activityId',
            'bookingRequest.items.0.activityId',
        ] as $key) {
            $value = data_get($payload, $key);
            if ($value !== null && $value !== '') {
                return Product::where('product_api_id', (string) $value)
                    ->where(function (Builder $query) use ($provider): void {
                        $query->where('provider', $provider)->orWhere('platform', $provider);
                    })
                    ->first();
            }
        }

        return null;
    }
}
