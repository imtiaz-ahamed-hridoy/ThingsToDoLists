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
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('city_name');
            $table->string('city_short_code')->nullable();
            $table->boolean('city_is_capital')->default(false);
            $table->string('slug')->unique();
            $table->string('country_name')->nullable();
            $table->string('country_short_code')->nullable();
            $table->string('country_slug')->nullable();
            $table->string('currency_code')->nullable();
            $table->string('mobile_prefix')->nullable();
            $table->string('title')->nullable();
            $table->string('short_description')->nullable();
            $table->string('thumbnail_image')->nullable();
            $table->json('gallery_image')->nullable();
            $table->enum('item_status', ['0', '1', '2', '3'])->default('0')->comment('0 = Pending, 1 = Active, 2 = Deactivate, 3 = Rejected');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
