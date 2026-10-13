<?php

use App\Http\Controllers\API\Auth\LoginController;
use App\Http\Controllers\API\Auth\RegisterController;
use App\Http\Controllers\API\Creator\CreatorAuthController;
use App\Http\Controllers\API\Creator\CreatorPortalController;
use App\Http\Controllers\API\Auth\ProfileUpdateController;
use App\Http\Controllers\API\Auth\SupplierAuthController;
use App\Http\Controllers\API\Dashboard\DashboardOverviewController;
use App\Http\Controllers\API\Review\ReviewController;
use App\Http\Controllers\API\Supplier\SupplierBookingController;
use App\Http\Controllers\API\Supplier\SupplierDashboardController;
use App\Http\Controllers\API\Supplier\SupplierFinanceController;
use App\Http\Controllers\API\Supplier\SupplierProductController;
use App\Http\Controllers\API\Supplier\SupplierProductCreationController;
use App\Http\Controllers\API\Supplier\SupplierProfileController;
use App\Http\Controllers\API\Supplier\SupplierReviewController;
use App\Http\Controllers\API\User\UserController;
use App\Http\Controllers\API\User\UserBookingController;
use App\Http\Controllers\API\Search\SearchController;
// Admin dashboard routes are handled in routes/backend.php (web), not in API auth routes.
use Illuminate\Support\Facades\Route;

// Guest Routes
Route::group(['middleware' => 'guest:sanctum'], function () {
    Route::post('login', [LoginController::class, 'login']);
    Route::post('register', [RegisterController::class, 'register']);
    Route::post('email/verify/resend', [RegisterController::class, 'resendEmailVerifyOTP'])->middleware('throttle:2,1');
    Route::post('email/verify', [RegisterController::class, 'verifyEmail']);
    Route::post('password/forgot', [RegisterController::class, 'forgotPassword'])->middleware('throttle:3,1');
    Route::post('password/forgot/verify', [RegisterController::class, 'verifyForgotPasswordOTP']);
    Route::post('password/reset', [RegisterController::class, 'resetPassword'])->middleware('throttle:5,1');

    // Supplier Portals Routes
    Route::post('supplier/login', [SupplierAuthController::class, 'login']);
    Route::post('supplier/register', [SupplierAuthController::class, 'register']);

    // Content Creator Portals Routes
    Route::post('creator/login', [CreatorAuthController::class, 'login']);
    Route::post('creator/register', [CreatorAuthController::class, 'register']);
    Route::post('creator/email/verify', [CreatorAuthController::class, 'verifyEmail']);
    Route::post('creator/email/verify/resend', [CreatorAuthController::class, 'resendEmailVerifyOTP'])->middleware('throttle:2,1');
    Route::post('creator/forgot-password', [CreatorAuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
    Route::post('creator/password/forgot/verify', [CreatorAuthController::class, 'verifyForgotPasswordOTP']);
    Route::post('creator/password/reset', [CreatorAuthController::class, 'resetPassword'])->middleware('throttle:5,1');
});


Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::post('/logout/all', [LoginController::class, 'logoutAll']);
    Route::post('/profile', [LoginController::class, 'profileUpdate']);
    Route::post('/password/change', [LoginController::class, 'changePassword']);
    Route::get('/profile', [LoginController::class, 'profile']);

    // profile update routes
    Route::post('/change-password', [ProfileUpdateController::class, 'changePassword']);
    Route::post('/account-delete', [ProfileUpdateController::class, 'accountDelete']);

    Route::post('/profile-avatar-upload', [ProfileUpdateController::class, 'profileAvatarUpload']);
    Route::post('/profile-avatar-remove', [ProfileUpdateController::class, 'profileAvatarRemove']);

    // ==================== REVIEWS ====================
    Route::get('/reviews', [ReviewController::class, 'index']);
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::post('/reviews/{reviews}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{reviews}', [ReviewController::class, 'destroy']);

    // ==================== USER MANAGEMENT ====================
    Route::get('/user/bookings', [UserBookingController::class, 'index']);
    Route::get('/user/bookings/{reference}', [UserBookingController::class, 'show']);

    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

    // ==================== DASHBOARD OVERVIEW ====================
    Route::get('/dashboard/overview', [DashboardOverviewController::class, 'index']);

    // Admin dashboard routes (web) are implemented in routes/backend.php.

    // ==================== SUPPLIER DASHBOARD FRONTEND ROUTES ====================

    // ==================== SUPPLIER DASHBOARD FRONTEND ROUTES ====================
    Route::group(['middleware' => ['role:supplier,vendor']], function () {
        Route::prefix('supplier-dashboard')->group(function () {
            Route::controller(SupplierDashboardController::class)->group(function () {
                Route::get('home', 'index');
            });

            Route::prefix('products')->controller(SupplierProductController::class)->group(function () {
                Route::get('/', 'index');
                Route::delete('{id}', 'destroy');
            });

            Route::controller(SupplierProductCreationController::class)->prefix('create-products')->group(function () {
                Route::get('/', 'index');
                Route::get('draft/{product?}', 'resume');
                Route::post('save-draft/{product?}', 'saveDraft');
                Route::post('submit/{product?}', 'submit');
                Route::post('{product}/photos/upload', 'uploadPhoto');
                Route::delete('{product}/photos/{photo}', 'deletePhoto');
                Route::put('{product}/photos/reorder', 'reorderPhotos');
            });

            Route::controller(SupplierBookingController::class)->group(function () {
                Route::get('bookings', 'index');
            });

            Route::controller(SupplierReviewController::class)->group(function () {
                Route::get('reviews', 'index');
            });

            Route::controller(SupplierFinanceController::class)->group(function () {
                Route::get('finance', 'index');
            });
        });
    });

    // ==================== SUPPLIER SUPPORT APIS ====================
    Route::group(['middleware' => ['role:supplier,vendor']], function () {
        Route::prefix('supplier')->group(function () {
            Route::controller(SupplierProfileController::class)->prefix('profile')->group(function () {
                Route::get('/', 'show');
                Route::put('/', 'update');
                Route::post('avatar', 'uploadAvatar');
            });

            Route::controller(SupplierFinanceController::class)->prefix('finance')->group(function () {
                Route::get('payouts', 'payouts');
                Route::get('invoices', 'invoices');
                Route::get('payment-confirmations', 'paymentConfirmations');
                Route::get('tax-details', 'getTaxDetails');
                Route::put('tax-details', 'updateTaxDetails');
            });
        });
    });

    Route::group(['middleware' => ['role:creator']], function () {
        Route::group(['prefix' => 'creator-portal'], function () {
            Route::get('/', [CreatorPortalController::class, 'landing']);
            Route::get('/global-application', [CreatorPortalController::class, 'communityApplication']);
            Route::post('/global-application', [CreatorPortalController::class, 'storeCommunityApplication']);
            Route::get('/sponsor-application', [CreatorPortalController::class, 'sponsoredExperience']);
            Route::post('/sponsor-application', [CreatorPortalController::class, 'storeSponsoredExperience']);
        });
    });

    // ==================== RECENTLY VIEWED TRACKING ====================
    Route::post('/user/recently-viewed', [SearchController::class, 'recordRecentlyViewed']);
    Route::get('/user/recently-viewed', [SearchController::class, 'getRecentlyViewedAuth']);
});
