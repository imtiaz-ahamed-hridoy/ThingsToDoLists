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
        Schema::create('feature_type_product', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('feature_type_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->foreign('feature_type_id')->references('id')->on('feature_types')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->unique(['feature_type_id', 'product_id']);
            $table->index(['feature_type_id', 'position'], 'ft_product_pos_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feature_type_product');
    }
};
