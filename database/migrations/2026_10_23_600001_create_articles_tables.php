<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 160);
            $table->string('slug', 180)->unique();
            $table->string('excerpt', 300)->nullable();
            // HTML dari editor, sudah disaring (App\Services\Content\ArticleSanitizer).
            $table->longText('body');
            $table->string('cover_image_path')->nullable();
            $table->json('tags')->nullable();
            // draft | published. Terbit dengan published_at di masa depan = terjadwal.
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->string('meta_title', 70)->nullable();
            $table->string('meta_description', 160)->nullable();
            $table->unsignedSmallInteger('reading_minutes')->default(1);
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });

        Schema::create('article_product', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->primary(['article_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_product');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('article_categories');
    }
};
