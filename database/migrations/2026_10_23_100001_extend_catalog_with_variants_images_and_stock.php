<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Subkategori satu tingkat (mis. Casing HP > Casing iPhone).
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('categories')->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            // Judul pilihan varian di halaman produk, mis. "Warna" atau "Tipe HP".
            $table->string('variant_label', 50)->nullable()->after('weight');
            // Ringkasan dari varian aktif agar listing & filter tidak perlu join.
            $table->boolean('has_variants')->default(false)->after('variant_label');
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_image_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->string('sku', 64)->nullable()->unique();
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('compare_at_price')->nullable();
            $table->unsignedInteger('stock')->default(0);
            // Kosong = ikut berat produk.
            $table->unsignedInteger('weight')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('variant_name', 100)->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->index();
            $table->integer('quantity');
            $table->unsignedInteger('stock_after');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->string('variant_name', 100)->nullable()->after('product_name');
        });

        // Pindahkan foto tunggal lama ke galeri.
        DB::table('products')->whereNotNull('image_path')->orderBy('id')->each(function ($product) {
            DB::table('product_images')->insert([
                'product_id' => $product->id,
                'path' => $product->image_path,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('weight');
        });

        DB::table('product_images')->orderBy('sort_order')->orderBy('id')->get()
            ->unique('product_id')
            ->each(fn ($image) => DB::table('products')->where('id', $image->product_id)->update(['image_path' => $image->path]));

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_variant_id');
            $table->dropColumn('variant_name');
        });

        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_images');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['variant_label', 'has_variants']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
