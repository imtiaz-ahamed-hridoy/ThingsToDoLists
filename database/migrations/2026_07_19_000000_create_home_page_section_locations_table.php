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
        Schema::create('home_page_section_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_page_section_id')->constrained('home_page_sections')->onDelete('cascade');
            $table->enum('location_type', ['country', 'city']);
            $table->string('location_id');
            $table->string('image')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('home_page_section_locations');
    }
};
