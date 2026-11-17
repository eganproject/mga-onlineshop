<?php

use App\Enums\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jabatan staf disimpan di database supaya hak aksesnya bisa diatur dari
 * Admin › Jabatan & Hak Akses. Isi awal = aturan lama (StaffRole), jadi
 * perilaku tidak berubah setelah migrate. users.staff_role tetap kolom
 * string yang berisi `key` jabatan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_roles', function (Blueprint $table) {
            $table->id();
            $table->string('key', 20)->unique();
            $table->string('name', 60);
            $table->string('description', 255)->nullable();
            $table->string('color', 20)->default('stone');
            $table->json('permissions')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $all = array_map(fn (Permission $p) => $p->value, Permission::cases());
        $defaults = [
            ['owner', 'Superadmin', 'Akses penuh, termasuk akun staf, jabatan & hak akses, dan kunci API integrasi.', 'dark', $all],
            ['admin', 'Admin', 'Semua menu toko kecuali akun staf dan integrasi.', 'brand', array_values(array_diff($all, ['staff.manage', 'integrations.manage']))],
            ['warehouse', 'Staf Gudang', 'Memproses & mengirim pesanan, input resi, dan mengelola stok.', 'amber', ['orders.view', 'orders.manage', 'stock.manage']],
            ['cs', 'Customer Service', 'Melihat pesanan & pelanggan, membuat penjualan manual beserta tagihannya, serta memoderasi ulasan.', 'sky', ['orders.view', 'orders.create', 'customers.view', 'reviews.manage']],
        ];

        foreach ($defaults as $i => [$key, $name, $description, $color, $permissions]) {
            DB::table('staff_roles')->insert([
                'key' => $key,
                'name' => $name,
                'description' => $description,
                'color' => $color,
                'permissions' => json_encode($permissions),
                'sort_order' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Jabatan buatan sendiri tidak dikenal kode lama: staf yang memakainya dijadikan Staf Gudang
        // (hak akses paling sempit) supaya tidak otomatis jadi Superadmin karena kolomnya kosong.
        DB::table('users')->whereNotNull('staff_role')
            ->whereNotIn('staff_role', ['owner', 'admin', 'warehouse', 'cs'])
            ->update(['staff_role' => 'warehouse']);

        Schema::dropIfExists('staff_roles');
    }
};
