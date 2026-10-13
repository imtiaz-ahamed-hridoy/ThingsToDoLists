<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old tables if they exist from previous incomplete attempts
        Schema::dropIfExists('destination_highlight_product');
        Schema::dropIfExists('destination_highlights');

        Schema::create('destination_highlights', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('destination_type', ['country', 'city']);
            $table->unsignedBigInteger('destination_id'); // FK to cities.id
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index(['destination_type', 'destination_id']);
            $table->index('status');
        });

        Schema::create('destination_highlight_product', function (Blueprint $table) {
            $table->unsignedBigInteger('destination_highlight_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('position')->default(0);

            $table->primary(['destination_highlight_id', 'product_id']);
            $table->index(['destination_highlight_id', 'position'], 'dh_product_pos_idx');

            $table->foreign('destination_highlight_id')
                  ->references('id')
                  ->on('destination_highlights')
                  ->onDelete('cascade');

            $table->foreign('product_id')
                  ->references('id')
                  ->on('products')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destination_highlight_product');
        Schema::dropIfExists('destination_highlights');
    }
};
