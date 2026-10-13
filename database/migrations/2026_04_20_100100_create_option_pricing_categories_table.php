<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('option_pricing_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_option_id')->constrained('product_options')->cascadeOnDelete();

            $table->string('provider', 50)->nullable()->index();
            $table->string('idempotency_key', 150)->nullable()->unique();

            $table->string('external_id')->nullable()->index(); // Bokun pricingCategoryId / GlobalTix ticketType.id
            $table->string('category_code', 50)->index();
            $table->string('category_label');

            $table->unsignedSmallInteger('age_from')->nullable();
            $table->unsignedSmallInteger('age_to')->nullable();
            $table->unsignedInteger('occupancy')->default(1);
            $table->unsignedInteger('group_size')->default(0);

            // Base prices by category (default billing rows)
            $table->decimal('selling_price', 12, 2)->nullable();
            $table->decimal('nett_price', 12, 2)->nullable();
            $table->decimal('original_price', 12, 2)->nullable();
            $table->decimal('recommended_price', 12, 2)->nullable();
            $table->decimal('merchant_price', 12, 2)->nullable();
            $table->string('currency', 10)->default('USD');

            // Purchase constraints
            $table->unsignedInteger('min_purchase_qty')->nullable();
            $table->unsignedInteger('max_purchase_qty')->nullable();

            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_age_qualified')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['product_option_id', 'external_id'], 'opc_option_external_unique');
            $table->index(['product_option_id', 'category_code'], 'opc_option_category_idx');
        });

        Schema::table('prices', function (Blueprint $table) {
            $table->foreign('pricing_category_id')
                ->references('id')
                ->on('option_pricing_categories')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('prices')) {
            Schema::table('prices', function (Blueprint $table) {
                $table->dropForeign(['pricing_category_id']);
            });
        }

        Schema::dropIfExists('option_pricing_categories');
    }
};
