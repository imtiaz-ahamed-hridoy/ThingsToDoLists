<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_category_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('availability_id')->constrained('availability')->cascadeOnDelete();
            $table->foreignId('pricing_category_id')->nullable()->constrained('option_pricing_categories')->nullOnDelete();

            $table->string('category_external_id')->nullable();
            $table->string('category_code', 50)->index();
            $table->string('category_label')->nullable();

            $table->decimal('selling_price', 12, 2);
            $table->decimal('nett_price', 12, 2)->nullable();
            $table->decimal('original_price', 12, 2)->nullable();
            $table->decimal('recommended_price', 12, 2)->nullable();
            $table->decimal('merchant_price', 12, 2)->nullable();
            $table->decimal('tax_amount', 12, 2)->nullable();
            $table->string('currency', 10)->default('USD');

            $table->unsignedInteger('available_qty')->nullable();
            $table->unsignedInteger('min_qty')->nullable();
            $table->unsignedInteger('max_qty')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['availability_id', 'category_code'], 'acp_avail_category_idx');
            $table->index(['availability_id', 'pricing_category_id'], 'acp_avail_pricing_cat_idx');
            $table->index(['availability_id', 'category_external_id'], 'acp_avail_category_external_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_category_prices');
    }
};
