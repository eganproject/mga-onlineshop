<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Outbound manual di warehouse29 untuk penjualan manual (POST /api/v1/outbound-manuals).
            // Status: queued, pending (menunggu approve gudang), approved, failed, deleted.
            $table->string('warehouse_outbound_status', 20)->nullable()->after('created_by')->index();
            $table->string('warehouse_outbound_code', 60)->nullable()->after('warehouse_outbound_status');
            $table->string('warehouse_outbound_error', 500)->nullable()->after('warehouse_outbound_code');
            $table->timestamp('warehouse_outbound_sent_at')->nullable()->after('warehouse_outbound_error');
            $table->timestamp('warehouse_outbound_approved_at')->nullable()->after('warehouse_outbound_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['warehouse_outbound_status']);
            $table->dropColumn(['warehouse_outbound_status', 'warehouse_outbound_code', 'warehouse_outbound_error', 'warehouse_outbound_sent_at', 'warehouse_outbound_approved_at']);
        });
    }
};
