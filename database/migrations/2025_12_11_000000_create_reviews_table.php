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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnDelete();
            
            $table->tinyInteger('ratings')->default(0)->index();
            $table->text('comment')->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active')->index();

            // Review images
            $table->json('images')->nullable();
            $table->string('image_url')->nullable();
            
            $table->timestamps();
            
            // Performance indexes
            $table->index('created_at');
            $table->index(['product_id', 'status', 'created_at'], 'reviews_product_status_index');
            $table->index(['user_id', 'status', 'created_at'], 'reviews_user_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
