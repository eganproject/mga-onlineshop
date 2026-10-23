<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * payment_due_at: batas waktu pesanan dibayar; lewat dari itu pesanan
 * dibatalkan otomatis dan stoknya dikembalikan.
 * external_id: order_id yang dikirim ke gateway. Satu pesanan bisa punya
 * beberapa tagihan (link dibuat ulang), dan Midtrans menolak order_id ganda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('payment_due_at')->nullable()->after('notes')->index();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('external_id', 64)->nullable()->after('gateway');
            $table->unique(['gateway', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['gateway', 'external_id']);
            $table->dropColumn('external_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_due_at']);
            $table->dropColumn('payment_due_at');
        });
    }
};
