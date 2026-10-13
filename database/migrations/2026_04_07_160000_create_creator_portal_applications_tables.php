<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creator_community_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email')->index();
            $table->string('name');
            $table->string('instagram_handle');
            $table->unsignedInteger('instagram_followers');
            $table->string('youtube_handle')->nullable();
            $table->string('tiktok_handle')->nullable();
            $table->string('website_url')->nullable();
            $table->boolean('has_base_location')->default(true);
            $table->string('country');
            $table->string('city');
            $table->string('discovered_via');
            $table->string('discovered_via_other')->nullable();
            $table->string('referred_by')->nullable();
            $table->string('status')->default('submitted');
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['email', 'status']);
        });

        Schema::create('creator_sponsored_experience_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users', 'id', 'cr_sp_user_id_fk')->nullOnDelete();
            $table->foreignId('creator_community_application_id')->nullable()->constrained('creator_community_applications', 'id', 'cr_sp_comm_app_id_fk')->nullOnDelete();
            $table->boolean('is_community_member')->default(true);
            $table->string('name');
            $table->string('email')->index();
            $table->string('instagram_url');
            $table->string('preferred_currency', 10);
            $table->string('reel_views_range');
            $table->date('planned_experience_date');
            $table->boolean('no_go_experiences_accepted')->default(false);
            $table->boolean('feature_consent')->default(false);
            $table->string('status')->default('submitted');
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['email', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_sponsored_experience_applications');
        Schema::dropIfExists('creator_community_applications');
    }
};
