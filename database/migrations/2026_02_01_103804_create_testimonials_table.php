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
        Schema::create('testimonial_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('country')->nullable();
            $table->string('country_icon')->nullable();
            $table->string('avater')->nullable();
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->text('footer_description')->nullable();
            $table->integer('rating')->default(5);
            $table->boolean('status')->default(true);
            $table->timestamps();
            
            // Performance indexes
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('testimonial_settings');
    }
};
