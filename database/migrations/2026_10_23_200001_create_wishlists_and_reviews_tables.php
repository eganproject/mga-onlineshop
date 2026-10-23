<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'product_id']);
        });

        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Pesanan selesai yang jadi bukti pembelian (label "Pembeli terverifikasi").
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title', 120)->nullable();
            $table->text('body');
            $table->string('status', 20)->default('pending')->index();
            $table->text('admin_reply')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            // Satu ulasan per pelanggan per produk (bisa diedit).
            $table->unique(['user_id', 'product_id']);
            $table->index(['product_id', 'status']);
        });

        // Ringkasan rating di tabel produk agar listing tidak perlu agregasi.
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('rating_avg', 2, 1)->default(0)->after('is_featured');
            $table->unsignedInteger('review_count')->default(0)->after('rating_avg');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['rating_avg', 'review_count']);
        });

        Schema::dropIfExists('product_reviews');
        Schema::dropIfExists('wishlist_items');
    }
};
