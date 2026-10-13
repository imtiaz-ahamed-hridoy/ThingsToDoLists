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
        Schema::create('job_histories', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->string('job_class');
            $table->string('status', 20)->default('processed'); // processed, failed
            $table->float('duration_ms')->nullable(); // how long it ran in milliseconds
            $table->string('error_message')->nullable(); // error message if failed
            $table->timestamp('processed_at')->useCurrent()->index();
            $table->index(['queue', 'status', 'processed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_histories');
    }
};
