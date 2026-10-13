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
        Schema::create('temp_products', function (Blueprint $table) {
            $table->id();
            $table->string('product_api_id');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('slug')->nullable();
            $table->string('vendor_name')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('review_count')->default(0);
            $table->string('city')->nullable();
            // $table->foreignId('city_id')->constrained('cities')->nullable();
            $table->string('thumbnail')->nullable();
            $table->json('raw_data')->nullable();
            $table->enum('status', ['pending', 'active', 'inactive'])->default('pending');
            $table->string('last_import_status')->nullable();
            $table->text('last_import_reason')->nullable();
            $table->timestamp('last_import_attempted_at')->nullable();
            $table->string('api_platform')->default('bokun');
            $table->timestamps();
            
            $table->unique(['product_api_id', 'api_platform'], 'temp_products_product_api_id_api_platform_unique');
            
            // Performance indexes
            $table->index('api_platform');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temp_products');
    }
};
