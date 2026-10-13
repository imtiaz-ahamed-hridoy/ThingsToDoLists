<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            $table->string('provider', 50)->index();            // bokun | globaltix | supplier
            $table->string('external_id')->nullable()->index(); // Bokun rateId / GlobalTix option.id
            $table->string('name');
            $table->text('description')->nullable();

            // Option-level commerce fields (GlobalTix + Supplier)
            $table->string('currency', 10)->nullable();
            $table->string('ticket_format', 50)->nullable();
            $table->string('ticket_validity', 100)->nullable();
            $table->string('defined_duration', 100)->nullable();
            $table->boolean('is_dynamic_pricing')->default(false);
            $table->boolean('is_capacity')->default(false);
            $table->boolean('is_cancellable')->default(true);
            $table->json('time_slot')->nullable();
            $table->json('visit_date')->nullable();
            $table->json('advance_booking')->nullable();
            $table->json('questions')->nullable();
            $table->json('inclusions')->nullable();
            $table->json('exclusions')->nullable();
            $table->text('cancellation_policy')->nullable();
            $table->json('cancellation_notes')->nullable();
            $table->date('publish_start')->nullable();
            $table->date('publish_end')->nullable();
            $table->date('redeem_start')->nullable();
            $table->date('redeem_end')->nullable();

            $table->boolean('is_default')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);

            // Provider-specific extras
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(['product_id', 'provider', 'external_id'], 'product_options_unique');
            $table->index(['product_id', 'is_default']);
            
            // Performance indexes
            $table->index(['provider', 'external_id'], 'product_options_provider_external_index');
        });

        Schema::table('prices', function (Blueprint $table) {
            $table->foreign('product_option_id')
                ->references('id')
                ->on('product_options')
                ->cascadeOnDelete();
        });

        Schema::table('availability', function (Blueprint $table) {
            $table->foreign('product_option_id')
                ->references('id')
                ->on('product_options')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('prices')) {
            Schema::table('prices', function (Blueprint $table) {
                $table->dropForeign(['product_option_id']);
            });
        }

        if (Schema::hasTable('availability')) {
            Schema::table('availability', function (Blueprint $table) {
                $table->dropForeign(['product_option_id']);
            });
        }

        Schema::dropIfExists('product_options');
    }
};
