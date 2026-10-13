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
        Schema::create('chunk_upload_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('temp_id')->unique();
            $table->string('file_name');
            $table->string('disk')->default('public');
            $table->string('folder');
            $table->integer('total_chunks')->default(0);
            $table->integer('uploaded_chunks')->default(0);
            $table->boolean('is_completed')->default(false);
            $table->string('final_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chunk_upload_sessions');
    }
};
