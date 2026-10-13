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
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('email')->nullable();
            $table->string('number')->nullable();
            $table->string('system_name')->nullable();
            $table->string('address')->nullable();
            $table->string('copyright_text')->nullable();
            $table->string('logo')->nullable();
            $table->string('favicon')->nullable();
            $table->longText('description')->nullable();
            
            // Social Links
            $table->string('facebook')->nullable();
            $table->string('twitter')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('instagram')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('telegram')->nullable();
            
            // Supplier Guide Fields
            $table->string('supplier_guide_title')->nullable();
            $table->longText('supplier_guide_description')->nullable();
            $table->longText('supplier_guide_link_or_iframe')->nullable();
            $table->string('supplier_guide_media')->nullable();
            $table->string('supplier_guide_media_type')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
