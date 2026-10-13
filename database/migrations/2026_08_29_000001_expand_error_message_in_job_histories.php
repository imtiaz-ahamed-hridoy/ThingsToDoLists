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
        Schema::table('job_histories', function (Blueprint $table) {
            $table->longText('error_message')->nullable()->change();
        });

        Schema::table('provider_webhook_logs', function (Blueprint $table) {
            $table->longText('error_message')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_histories', function (Blueprint $table) {
            $table->string('error_message', 255)->nullable()->change();
        });

        Schema::table('provider_webhook_logs', function (Blueprint $table) {
            $table->text('error_message')->nullable()->change();
        });
    }
};
