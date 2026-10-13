<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dedicated image & video tables for products.
 *
 * Replaces the JSON blob approach (products.image_gallery / video_gallery)
 * with properly indexed, queryable tables.
 *
 * Benefits:
 *   - Indexable by product_id for fast retrieval
 *   - Sortable with sort_order
 *   - Supports duplicate detection via provider_path
 *   - Supports SEO alt text per image
 *   - Supports lazy loading (only load images when needed)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── Product Images ──────────────────────────────────────────────
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('provider')->default('local'); // bokun, globaltix, supplier, local

            // URLs (at least one must be non-null)
            $table->text('original_url')->nullable();   // Full-res original
            $table->text('large_url')->nullable();       // Large display (1200px)
            $table->text('preview_url')->nullable();     // Thumbnail/preview (400px)

            // Metadata
            $table->string('alt_text')->nullable();      // SEO alt text
            $table->string('external_id')->nullable();   // Provider's image ID
            $table->string('provider_path')->nullable(); // Provider's storage path (for dedup)
            $table->string('mime_type')->nullable();      // image/jpeg, image/png, image/webp
            $table->string('extension')->nullable();      // jpg, png, webp
            $table->unsignedInteger('file_size')->nullable(); // Bytes
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();

            // Flags
            $table->boolean('is_primary')->default(false); // Used as product thumbnail
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            // Raw provider data for debugging
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['product_id', 'sort_order']);
            $table->index(['product_id', 'is_primary']);
            $table->index(['product_id', 'is_active']);
        });

        // ─── Product Videos ──────────────────────────────────────────────
        Schema::create('product_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('provider')->default('local'); // bokun, globaltix, supplier, local

            // Video source
            $table->text('source_url')->nullable();       // YouTube/Vimeo/direct URL
            $table->text('thumbnail_url')->nullable();    // Video thumbnail image
            $table->text('preview_url')->nullable();      // Preview/poster image
            $table->text('embed_html')->nullable();        // iframe embed HTML (YouTube/Vimeo)

            // Metadata
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('external_id')->nullable();    // Provider's video ID
            $table->string('provider_name')->nullable();  // youtube, vimeo, self-hosted
            $table->unsignedInteger('duration')->nullable(); // Duration in seconds
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('file_size')->nullable(); // Bytes
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();

            // Flags
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            // Raw provider data
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['product_id', 'sort_order']);
            $table->index(['product_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_videos');
        Schema::dropIfExists('product_images');
    }
};
