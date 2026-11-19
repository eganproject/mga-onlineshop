<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sumber ID wilayah alamat. RajaOngkir dan Biteship memakai ID wilayah yang
 * berbeda, jadi dicatat alamat ini dipilih dari data yang mana. Null = alamat
 * lama (RajaOngkir/simulasi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('region_source', 20)->nullable()->after('district_id');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn('region_source');
        });
    }
};
