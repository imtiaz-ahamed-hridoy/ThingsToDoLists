<?php

use App\Http\Controllers\API\Activity\{BokunActivityController, GlobalTixActivityController, GlobalTixProxyController};
use App\Http\Controllers\API\AvailabilityCheck\AvailabilityCheckController;
use App\Http\Controllers\API\Booking\{BookingLookupController, GlobalTixBookingController, BokunBookingController, StripeWebhookController, ManualBookingController};
use App\Http\Controllers\API\CurrencyController;
use App\Http\Controllers\API\DestinationHighlightApiController;
use App\Http\Controllers\API\DynamicPage\DynamicPageController;
use App\Http\Controllers\API\Faq\FaqController;
use App\Http\Controllers\API\Home\BlogApiController;
use App\Http\Controllers\API\Home\BlogCategoryApiController;
use App\Http\Controllers\API\Home\HomePageBannerApiController;
use App\Http\Controllers\API\Home\HomePageSectionsApiController;
use App\Http\Controllers\API\Home\TestimonialApiController;
use App\Http\Controllers\API\Location\LocationListController;
use App\Http\Controllers\API\Newsletter\NewsletterController;
use App\Http\Controllers\API\Product\ProductController;
use App\Http\Controllers\API\Product\WishlistApiController;
use App\Http\Controllers\API\Provider\{CheckAvailabilityController, ProductDetailsController, ProductRawDataController};
use App\Http\Controllers\API\Search\SearchController;
use App\Http\Controllers\API\SystemSetting\SystemSettingController;
use App\Http\Controllers\API\Webhook\GlobalTixWebhookController;
use Illuminate\Support\Facades\Route;

// Route for System Setting
Route::get('/system-setting', [SystemSettingController::class, 'systemSetting']);
Route::get('/home-page-sections', [HomePageSectionsApiController::class, 'index']);
Route::get('/home-page-sections/{id}', [HomePageSectionsApiController::class, 'show']);
// Homepage banners
Route::get('/home-page-banners', [HomePageBannerApiController::class, 'index']);

// NOTE: Home page settings API removed per request

// Blog public APIs
Route::get('/blogs', [BlogApiController::class, 'index']);
Route::get('/blogs/featured', [BlogApiController::class, 'featured']);
Route::get('/blogs/latest', [BlogApiController::class, 'latest']);
Route::get('/blogs/search', [BlogApiController::class, 'search']);
Route::get('/blogs/{slug}', [BlogApiController::class, 'show']);
Route::get('/blog-categories', [BlogCategoryApiController::class, 'index']);


Route::get('/testimonials-settings', [TestimonialApiController::class, 'index']);
Route::get('/testimonials-settings/show/{id}', [TestimonialApiController::class, 'show']);

// Destination Highlights
Route::get('/destination-highlights/detail/{id}', [DestinationHighlightApiController::class, 'show']);
Route::get('/destination-highlights/{destinationSlug}', [DestinationHighlightApiController::class, 'index']);

// Currencies
Route::get('/currencies', [CurrencyController::class, 'index']);
Route::post('/currencies/set', [CurrencyController::class, 'setCurrency']);

// Languages
Route::get('/languages', [\App\Http\Controllers\API\LanguageController::class, 'getSupportedLanguages']);



// Location lists
Route::get('/city/list', [LocationListController::class, 'cities']);
Route::get('/country/list', [LocationListController::class, 'countries']);


//faq related controller
Route::get('/faq', [FaqController::class, 'index']);
Route::get('/faq/show/{id}', [FaqController::class, 'show']);

// Dynamic Pages routes
Route::get('/pages', [DynamicPageController::class, 'list']);
Route::get('/pages/{id}', [DynamicPageController::class, 'show']);

// route for products DB-saving
Route::get('/product/category-wise', [ProductController::class, 'categoryWiseProducts']);
Route::get('/product/list', [ProductController::class, 'index']);
Route::match(['get', 'post'], '/product/show/{id?}', [ProductController::class, 'show']);
Route::get('/product/similar/{id}', [ProductController::class, 'similarProducts']);
Route::get('/product/top-in-city/{id}', [ProductController::class, 'topInCity']);
Route::get('/product/nearby-cities/{id}', [ProductController::class, 'nearbyCities']);
Route::post('/availability/check', [AvailabilityCheckController::class, 'checkAvailability']);
Route::post('/bookings/lookup', [BookingLookupController::class, 'show'])->middleware('throttle:10,1');

// product search
Route::get('/search', [SearchController::class, 'search']);
Route::get('/search/global', [SearchController::class, 'cardSearch']);
Route::get('/search/card', [SearchController::class, 'cardSearch']);
Route::get('/search/city/{city}', [SearchController::class, 'cityPage']);
Route::get('/search/collection/{slug}', [SearchController::class, 'collectionPage']);
Route::get('/search/destination/{slug}', [SearchController::class, 'destinationPage']);
Route::get('/search/trending', [SearchController::class, 'trending']);
Route::get('/search/recently-viewed', [SearchController::class, 'recentlyViewedPublic']);

// ==================== CHECK THINGS REAL (Real-Time Provider APIs) ====================
Route::post('/check-availability', [CheckAvailabilityController::class, 'checkAvailability']);
Route::post('/product-details', ProductDetailsController::class);
Route::post('/product-raw-data', ProductRawDataController::class);

// ==================== ACTIVITIES ====================
Route::post('/bokun/activities', [BokunActivityController::class, 'getActivities']);
Route::get('/bokun/activities/{activityId}', [BokunActivityController::class, 'getActivity']);
Route::post('/bokun/activities/availability', [BokunActivityController::class, 'checkAvailability']);
Route::get('/bokun/activities/pickup-places/{activityId}', [BokunActivityController::class, 'getPickupPlaces']);

// ==================== GLOBALTIX ACTIVITIES ====================
Route::post('/globaltix/activities', [GlobalTixActivityController::class, 'getActivities']);
Route::get('/globaltix/activities/{activityId}', [GlobalTixActivityController::class, 'getActivity']);
Route::post('/globaltix/activities/availability', [GlobalTixActivityController::class, 'checkAvailability']);
Route::get('/globaltix/proxy/product-options', [GlobalTixProxyController::class, 'productOptions']);

// ==================== GLOBALTIX BOOKING ====================
Route::post('/globaltix/booking/reserve', [GlobalTixBookingController::class, 'reserveBooking']);
// NOTE: Confirmation is handled automatically by the Stripe webhook (/webhook/stripe).
//       The webhook calls GlobalTix /api/booking/confirm after payment is captured.
Route::post('/globaltix/booking/release', [GlobalTixBookingController::class, 'releaseBooking']);
Route::get('/globaltix/booking/details', [GlobalTixBookingController::class, 'getBookingInfos']);
Route::get('/globaltix/booking/show/{reference}', [GlobalTixBookingController::class, 'show']);

// ==================== MANUAL / SUPPLIER BOOKING ====================
Route::post('/manual-booking/reserve', [ManualBookingController::class, 'reserveBooking']);
Route::post('/manual-booking/release', [ManualBookingController::class, 'releaseBooking']);
Route::get('/manual-booking/show/{reference}', [ManualBookingController::class, 'show']);

// ==================== GLOBALTIX WEBHOOKS ====================
// Official GlobalTix Webhook endpoints
// Docs: https://app.swaggerhub.com/apis-docs/GlobalTix-GT/Webhook/1.0.0
// Each event has its own Notification URL + Notification Secret in GlobalTix Dashboard
Route::prefix('webhook/globaltix')->group(function () {
    // BOOKING tab events
    Route::post('/booking-ticket-update',       [GlobalTixWebhookController::class, 'bookingTicketUpdate']);
    Route::post('/booking-transaction-update',   [GlobalTixWebhookController::class, 'bookingTransactionUpdate']);
    Route::post('/booking-ticket-redeem',        [GlobalTixWebhookController::class, 'bookingTicketRedeem']);
    Route::post('/booking-ticket-revoke',        [GlobalTixWebhookController::class, 'bookingTicketRevoke']);
    Route::post('/booking-ticket-expired',       [GlobalTixWebhookController::class, 'bookingTicketExpired']);
    // Alias paths (if dashboard uses non-booking-prefixed URLs)
    Route::post('/ticket-redeem',                [GlobalTixWebhookController::class, 'bookingTicketRedeem']);
    Route::post('/ticket-revoke',                [GlobalTixWebhookController::class, 'bookingTicketRevoke']);
    Route::post('/ticket-expired',               [GlobalTixWebhookController::class, 'bookingTicketExpired']);
    // PRODUCT tab events
    Route::post('/product-info-update',          [GlobalTixWebhookController::class, 'productInfoUpdate']);
    Route::post('/ticket-type-price-update',     [GlobalTixWebhookController::class, 'ticketTypePriceUpdate']);
    Route::post('/price-update',                 [GlobalTixWebhookController::class, 'ticketTypePriceUpdate']);
    // Legacy catch-all
    Route::post('/',                             [GlobalTixWebhookController::class, 'handleWebhook']);
});


//  Paypal routes
// Route::post('/paypal/checkout-options', [DirectBookingController::class, 'getCheckoutOptions']);
// Route::post('/paypal/reserve', [DirectBookingController::class, 'reserveWithPaypal']);
// Route::post('/paypal/capture', [DirectBookingController::class, 'paypalCapture']);
// Route::post('/paypal/webhook', [DirectBookingController::class, 'paypalWebhook']);

// ==================== Bokun Booking (Stripe Flow) ====================
Route::post('/bokun-booking/checkout-options', [BokunBookingController::class, 'getCheckoutOptions']);
Route::post('/bokun-booking/submit-checkout', [BokunBookingController::class, 'submitCheckout']);
Route::get('/bokun-booking/show/{reference}', [BokunBookingController::class, 'show']);
Route::get('/bokun-booking/{confirmationCode}/abort-reserved', [BokunBookingController::class, 'abortReservedBooking']);
Route::post('/bokun-booking/{confirmationCode}/payment-error', [BokunBookingController::class, 'paymentError']);
Route::match(['get', 'post'], '/bokun-booking/cancel-booking/{confirmationCode}', [BokunBookingController::class, 'cancelBooking']);
Route::get('/bokun-booking/{id}/summary', [BokunBookingController::class, 'bookingSummary']);
Route::get('/bokun-booking/activity-booking/{productConfirmationCode}/ticket', [BokunBookingController::class, 'activityBookingTicket']);
Route::post('/webhook/stripe', [StripeWebhookController::class, 'handle']);

// ==================== NEWSLETTER ====================
Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe']);

// ==================== WISHLIST ====================
// Single endpoint handles both guests (via X-Guest-Wishlist header) and auth users (via Bearer token).
// auth:sanctum is used as optional here — if no token is present, guard returns null and the
// controller falls back to the X-Guest-Wishlist header. This avoids having two duplicate routes.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/wishlist', [WishlistApiController::class, 'index']);
    Route::post('/wishlist/toggle', [WishlistApiController::class, 'toggle']);
});

// Guest-only wishlist endpoints (no Bearer token required — use X-Guest-Wishlist header)
Route::get('/wishlist/guest', [WishlistApiController::class, 'index']);
Route::post('/wishlist/guest/toggle', [WishlistApiController::class, 'toggle']);

