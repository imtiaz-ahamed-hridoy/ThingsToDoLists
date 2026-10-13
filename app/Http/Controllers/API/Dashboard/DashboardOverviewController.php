<?php

namespace App\Http\Controllers\API\Dashboard;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Models\BookingInfo;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DashboardOverviewController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $data = [
            'total_products'    => Product::count(),
            'active_products'   => Product::where('status', 'Active')->count(),
            'inactive_products' => Product::where('status', 'Inactive')->count(),
            'total_users'       => User::where('role', 'User')->count(),
            'total_reviews'     => Review::count(),
            'total_bookings'    => BookingInfo::count(),
            'paid_bookings'     => BookingInfo::where('status', 'PAID')->count(),
            'confirmed_bookings'=> BookingInfo::where('status', 'CONFIRMED')->count(),
            'failed_bookings'   => BookingInfo::where(function ($query): void {
                $query->where('status', 'FAILED')
                    ->orWhere('admin_status', 'needs_attention')
                    ->orWhereNotNull('failure_reason');
            })->count(),
            'refund_pending_bookings' => BookingInfo::where('refund_status', 'PENDING')->count(),
        ];

        return $this->success('Dashboard overview fetched successfully', $data, 200);
    }
}
