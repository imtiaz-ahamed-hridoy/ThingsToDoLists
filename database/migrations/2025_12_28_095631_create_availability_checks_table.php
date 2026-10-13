<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authoritative creator for the normalized `availability` table.
 *
 * ONE ROW per date + time + option.
 *
 * Category-specific prices are moved to `availability_category_prices`
 * child table for proper normalization and billing accuracy.
 *
 * Bokun:     availabilities[] with date, startTime, availabilityCount
 * GlobalTix: availability[].data[] with date, time, ticket_type_id
 * Supplier:  user-entered date/time slots
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            // This late-bound relation is attached after `product_options`
            // exists in the 2026_04_20 product option migration.
            $table->unsignedBigInteger('product_option_id')->nullable();

            $table->string('idempotency_key', 150)->nullable()->unique();
            $table->string('provider', 50)->index();

            // Provider external IDs
            $table->string('external_id')->nullable()->index();   // Bokun availability.id / GlobalTix session.id

            // Date + Time
            $table->date('available_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->dateTime('start_at')->nullable();             // Full datetime for sorting
            $table->string('time_zone', 100)->nullable();

            // Capacity
            $table->unsignedInteger('capacity_total')->default(0);
            $table->unsignedInteger('capacity_booked')->default(0);
            $table->unsignedInteger('capacity_reserved')->default(0);
            $table->boolean('unlimited_availability')->default(false);
            $table->boolean('sold_out')->default(false);
            $table->unsignedInteger('min_participants')->default(1);
            $table->unsignedInteger('max_participants')->nullable();

            // Default display price for this slot (cheapest category)
            $table->decimal('display_price', 12, 2)->nullable();
            $table->string('currency', 10)->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_closed')->default(false);

            // GlobalTix series info
            $table->string('series_id')->nullable();
            $table->string('series_name')->nullable();

            // Raw provider data for debugging
            $table->json('raw_data')->nullable();

            $table->timestamps();

            $table->index(['product_id', 'available_date'], 'avail_product_date_idx');
            $table->index(['product_id', 'available_date', 'start_time'], 'avail_product_date_time_idx');
            $table->index(['product_option_id', 'available_date'], 'avail_option_date_idx');
            $table->index(['provider', 'external_id'], 'avail_provider_external_idx');
            $table->index(['available_date', 'is_active', 'sold_out'], 'avail_active_date_idx');

            // Performance indexes
            $table->index('created_at');
            $table->index(['is_active', 'sold_out', 'available_date'], 'availability_is_active_sold_out_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability');
    }
};
