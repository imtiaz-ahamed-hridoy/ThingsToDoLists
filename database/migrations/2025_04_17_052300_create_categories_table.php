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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type');
            $table->enum('status', [0, 1])->default('1')->comment('0 = Deactivate, 1 = Active');
            $table->integer('parent_category_id')->nullable();

            // Search & display fields
            $table->string('search_label')->nullable();
            $table->text('search_subtitle')->nullable();
            $table->unsignedInteger('search_sort_order')->default(0);
            $table->boolean('show_in_search')->default(true);
            $table->string('search_icon')->nullable();

            // Location (plain column — FK enforced in existing DB, avoids circular migration order)
            $table->unsignedBigInteger('city_id')->nullable()->index();
            $table->string('location_country')->nullable();
            $table->string('location_country_code', 10)->nullable();

            $table->timestamps();
            
            // Performance indexes
            $table->index('status');
            $table->index(['slug', 'status'], 'categories_slug_status_index');
            $table->index(['status', 'search_sort_order', 'name'], 'categories_search_sort_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
