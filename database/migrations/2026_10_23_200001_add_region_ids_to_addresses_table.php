<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alamat kini menyimpan ID wilayah bertingkat (provinsi → kota → kecamatan)
 * dari penyedia ongkir. Ongkir dihitung dari district_id.
 *
 * destination_id lama berasal dari pencarian lokasi yang ID-nya berbeda,
 * jadi tidak dipindahkan; alamat lama cukup diedit sekali oleh pelanggan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('province_id', 20)->nullable()->after('phone');
            $table->string('city_id', 20)->nullable()->after('province');
            $table->string('district_id', 20)->nullable()->after('city');
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn('destination_id');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('destination_id')->nullable();
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['province_id', 'city_id', 'district_id']);
        });
    }
};
