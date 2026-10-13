<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authoritative creator for the normalized `prices` table.
 *
 * This table stores category billing prices and date-range overrides only.
 * Legacy flat category columns must not be reintroduced here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            // These late-bound relations are attached after the dependent
            // tables are created in the 2026_04_20 product option migrations.
            $table->unsignedBigInteger('product_option_id')->nullable();
            $table->unsignedBigInteger('pricing_category_id')->nullable();

            $table->string('idempotency_key', 150)->nullable()->unique();
            $table->string('provider', 50)->index();

            // Optional source metadata for traceability
            $table->string('source_type', 50)->nullable();
            $table->string('price_kind', 50)->nullable();
            $table->string('external_id')->nullable();
            $table->string('rate_id')->nullable();
            $table->text('rate_name')->nullable();
            $table->string('ticket_type_id')->nullable();
            $table->text('ticket_type_name')->nullable();
            $table->string('category_code', 50)->nullable();
            $table->text('category_name')->nullable();

            // Date range price overrides
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();

            // Billing fields
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->decimal('nett_price', 12, 2)->nullable();
            $table->decimal('original_price', 12, 2)->nullable();
            $table->decimal('recommended_price', 12, 2)->nullable();
            $table->decimal('merchant_price', 12, 2)->nullable();
            $table->string('currency', 10)->default('USD');

            // Tax
            $table->decimal('tax_amount', 12, 2)->nullable();
            $table->decimal('tax_percentage', 5, 2)->nullable();

            // Discount
            $table->decimal('discount_percentage', 5, 2)->nullable();
            $table->decimal('discounted_price', 12, 2)->nullable();

            $table->boolean('is_default')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'valid_from', 'valid_to'], 'prices_product_dates_idx');
            $table->index(['product_option_id', 'pricing_category_id'], 'prices_option_category_idx');
            $table->index(['provider', 'product_id'], 'prices_provider_product_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prices');
    }
};
