<?php

namespace App\Http\Controllers\API\Booking;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\BookingInfo;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingLookupController extends Controller
{
    use ApiResponse;

    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $reference = $validated['reference'];
        $email = strtolower($validated['email']);

        $booking = BookingInfo::with([
                'details',
                'payment',
                'product.reviews' => fn ($q) => $q->where('status', 'Active')->with('users')->latest(),
                'product.productImages',
                'events',
            ])
            ->where(function ($query) use ($reference): void {
                $query->where('booking_reference', $reference)
                    ->orWhere('reference', $reference)
                    ->orWhere('confirmation_code', $reference)
                    ->orWhere('product_confirmation_code', $reference)
                    ->orWhere('booking_id', $reference)
                    ->orWhere('product_booking_id', $reference);
            })
            ->where(function ($query) use ($email): void {
                $query->whereRaw('LOWER(customer_email) = ?', [$email])
                    ->orWhereRaw('LOWER(payer_email) = ?', [$email]);
            })
            ->first();

        if (! $booking) {
            return $this->error('Booking not found for the provided reference and email.', 404);
        }

        return $this->success('Booking fetched successfully.', (new BookingResource($booking))->resolve($request));
    }
}
