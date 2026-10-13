<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\EmailLog;
use App\Models\Faq;
use App\Models\BookingInfo;
use App\Models\Product;
use App\Models\Review;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the dashboard page.
     *
     * @return View
     */


    public function index(): View
    {
        if (Auth::check() && (Auth::user()->role === 'Admin' || Auth::user()->role === 'Support')) {

            $admins = User::where('role', 'Admin')->count();
            $users = User::where('role', '!=', 'Admin')->count();
            $email = EmailLog::where('status', 'success')->count();
            $review=Review::where('status', 'Active')->count();
            $product=Product::where('status', 'Active')->count();
            $faq=Faq::where('status', 'Active')->count();
            $activityLog=Activity::count();
            $booking=BookingInfo::count();
            $newsletterSubscribers = NewsletterSubscriber::count();
            $failedBooking=BookingInfo::where(function ($query): void {
                $query->where('status', 'FAILED')
                    ->orWhere('admin_status', 'needs_attention')
                    ->orWhereNotNull('failure_reason');
            })->count();

            //dd(Auth::user());
            return view('backend.layouts.dashboard.index',
             compact('admins', 'users', 'email', 'review', 'product', 'faq', 'activityLog', 'booking', 'failedBooking', 'newsletterSubscribers'));
        }else{
            return view('auth.layouts.login');
        }
    }
}
