<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Riwayat impor file (produk / update stok). File asli disimpan di disk lokal
        // sampai impor selesai; hasil validasi & ringkasan disimpan sebagai JSON.
        Schema::create('product_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('mode', 20)->nullable();
            $table->string('status', 20)->index();
            $table->string('filename');
            $table->string('path')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->json('summary')->nullable();
            $table->json('preview')->nullable();
            $table->json('errors')->nullable();
            $table->json('result')->nullable();
            $table->text('failure')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_imports');
    }
};
