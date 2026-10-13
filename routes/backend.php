<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Settings\StripeKeyController;
use App\Http\Controllers\Admin\CMS\TestimonialCmsController;
use App\Http\Controllers\Admin\CMS\HomePageSectionController;
use App\Http\Controllers\Admin\Product\{ProductController, TempProductController, CategoryController, GlobalTixAdminController};
use App\Http\Controllers\Admin\Content\{DynamicPageController, FaqController, ReviewController};
use App\Http\Controllers\Admin\Communication\{MailController, EmailLogController};
use App\Http\Controllers\Admin\System\{CitiesController, ActivityController};
use App\Http\Controllers\Admin\User\UserController;
use App\Http\Controllers\Admin\Booking\AvailabilityCheckController;
use App\Http\Controllers\Admin\Booking\BookingController;
use App\Http\Controllers\API\Admin\BookingController as AdminBookingApiController;
use App\Http\Controllers\Admin\DestinationHighlightController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\QueueMonitorController;
use App\Http\Controllers\Admin\CMS\HomePageBannerController;
use App\Http\Controllers\Admin\CMS\BlogPostController;
use App\Http\Controllers\Admin\Product\BokunAdminController;

// ==================== DASHBOARD ====================
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

// ==================== NOTIFICATIONS ====================
Route::get('/notifications', [NotificationController::class, 'index'])->name('admin.notifications.index');
Route::post('/notifications/mark-as-read/{id?}', [NotificationController::class, 'markAsRead'])->name('admin.notifications.read');

// ==================== USERS ====================
Route::get('/user', [UserController::class, 'index'])->name('user.index');
Route::get('/user/create', [UserController::class, 'create'])->name('user.create');
Route::get('/user/{id}', [UserController::class, 'show'])->name('user.show');
Route::post('/user/store', [UserController::class, 'store'])->name('user.store');
Route::get('/user/edit/{id}', [UserController::class, 'edit'])->name('user.edit');
Route::patch('/user/update/{id}', [UserController::class, 'update'])->name('user.update');
Route::delete('/user/destroy/{id}', [UserController::class, 'destroy'])->name('user.destroy');

// ==================== DYNAMIC PAGES ====================
Route::get('/page', [DynamicPageController::class, 'index'])->name('page.index');
Route::get('/page/create', [DynamicPageController::class, 'create'])->name('page.create');
Route::post('/page/store', [DynamicPageController::class, 'store'])->name('page.store');
Route::get('/page/edit/{id}', [DynamicPageController::class, 'edit'])->name('page.edit');
Route::patch('/page/update/{id}', [DynamicPageController::class, 'update'])->name('page.update');
Route::delete('/page/destroy/{id}', [DynamicPageController::class, 'destroy'])->name('page.destroy');

// ==================== MAIL TEMPLATES ====================
Route::get('/mail/template', [MailController::class, 'index'])->name('mail.index');
Route::get('/mail/template/create', [MailController::class, 'create'])->name('mail.create');
Route::post('/mail/template/create', [MailController::class, 'store'])->name('mail.store');
Route::get('/mail/template/edit/{id}', [MailController::class, 'edit'])->name('mail.edit');
Route::post('/mail/template/update/{id}', [MailController::class, 'update'])->name('mailTemp.update');
Route::delete('/mail/template/delete/{id}', [MailController::class, 'destroy'])->name('mail.destroy');

// ==================== EMAIL LOG ====================
Route::get('/mail', [EmailLogController::class, 'index'])->name('email.index');
Route::get('/mail/send', [EmailLogController::class, 'create'])->name('email.create');
Route::post('/mail/send', [EmailLogController::class, 'sendBulkMail'])->name('send.bulk.mail');
Route::delete('/mail/delete/{id}', [EmailLogController::class, 'destroy'])->name('email.destroy');

// ==================== FAQ ====================
Route::get('/faq', [FaqController::class, 'index'])->name('faq.index');
Route::get('/faq/create', [FaqController::class, 'create'])->name('faq.create');
Route::post('/faq/create', [FaqController::class, 'store'])->name('faq.store');
Route::get('/faq/{id}', [FaqController::class, 'show'])->name('faq.show');
Route::get('/faq/edit/{id}', [FaqController::class, 'edit'])->name('faq.edit');
Route::put('/faq/update/{id}', [FaqController::class, 'update'])->name('faq.update');
Route::delete('/faq/delete/{id}', [FaqController::class, 'destroy'])->name('faq.destroy');

// ==================== REVIEWS ====================
Route::get('/review', [ReviewController::class, 'index'])->name('review.index');
Route::get('/review/edit/{id}', [ReviewController::class, 'edit'])->name('review.edit');
Route::put('/review/update/{id}', [ReviewController::class, 'update'])->name('review.update');
Route::delete('/review/delete/{id}', [ReviewController::class, 'destroy'])->name('review.destroy');

// ==================== ACTIVITY LOGS ====================
Route::get('/activity-logs', [ActivityController::class, 'index'])->name('activity.index');
Route::delete('/activity/delete/{id}', [ActivityController::class, 'destroy'])->name('activity.destroy');

// ==================== STRIPE ====================
Route::get('/stripe/edit', [StripeKeyController::class, 'edit'])->name('stripe.edit');
Route::put('/stripe/update', [StripeKeyController::class, 'update'])->name('stripe.update');

// ==================== CITIES ====================
Route::get('/cities', [CitiesController::class, 'index'])->name('cities.index');
Route::get('/cities/create', [CitiesController::class, 'create'])->name('cities.create');
Route::post('/cities/store', [CitiesController::class, 'store'])->name('cities.store');
Route::get('/cities/edit/{id}', [CitiesController::class, 'edit'])->name('cities.edit');
Route::put('/cities/update/{id}', [CitiesController::class, 'update'])->name('cities.update');
Route::delete('/cities/destroy/{id}', [CitiesController::class, 'destroy'])->name('cities.destroy');

// ==================== CATEGORIES ====================
Route::get('/category', [CategoryController::class, 'index'])->name('category.index');
Route::get('/category/create', [CategoryController::class, 'create'])->name('category.create');
Route::post('/category/store', [CategoryController::class, 'store'])->name('category.store');
Route::get('/category/edit/{id}', [CategoryController::class, 'edit'])->name('category.edit');
Route::put('/category/update/{id}', [CategoryController::class, 'update'])->name('category.update');
Route::delete('/category/destroy/{id}', [CategoryController::class, 'destroy'])->name('category.destroy');

// ==================== DESTINATION HIGHLIGHTS ====================

Route::get('/destination-highlights', [DestinationHighlightController::class, 'index'])->name('destination-highlights.index');
Route::get('/destination-highlights/create', [DestinationHighlightController::class, 'create'])->name('destination-highlights.create');
Route::post('/destination-highlights', [DestinationHighlightController::class, 'store'])->name('destination-highlights.store');
Route::get('/destination-highlights/load-products', [DestinationHighlightController::class, 'loadProducts'])->name('destination-highlights.load-products');
Route::get('/destination-highlights/{id}/edit', [DestinationHighlightController::class, 'edit'])->name('destination-highlights.edit');
Route::put('/destination-highlights/{id}', [DestinationHighlightController::class, 'update'])->name('destination-highlights.update');
Route::delete('/destination-highlights/{id}', [DestinationHighlightController::class, 'destroy'])->name('destination-highlights.destroy');

// ==================== TEMP PRODUCTS (Bokun / GlobalTix Import) ====================
Route::get('/temp-products', [TempProductController::class, 'index'])->name('tempProduct.index');
Route::get('/temp-products/bokun/active-ids', [TempProductController::class, 'getActiveBokunIds'])->name('tempProduct.bokun.activeIds');
Route::post('/temp-products/bokun/queue-active', [TempProductController::class, 'queueActiveBokunActivities'])->name('tempProduct.bokun.queueActive');
Route::post('/temp-products/bokun/process-chunk', [TempProductController::class, 'processBokunIdChunk'])->name('tempProduct.bokun.processChunk');
Route::post('/temp-products/search', [TempProductController::class, 'search'])->name('tempProduct.search');
Route::post('/temp-products/import-by-api-id', [TempProductController::class, 'importByApiId'])->name('tempProduct.importByApiId');
Route::post('/temp-products/bulk-import', [TempProductController::class, 'bulkImport'])->name('tempProduct.bulkImport');
Route::get('/temp-products/get/{id}', [TempProductController::class, 'getActivity'])->name('tempProduct.get');
Route::get('/temp-products/{id}/availability', [TempProductController::class, 'getAvailability'])->name('tempProduct.availability');
Route::get('/temp-products-list', [TempProductController::class, 'getAllProduct'])->name('tempProduct.list');
Route::delete('/temp-products/destroy/{id}', [TempProductController::class, 'destroy'])->name('tempProduct.destroy');
Route::match(['delete', 'post'], '/temp-products/bulk-delete', [TempProductController::class, 'bulkDelete'])->name('tempProduct.bulkDelete');
Route::get('/temp-products/globaltix/categories', [TempProductController::class, 'getGlobalTixCategories'])->name('tempProduct.globaltix.categories');
Route::get('/temp-products/globaltix/countries', [TempProductController::class, 'getGlobalTixCountries'])->name('tempProduct.globaltix.countries');
Route::get('/temp-products/globaltix/categories', [TempProductController::class, 'getGlobalTixCategories'])->name('tempProduct.globaltix.categories');
Route::get('/temp-products/globaltix/cities', [TempProductController::class, 'getGlobalTixCities'])->name('tempProduct.globaltix.cities');
Route::post('/temp-products/globaltix/new-product-ids', [TempProductController::class, 'getGlobalTixNewProductIds'])->name('tempProduct.globaltix.newProductIds');
Route::post('/temp-products/globaltix/process-chunk', [TempProductController::class, 'processGlobalTixIdChunk'])->name('tempProduct.globaltix.processChunk');
Route::post('/temp-products/globaltix/sync-catalog', [TempProductController::class, 'syncGlobalTixCatalog'])->name('tempProduct.globaltix.syncCatalog');
Route::get('/temp-products/live-raw/{id}', [TempProductController::class, 'getLiveRawActivityData'])->name('tempProduct.liveRaw');
Route::post('/temp-products/live-availability', [TempProductController::class, 'getLiveTicketAvailability'])->name('tempProduct.liveAvailability');
Route::get('/temp-products/{id}', [TempProductController::class, 'show'])->name('tempProduct.show');

// ==================== PRODUCTS ====================
Route::get('/product', [ProductController::class, 'index'])->name('product.index');
Route::get('/product/create', [ProductController::class, 'create'])->name('product.create');
Route::post('/product/store', [ProductController::class, 'store'])->name('product.store');
Route::post('/product/{id}/toggle-top', [ProductController::class, 'toggleTop'])->name('product.toggleTop');
Route::get('/product/edit/{product}', [ProductController::class, 'edit'])->name('product.edit');
Route::post('product/videos/upload-chunk', [ProductController::class, 'uploadAjaxChunk'])->name('product.upload.ajax.chunk');
Route::post('product/videos/complete', [ProductController::class, 'completeVideoUpload'])->name('product.videos.complete');
Route::get('/product/live-raw/{id}', [ProductController::class, 'getLiveRawActivityData'])->name('product.liveRaw');
Route::post('/product/live-availability', [ProductController::class, 'getLiveTicketAvailability'])->name('product.liveAvailability');
Route::put('/product/update/{product}', [ProductController::class, 'update'])->name('product.update');
Route::get('/product/show/{product}', [ProductController::class, 'show'])->name('product.show');
Route::delete('/product/destroy/{id}', [ProductController::class, 'destroy'])->name('product.destroy');
Route::delete('/product/bulk-delete', [ProductController::class, 'bulkDelete'])->name('product.bulkDelete');

// ==================== BOOKINGS (Blade page) ====================
Route::get('/bookings', [BookingController::class, 'index'])->name('booking.index');
Route::get('/bookings/create', [BookingController::class, 'create'])->name('booking.create');
Route::get('/bookings/{reference}/details', [BookingController::class, 'show'])->name('booking.show');
Route::get('/bookings/{reference}/invoice', [BookingController::class, 'invoice'])->name('booking.invoice');

// ==================== ADMIN BOOKING MANAGEMENT (AJAX) ====================
Route::get('/bookings/data', [AdminBookingApiController::class, 'index'])->name('admin.booking.data');
Route::get('/bookings/search-products', [AdminBookingApiController::class, 'searchProducts'])->name('admin.booking.search.products');
Route::get('/bookings/{reference}', [AdminBookingApiController::class, 'show'])->name('admin.booking.show');
Route::put('/bookings/{reference}', [AdminBookingApiController::class, 'update'])->name('admin.booking.update');
Route::post('/bookings/{reference}/retry', [AdminBookingApiController::class, 'retry'])->name('admin.booking.retry');
Route::post('/bookings/{reference}/cancel', [AdminBookingApiController::class, 'cancel'])->name('admin.booking.cancel');
Route::post('/bookings/{reference}/refund', [AdminBookingApiController::class, 'refund'])->name('admin.booking.refund');
Route::post('/bookings/{reference}/resolve', [AdminBookingApiController::class, 'resolve'])->name('admin.booking.resolve');
Route::post('/bookings/create-booking', [AdminBookingApiController::class, 'createBooking'])->name('admin.booking.create.booking');
Route::get('/bookings/ticket-types/{productId}', [AdminBookingApiController::class, 'getTicketTypes'])->name('admin.booking.ticket.types');
Route::get('/bookings/{reference}/payment-link', [AdminBookingApiController::class, 'generatePaymentLink'])->name('admin.booking.payment.link');

// ==================== AVAILABILITY CHECK ====================
Route::get('/availability', [AvailabilityCheckController::class, 'index'])->name('availability.index');
Route::get('/availability/create', [AvailabilityCheckController::class, 'create'])->name('availability.create');
Route::post('/availability/store', [AvailabilityCheckController::class, 'store'])->name('availability.store');
Route::get('/availability/edit/{id}', [AvailabilityCheckController::class, 'edit'])->name('availability.edit');
Route::put('/availability/update/{id}', [AvailabilityCheckController::class, 'update'])->name('availability.update');
Route::delete('/availability/delete/{id}', [AvailabilityCheckController::class, 'destroy'])->name('availability.destroy');
Route::delete('/availability/bulk-delete', [AvailabilityCheckController::class, 'bulkDelete'])->name('availability.bulkDelete');

// ==================== CMS SETTINGS ====================

// Testimonials
Route::get('/cms-settings/testimonials', [TestimonialCmsController::class, 'index'])->name('cmsSettings.testimonial.index');
Route::get('/cms-settings/testimonials/create', [TestimonialCmsController::class, 'create'])->name('cmsSettings.testimonial.create');
Route::post('/cms-settings/testimonials/store', [TestimonialCmsController::class, 'store'])->name('cmsSettings.testimonial.store');
Route::get('/cms-settings/testimonials/{id}/edit', [TestimonialCmsController::class, 'editTestimonial'])->name('cmsSettings.testimonial.edit');
Route::put('/cms-settings/testimonials/{id}', [TestimonialCmsController::class, 'update'])->name('cmsSettings.testimonial.update');
Route::delete('/cms-settings/testimonials/{id}', [TestimonialCmsController::class, 'destroy'])->name('cmsSettings.testimonial.destroy');

// Home Page Sections (Flexible Section Builder)
Route::get('/cms-settings/sections', [HomePageSectionController::class, 'index'])->name('cmsSettings.sections.index');

Route::get('/cms-settings/sections/location-search', [HomePageSectionController::class, 'locationSearch'])->name('cmsSettings.sections.locationSearch');
Route::get('/cms-settings/sections/{id}/edit', [HomePageSectionController::class, 'edit'])->name('cmsSettings.sections.edit');
Route::put('/cms-settings/sections/{id}', [HomePageSectionController::class, 'update'])->name('cmsSettings.sections.update');

Route::post('/cms-settings/sections/reorder', [HomePageSectionController::class, 'reorder'])->name('cmsSettings.sections.reorder');
Route::get('/cms-settings/sections/{id}/toggle-status', [HomePageSectionController::class, 'toggleStatus'])->name('cmsSettings.sections.toggleStatus');

// Home Page Banners (Admin web UI - Single Banner)


Route::get('/cms-settings/banners', [HomePageBannerController::class, 'index'])->name('cmsSettings.banners.index');
Route::get('/cms-settings/banners/{id}/edit', [HomePageBannerController::class, 'edit'])->name('cmsSettings.banners.edit');
Route::put('/cms-settings/banners/{id}', [HomePageBannerController::class, 'update'])->name('cmsSettings.banners.update');



// Blog Posts (Admin web UI)
Route::get('/cms-settings/blogs', [BlogPostController::class, 'index'])->name('cmsSettings.blogs.index');
Route::get('/cms-settings/blogs/create', [BlogPostController::class, 'create'])->name('cmsSettings.blogs.create');
Route::post('/cms-settings/blogs/store', [BlogPostController::class, 'store'])->name('cmsSettings.blogs.store');
Route::get('/cms-settings/blogs/{id}/edit', [BlogPostController::class, 'edit'])->name('cmsSettings.blogs.edit');
Route::put('/cms-settings/blogs/{id}', [BlogPostController::class, 'update'])->name('cmsSettings.blogs.update');
Route::delete('/cms-settings/blogs/{id}', [BlogPostController::class, 'destroy'])->name('cmsSettings.blogs.destroy');
Route::get('/cms-settings/blogs/{id}/toggle-status', [BlogPostController::class, 'toggleStatus'])->name('cmsSettings.blogs.toggleStatus');

// ==================== QUEUE MONITOR ====================
Route::get('/queue-monitor', [QueueMonitorController::class, 'index'])->name('queue-monitor.index');
Route::post('/queue-monitor/retry-all', [QueueMonitorController::class, 'retryAll'])->name('queue-monitor.retry-all');
Route::post('/queue-monitor/retry-selected', [QueueMonitorController::class, 'retrySelected'])->name('queue-monitor.retry-selected');
Route::post('/queue-monitor/retry/{id}', [QueueMonitorController::class, 'retry'])->name('queue-monitor.retry');
Route::delete('/queue-monitor/forget/{id}', [QueueMonitorController::class, 'forget'])->name('queue-monitor.forget');
Route::delete('/queue-monitor/flush-failed', [QueueMonitorController::class, 'flushFailed'])->name('queue-monitor.flush-failed');

// Queue Control Actions
Route::post('/queue-monitor/restart-workers', [QueueMonitorController::class, 'restartWorkers'])->name('queue-monitor.restart-workers');
Route::post('/queue-monitor/clear-all', [QueueMonitorController::class, 'clearAllQueues'])->name('queue-monitor.clear-all');
Route::post('/queue-monitor/clear-queue/{queue}', [QueueMonitorController::class, 'clearQueue'])->name('queue-monitor.clear-queue');
Route::post('/queue-monitor/prune-history', [QueueMonitorController::class, 'pruneHistory'])->name('queue-monitor.prune-history');
Route::post('/queue-monitor/cancel-pending', [QueueMonitorController::class, 'cancelPending'])->name('queue-monitor.cancel-pending');

// ==================== NEWSLETTER SUBSCRIBERS ====================
Route::get('/newsletter-subscribers', [App\Http\Controllers\Admin\NewsletterSubscriberController::class, 'index'])->name('newsletter.index');
Route::delete('/newsletter-subscribers/destroy/{id}', [App\Http\Controllers\Admin\NewsletterSubscriberController::class, 'destroy'])->name('newsletter.destroy');

// ==================== BOKUN ADMIN ====================
Route::prefix('bokun')->group(function () {
    Route::get('/health', [BokunAdminController::class, 'healthCheck'])->name('admin.bokun.health');
    Route::post('/save-credentials', [BokunAdminController::class, 'saveCredentials'])->name('admin.bokun.saveCredentials');
});

// ==================== GLOBALTIX ADMIN ====================
Route::prefix('globaltix')->group(function () {
    Route::get('/health', [GlobalTixAdminController::class, 'healthCheck'])->name('admin.globaltix.health');
    Route::get('/health-api', [GlobalTixAdminController::class, 'healthCheckApi'])->name('admin.globaltix.health.api');
    Route::post('/clear-token', [GlobalTixAdminController::class, 'clearToken'])->name('admin.globaltix.clearToken');
    Route::post('/sync-products', [GlobalTixAdminController::class, 'syncProducts'])->name('admin.globaltix.syncProducts');
    Route::post('/sync-single', [GlobalTixAdminController::class, 'syncSingleProduct'])->name('admin.globaltix.syncSingle');
    Route::post('/save-credentials', [GlobalTixAdminController::class, 'saveCredentials'])->name('admin.globaltix.saveCredentials');
});
