<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            // nullable — set for authenticated users, null for guests
            $table->unsignedBigInteger('user_id')->nullable()->index();
            // nullable — set for guest users, null for authenticated users
            $table->string('guest_id', 100)->nullable()->index();
            $table->unsignedBigInteger('product_id');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');

            // Prevent authenticated users from having duplicate wishlist items
            $table->unique(['user_id', 'product_id'], 'wishlists_user_product_unique');
            // Prevent guests from having duplicate wishlist items
            // Note: MySQL treats NULLs as distinct in unique indexes, so service layer
            // enforces uniqueness for guests via query-before-insert pattern (toggle method).
            $table->index(['guest_id', 'product_id'], 'wishlists_guest_product_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlists');
    }
};
