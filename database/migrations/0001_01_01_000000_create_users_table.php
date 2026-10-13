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
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Personal Info
            $table->string('name')->nullable();
            $table->date('dob')->nullable();
            $table->text('avatar')->nullable();

            // Contact & Auth
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable()->unique();
            $table->string('password');

            // Roles & Status
            $table->enum('role', ['Admin', 'User', 'Vendor', 'ContentCreator'])->default('User')->index();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->boolean('is_agree')->default(true);
            $table->boolean('is_online')->default(false);
            $table->string('preferred_currency', 10)->nullable();

            // Verification & Reset
            $table->timestamp('email_verified_at')->nullable();
            $table->string('reset_code')->nullable()->index();
            $table->timestamp('reset_code_expires_at')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
