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
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->string('external_id')->nullable()->index();

            // ─── Location Details ────────────────────────────────────────────────
            $table->string('city')->nullable();
            $table->string('countryCode')->nullable();
            $table->string('country')->nullable();
            $table->text('address')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city_id', 100)->nullable()->index();

            // ─── Coordinates ─────────────────────────────────────────────────────
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('coordinates')->nullable();

            // ─── Classification & Ordering ───────────────────────────────────────
            $table->string('location_type', 50)->nullable()->default('destination');  // destination, meeting_point, pickup, dropoff, start_point
            $table->string('label')->nullable();                                     // Display label
            $table->boolean('is_primary')->default(true);                            // Primary location flag
            $table->unsignedInteger('sort_order')->default(0);

            // ─── Debug / Provider Snapshot ────────────────────────────────────────
            $table->json('meta')->nullable();                                        // Full provider location data

            $table->timestamps();

            $table->index(['product_id', 'city']);
            $table->index(['product_id', 'location_type']);

            // Performance indexes
            $table->index(['city', 'country'], 'locations_city_country_index');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
