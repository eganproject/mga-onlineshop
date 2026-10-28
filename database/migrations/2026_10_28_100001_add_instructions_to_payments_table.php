<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * instructions: cara bayar dari Midtrans Core API (nomor VA, kode bayar,
 * QR, deeplink) supaya ditampilkan langsung di halaman bayar toko.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->json('instructions')->nullable()->after('redirect_url');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('instructions');
        });
    }
};
