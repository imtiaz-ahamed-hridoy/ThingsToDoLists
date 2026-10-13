<?php

namespace App\Traits;

use App\Models\BookingInfo;
use Illuminate\Support\Facades\Schema;

trait SyncBookingInfosTrait
{
    private function syncBookingInfosFromProvider(BookingInfo $product, array $providerConfirmResponse, ?int $paymentId): void
    {
        if (!Schema::hasTable((new BookingInfo())->getTable())) {
            return;
        }

        $items = data_get($providerConfirmResponse, 'activityBookings')
            ?? data_get($providerConfirmResponse, 'productBookings')
            ?? data_get($providerConfirmResponse, 'booking.activityBookings')
            ?? data_get($providerConfirmResponse, 'booking.productBookings')
            ?? data_get($providerConfirmResponse, 'booking.bookedProducts')
            ?? [];

        if (empty($items)) {
            return;
        }

        $product->details()->delete();

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $date = data_get($item, 'date');
            if (is_numeric($date)) {
                $date = strlen((string) $date) === 13 
                    ? \Carbon\Carbon::createFromTimestampMs($date)->format('Y-m-d H:i:s')
                    : \Carbon\Carbon::createFromTimestamp($date)->format('Y-m-d H:i:s');
            } else {
                try { 
                    $date = \Carbon\Carbon::parse($date)->format('Y-m-d H:i:s'); 
                } catch (\Throwable) { 
                    $date = null; 
                }
            }

            BookingInfo::create([
                'booking_product_id' => $product->id,
                'booking_payment_id' => $paymentId,
                'user_id' => $product->user_id,
                'product_id' => $product->product_id,
                'vendor_id' => $product->vendor_id,
                'product_booking_id' => (string) (data_get($item, 'bookingId') ?? data_get($item, 'activityBookingId') ?? data_get($item, 'productBookingId')),
                'provider_type' => $product->provider_type ?? 'bokun',
                'status' => $product->status,
                'type' => data_get($item, 'type') ?? 'activity',
                'title' => data_get($item, 'title') ?? data_get($item, 'productTitle'),
                'price' => (float) (data_get($item, 'totalPrice') ?? data_get($item, 'price') ?? 0),
                'currency' => $product->currency,
                'date' => $date ? \Carbon\Carbon::parse($date)->format('Y-m-d') : null,
                'start_time' => data_get($item, 'startTime'),
                'quantity' => (int) (data_get($item, 'quantity') ?? (data_get($item, 'passengers') ? count((array) data_get($item, 'passengers', [])) : 1)),
                'provider_raw_data' => $item,
            ]);
        }
    }
}
