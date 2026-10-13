<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiered_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pricing_category_id')->constrained('option_pricing_categories')->cascadeOnDelete();

            $table->unsignedInteger('min_pax')->default(1);
            $table->unsignedInteger('max_pax')->default(999);

            $table->decimal('amount', 12, 2);
            $table->decimal('nett_amount', 12, 2)->nullable();
            $table->string('currency', 10)->default('USD');
            $table->decimal('tax_amount', 12, 2)->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['pricing_category_id', 'min_pax', 'max_pax'], 'tiered_prices_category_pax_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiered_prices');
    }
};
