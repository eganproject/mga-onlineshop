<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Tingkat harga grosir produk tanpa varian: [{"min_qty": 10, "price": 65000}, ...].
            $table->json('wholesale_tiers')->nullable()->after('compare_at_price');
            // Ringkasan: jumlah terkecil yang mendapat harga grosir (null = tidak ada grosir). Dipakai badge & filter katalog.
            $table->unsignedInteger('wholesale_min_qty')->nullable()->after('wholesale_tiers')->index();
            $table->unsignedInteger('min_order_qty')->default(1)->after('wholesale_min_qty');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->json('wholesale_tiers')->nullable()->after('compare_at_price');
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Harga normal per pcs bila baris ini memakai harga grosir (null = harga normal).
            $table->unsignedBigInteger('regular_price')->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('regular_price'));
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn('wholesale_tiers'));
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['wholesale_min_qty']);
            $table->dropColumn(['wholesale_tiers', 'wholesale_min_qty', 'min_order_qty']);
        });
    }
};
