<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Identity
            $table->string('provider', 50)->index(); // bokun, globaltix, supplier
            $table->string('product_api_id')->nullable()->index();
            $table->string('reference_code', 100)->nullable()->index();
            $table->string('slug')->unique();
            $table->string('platform', 50)->nullable()->index();

            // Relations
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->unsignedInteger('category_position')->default(0);

            // Core content
            $table->string('language', 10)->default('en');
            $table->string('product_category', 100)->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->longText('full_description')->nullable();
            $table->text('thumbnail')->nullable();
            $table->string('short_introduction', 255)->nullable();
            $table->text('highlights_summary')->nullable();
            $table->string('content_mode', 50)->default('manual');
            $table->text('auto_content_text')->nullable();

            // Lifecycle
            $table->string('status', 30)->default('draft')->index();
            $table->boolean('is_top')->default(false)->index();
            $table->boolean('published')->default(false)->index();
            $table->boolean('is_draft')->default(true)->index();
            $table->unsignedTinyInteger('wizard_step')->default(0);

            // Booking and product behavior
            $table->boolean('is_instant_confirmation')->default(false);
            $table->boolean('is_cancellable')->default(false);
            $table->boolean('allowCustomizedBookings')->default(false);
            $table->boolean('copyright_acknowledged')->default(false);

            // Classification
            $table->string('barcode_type')->nullable();
            $table->string('activityType', 100)->nullable();
            $table->string('scheduleType', 100)->nullable();
            $table->string('bookingType', 100)->nullable();
            $table->string('difficulty', 50)->nullable();
            $table->boolean('privateActivity')->default(false);
            $table->string('private_tour', 255)->nullable();
            $table->string('guide_type')->nullable();
            $table->unsignedInteger('max_group_size')->nullable();
            $table->boolean('online_reservation')->default(true);

            // Duration
            $table->string('duration')->nullable();
            $table->string('durationType', 50)->nullable();
            $table->integer('durationMinutes')->nullable();
            $table->integer('durationHours')->nullable();
            $table->integer('durationDays')->nullable();
            $table->integer('durationWeeks')->nullable();
            $table->string('durationText', 255)->nullable();
            $table->string('default_cut_off_time')->nullable();

            // Cutoff and capacity
            $table->string('cutoffType', 100)->nullable();
            $table->integer('cutoffReferenceHour')->nullable();
            $table->integer('cutoffReferenceMinute')->nullable();
            $table->integer('bookingCutoffMinutes')->nullable();
            $table->integer('bookingCutoffHours')->nullable();
            $table->integer('bookingCutoffDays')->nullable();
            $table->integer('bookingCutoffWeeks')->nullable();
            $table->string('capacityType', 100)->nullable();
            $table->integer('passCapacity')->nullable();
            $table->integer('passesAvailable')->nullable();

            // Meeting and logistics
            $table->string('meetingType', 100)->nullable();
            $table->string('meeting_point')->nullable();
            $table->string('meeting_point_address')->nullable();
            $table->json('meeting_point_coordinates')->nullable();
            $table->text('meeting_point_description')->nullable();
            $table->string('arrive_time')->nullable();
            $table->string('arrival_time')->nullable();
            $table->string('pickup_service', 255)->nullable();
            $table->string('pickupService', 100)->nullable();
            $table->text('pickup_info')->nullable();
            $table->string('drop_off_type')->nullable();
            $table->string('dropoffService', 100)->nullable();
            $table->string('places')->nullable();
            $table->string('address_list')->nullable();
            $table->string('customer_visits')->nullable();

            // Transport and food
            $table->boolean('has_transportation')->default(false);
            $table->boolean('travels_to_different_city')->default(false);
            $table->json('transportation_items')->nullable();
            $table->string('transportation_type')->nullable();
            $table->boolean('food_included')->default(false);
            $table->json('food_configuration')->nullable();

            // Restrictions and helper info
            $table->text('dressCode')->nullable();
            $table->integer('minAge')->nullable();
            $table->boolean('passportRequired')->default(false);
            $table->boolean('pet_friendly_policy')->default(false);
            $table->json('not_suitable_for')->nullable();
            $table->json('not_allowed')->nullable();
            $table->json('mandatory_items')->nullable();
            $table->text('custom_info_before_booking')->nullable();
            $table->text('other_info_before_activity')->nullable();

            // Display pricing
            $table->string('reservation_system')->nullable();
            $table->string('pricing_type')->nullable();
            $table->decimal('original_price', 12, 2)->nullable();
            $table->decimal('from_price', 12, 2)->nullable();
            $table->string('default_currency', 10)->nullable();
            $table->string('merchant_name')->nullable();

            // Reviews
            $table->decimal('reviewRating', 3, 2)->nullable();
            $table->unsignedInteger('reviewCount')->default(0);

            // Provider metadata
            $table->unsignedBigInteger('defaultRateId')->nullable();
            $table->string('time_zone')->nullable();
            $table->string('timezone_offset', 50)->nullable();
            $table->string('address_line')->nullable();
            $table->string('postal_code', 20)->nullable();

            // Structured JSON content
            $table->json('activityCategories')->nullable();
            $table->json('activityAttributes')->nullable();
            $table->json('highlights')->nullable();
            $table->json('inclusions')->nullable();
            $table->json('exclusions')->nullable();
            $table->json('included')->nullable();
            $table->json('excluded')->nullable();
            $table->json('know_before_you_go')->nullable();
            $table->json('itinerary')->nullable();
            $table->json('what_to_expect')->nullable();
            $table->json('terms_and_conditions')->nullable();
            $table->json('operating_hours')->nullable();
            $table->json('things_to_note')->nullable();
            $table->json('how_to_use')->nullable();
            $table->json('cancellation_notes')->nullable();
            $table->json('guidanceTypes')->nullable();
            $table->json('startTimes')->nullable();
            $table->json('agendaItems')->nullable();
            $table->json('rates')->nullable();
            $table->json('pricingCategories')->nullable();
            $table->json('startPoints')->nullable();
            $table->json('bookableExtras')->nullable();
            // Backward-compatible media snapshots (normalized media lives in product_images/product_videos)
            $table->json('image_gallery')->nullable();
            $table->json('video_gallery')->nullable();
            $table->json('options_configuration')->nullable();
            $table->json('pricing_configuration')->nullable();
            $table->json('validation_types')->nullable();
            $table->json('booking_questions_data')->nullable();
            $table->json('passenger_fields_data')->nullable();
            $table->json('contact_fields_data')->nullable();

            // Raw snapshot and sync
            $table->json('raw_response')->nullable();
            $table->timestamp('raw_response_saved_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Unique constraint for provider catalog imports
            $table->unique(['provider', 'product_api_id'], 'products_provider_api_id_unique');
            $table->index(['provider', 'status', 'published'], 'products_provider_status_published_idx');
            $table->index(['provider', 'from_price'], 'products_provider_from_price_idx');
            $table->index(['provider', 'reviewRating'], 'products_provider_review_rating_idx');

            // Performance indexes
            $table->index('vendor_id');
            $table->index('created_at');
            $table->index(['status', 'published'], 'products_status_published_index');
            $table->index(['vendor_id', 'status'], 'products_vendor_status_index');
            $table->index(['created_at', 'reviewRating', 'reviewCount'], 'products_created_at_review_index');
            $table->index(['category_id', 'category_position'], 'products_cat_pos_idx');

            // Fulltext search index (MySQL/Postgres only)
            if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
                $table->fullText(['title', 'description', 'full_description', 'short_introduction'], 'products_search_ft');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
