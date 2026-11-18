<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pembayaran di luar Midtrans (tunai, transfer langsung, EDC, lainnya) yang
 * dicatat staf: nomor referensi, bukti bayar, dan siapa yang mencatat.
 * Izin baru payments.record diberikan ke jabatan yang sudah bisa membuat
 * penjualan manual (sebelumnya tombol "Tandai lunas" ikut izin itu).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_reference', 120)->nullable()->after('gateway_fee');
            $table->string('payment_proof', 255)->nullable()->after('payment_reference');
            $table->foreignId('payment_recorded_by')->nullable()->after('payment_proof')->constrained('users')->nullOnDelete();
        });

        foreach (DB::table('staff_roles')->get(['id', 'permissions']) as $role) {
            $permissions = (array) json_decode((string) $role->permissions, true);
            if (in_array('orders.create', $permissions, true) && ! in_array('payments.record', $permissions, true)) {
                $permissions[] = 'payments.record';
                DB::table('staff_roles')->where('id', $role->id)->update(['permissions' => json_encode(array_values($permissions))]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_recorded_by');
            $table->dropColumn(['payment_reference', 'payment_proof']);
        });

        foreach (DB::table('staff_roles')->get(['id', 'permissions']) as $role) {
            $permissions = array_values(array_diff((array) json_decode((string) $role->permissions, true), ['payments.record']));
            DB::table('staff_roles')->where('id', $role->id)->update(['permissions' => json_encode($permissions)]);
        }
    }
};
