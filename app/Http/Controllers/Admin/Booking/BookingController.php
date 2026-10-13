<?php

namespace App\Http\Controllers\Admin\Booking;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\BookingInfo;
use App\Services\Booking\BookingPolicyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function index(Request $request): View
    {
        $query = BookingInfo::with(['details', 'product'])
            ->whereNull('booking_product_id')
            ->whereNull('event_type')
            ->latest('id');

        if ($request->filled('status')) {
            $query->where('status', strtoupper((string) $request->status));
        }

        if ($request->boolean('failed_only')) {
            $query->where(function ($query): void {
                $query->where('status', 'FAILED')
                    ->orWhere('admin_status', 'needs_attention')
                    ->orWhereNotNull('failure_reason');
            });
        }

        if ($request->filled('search')) {
            $search = (string) $request->search;
            $query->where(function ($query) use ($search): void {
                $query->where('booking_reference', 'LIKE', "%{$search}%")
                    ->orWhere('reference', 'LIKE', "%{$search}%")
                    ->orWhere('confirmation_code', 'LIKE', "%{$search}%")
                    ->orWhere('customer_name', 'LIKE', "%{$search}%")
                    ->orWhere('customer_email', 'LIKE', "%{$search}%");
            });
        }

        return view('backend.layouts.bookings.index', [
            'bookings' => $query->paginate(20)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('backend.layouts.bookings.create');
    }

    public function show(string $reference): View
    {
        $booking = BookingInfo::with(['details', 'payment', 'product', 'events'])
            ->whereNull('event_type')
            ->where(function ($q) use ($reference): void {
                $q->where('booking_reference', $reference)
                    ->orWhere('reference', $reference)
                    ->orWhere('confirmation_code', $reference);
            })
            ->firstOrFail();

        $policy = app(BookingPolicyService::class)->evaluate($booking);

        // Fetch activity logs from the Spatie activity_log table related to this booking
        $activityLogs = collect();
        try {
            $resolvedRef = $booking->resolved_reference;

            $activityLogs = Activity::with('causer')
                ->where(function ($q) use ($booking, $resolvedRef): void {
                    $q->where('subject_type', BookingInfo::class)
                        ->where('subject_id', $booking->id);
                })
                ->orWhere(function ($q) use ($resolvedRef): void {
                    $q->where('properties', 'LIKE', "%{$resolvedRef}%");
                })
                ->orWhere(function ($q) use ($booking): void {
                    $q->where('description', 'LIKE', "%booking%{$booking->id}%")
                        ->orWhere('description', 'LIKE', "%{$booking->booking_reference}%")
                        ->orWhere('description', 'LIKE', "%{$booking->reference}%");
                })
                ->latest('created_at')
                ->limit(50)
                ->get();
        } catch (\Throwable $e) {
            // Activity log table may not exist or have issues — don't block the page
            Log::error('Failed to fetch activity logs for booking: ' . $e->getMessage());
        }

        // Fetch other bookings by the same customer (by email), excluding the current one
        $customerBookings = collect();
        if ($booking->customer_email) {
            $customerBookings = BookingInfo::with('product')
                ->where('customer_email', $booking->customer_email)
                ->whereNull('event_type')  // parent bookings only
                ->where('id', '!=', $booking->id)
                ->latest('created_at')
                ->limit(20)
                ->get(['id', 'reference', 'booking_reference', 'confirmation_code', 'product_id',
                       'title', 'provider_type', 'status', 'total_price', 'currency',
                       'quantity', 'date', 'created_at', 'paid_at', 'confirmed_at',
                       'cancelled_at', 'admin_status']);
        }

        return view('backend.layouts.bookings.show', [
            'booking' => $booking,
            'policy' => $policy,
            'activityLogs' => $activityLogs,
            'customerBookings' => $customerBookings,
        ]);
    }

    public function invoice(string $reference): View
    {
        $booking = BookingInfo::with(['details', 'payment', 'product'])
            ->whereNull('event_type')
            ->where(function ($q) use ($reference): void {
                $q->where('booking_reference', $reference)
                    ->orWhere('reference', $reference)
                    ->orWhere('confirmation_code', $reference);
            })
            ->firstOrFail();

        $policy = app(BookingPolicyService::class)->evaluate($booking);

        return view('backend.layouts.bookings.invoice', [
            'booking' => $booking,
            'policy' => $policy,
        ]);
    }
}
