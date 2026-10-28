<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sinkron stok dari warehouse29:
 * - warehouse_sku: SKU barang di gudang yang dihubungkan ke produk/varian toko.
 * - warehouse_stock_items: salinan terakhir stok per SKU dari API gudang.
 * - warehouse_sync_runs: riwayat tiap tarikan (dipakai juga sebagai penanda waktu tarikan berikutnya).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('warehouse_sku', 100)->nullable()->unique()->after('sku');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('warehouse_sku', 100)->nullable()->unique()->after('sku');
        });

        Schema::create('warehouse_stock_items', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 100)->unique();
            $table->string('name')->nullable();
            $table->string('category')->nullable();
            $table->string('uom', 30)->nullable();
            $table->decimal('qty', 14, 2)->default(0);
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('warehouse_updated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('warehouse_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('mode', 20);
            $table->string('status', 20)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('fetched')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('unmatched')->default(0);
            $table->string('server_time', 40)->nullable();
            $table->text('message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_sync_runs');
        Schema::dropIfExists('warehouse_stock_items');

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropUnique(['warehouse_sku']);
            $table->dropColumn('warehouse_sku');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['warehouse_sku']);
            $table->dropColumn('warehouse_sku');
        });
    }
};
