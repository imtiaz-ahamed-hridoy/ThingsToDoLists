<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('blog_post_related')) {
            return;
        }

        Schema::create('blog_post_related', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->foreignId('related_post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);

            $table->primary(['post_id', 'related_post_id']);
            $table->index(['post_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_related');
    }
};
