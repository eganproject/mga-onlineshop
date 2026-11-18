<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Izin baru orders.cancel ("Batalkan pesanan"), dipisah dari orders.manage.
 * Jabatan yang sudah bisa memproses pesanan tetap bisa membatalkan, kecuali
 * Staf Gudang (permintaan pemilik toko: gudang tidak membatalkan pesanan).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('staff_roles')->get(['id', 'key', 'permissions']) as $role) {
            $permissions = (array) json_decode((string) $role->permissions, true);
            if ($role->key !== 'warehouse' && in_array('orders.manage', $permissions, true) && ! in_array('orders.cancel', $permissions, true)) {
                $permissions[] = 'orders.cancel';
                DB::table('staff_roles')->where('id', $role->id)->update(['permissions' => json_encode(array_values($permissions))]);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('staff_roles')->get(['id', 'permissions']) as $role) {
            $permissions = array_values(array_diff((array) json_decode((string) $role->permissions, true), ['orders.cancel']));
            DB::table('staff_roles')->where('id', $role->id)->update(['permissions' => json_encode($permissions)]);
        }
    }
};
