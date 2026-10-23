<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            // percent | fixed | free_shipping (lihat App\Enums\VoucherType)
            $table->string('type', 20);
            // percent: 1-100; fixed: nominal rupiah; free_shipping: batas ongkir ditanggung (0 = penuh).
            $table->unsignedBigInteger('value')->default(0);
            $table->unsignedBigInteger('max_discount')->nullable();
            $table->unsignedBigInteger('min_spend')->default(0);
            $table->unsignedInteger('quota')->nullable();
            $table->unsignedInteger('per_user_limit')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('voucher_id')->nullable()->after('total_weight')->constrained()->nullOnDelete();
            // Salinan kode, tetap tampil walau voucher dihapus.
            $table->string('voucher_code', 32)->nullable()->after('voucher_id');
            $table->unsignedBigInteger('discount')->default(0)->after('shipping_cost');
            $table->unsignedBigInteger('shipping_discount')->default(0)->after('discount');
            $table->index(['voucher_id', 'user_id', 'status']);
        });

        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('subtitle', 255)->nullable();
            $table->string('button_label', 40)->nullable();
            $table->string('link_url', 500)->nullable();
            $table->string('image_path');
            $table->string('mobile_image_path')->nullable();
            $table->boolean('show_text')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['voucher_id', 'user_id', 'status']);
            $table->dropConstrainedForeignId('voucher_id');
            $table->dropColumn(['voucher_code', 'discount', 'shipping_discount']);
        });

        Schema::dropIfExists('vouchers');
    }
};
