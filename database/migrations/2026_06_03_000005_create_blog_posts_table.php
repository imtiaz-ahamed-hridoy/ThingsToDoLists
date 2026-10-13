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
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('blog_categories')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('featured_image')->nullable();
            $table->string('author_name')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();

            // Parent-child for sub-articles (replaces blog_articles table)
            $table->foreignId('parent_id')->nullable()->constrained('blog_posts')->cascadeOnDelete();
            $table->string('subtitle')->nullable();

            // Featured & reading time
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedInteger('minutes_to_read')->nullable();

            $table->boolean('published')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
            
            // Performance indexes
            $table->index(['published', 'parent_id', 'published_at'], 'blog_posts_published_parent_index');
            $table->index(['category_id', 'published', 'published_at'], 'blog_posts_category_published_index');
            $table->index(['is_featured', 'published', 'sort_order'], 'blog_posts_featured_published_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
