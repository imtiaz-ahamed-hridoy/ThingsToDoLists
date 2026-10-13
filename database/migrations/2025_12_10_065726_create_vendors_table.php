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
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();

            // Identifiers
            $table->string('supplier_id')->nullable()->index();
            $table->string('external_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider', 50)->nullable()->index();

            // Profile
            $table->string('name')->index();
            $table->string('business_type')->nullable()->index();
            $table->string('preferred_language')->nullable()->default('en');
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->string('link')->nullable();

            // Contact
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('country')->nullable()->index();
            $table->json('locations')->nullable();
            $table->string('activities_count')->nullable();

            // Finance
            $table->string('currency', 10)->nullable();
            $table->string('vat_number')->nullable();
            $table->string('tax_identification_number')->nullable();
            $table->string('payment_method')->nullable();

            // Reputation
            $table->decimal('rating', 5, 2)->nullable()->index();
            $table->integer('reviews')->nullable();

            $table->timestamps();

            $table->unique(['provider', 'external_id']);
            $table->index(['provider', 'supplier_id']);
            $table->index(['provider', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
