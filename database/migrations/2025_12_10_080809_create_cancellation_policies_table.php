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
        Schema::create('cancellation_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->string('provider', 50)->nullable()->index();
            $table->string('title')->nullable();
            $table->longText('policy')->nullable();
            $table->text('tax')->nullable();
            $table->string('policyType')->nullable();
            $table->boolean('is_cancellable')->default(false);
            $table->json('cancellation_notes')->nullable();
            $table->json('penalty_rules')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
            $table->index(['product_id', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cancellation_policies');
    }
};
