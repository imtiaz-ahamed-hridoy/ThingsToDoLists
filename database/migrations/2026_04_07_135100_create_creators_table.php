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
        Schema::create('creators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            // Step 2 details
            $table->enum('creator_type', ['content_creator', 'travel_agent', 'in_destination_guide_reseller'])->default('content_creator');
            
            // Step 3 details
            $table->string('organization_name')->nullable();
            $table->string('currency')->default('USD');
            $table->string('country');
            
            // Step 3 Checkboxes & Links
            $table->boolean('posts_on_website')->default(false);
            $table->string('website_url')->nullable();
            
            $table->boolean('posts_on_social_media')->default(false);
            $table->string('instagram_handle')->nullable();
            $table->string('youtube_account')->nullable();
            $table->string('tiktok_account')->nullable();
            
            $table->string('main_channel')->nullable(); // dropdown selection
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('creators');
    }
};
