<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengiriman yang dibuat lewat Biteship dari detail pesanan (resi otomatis),
 * plus kode pos & pin lokasi tujuan yang dibutuhkan untuk memesan kurir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('destination_postal_code', 10)->nullable()->after('destination_id');
            $table->decimal('destination_latitude', 10, 7)->nullable()->after('destination_postal_code');
            $table->decimal('destination_longitude', 10, 7)->nullable()->after('destination_latitude');
            $table->string('shipment_id', 60)->nullable()->after('tracking_number');
            $table->string('shipment_tracking_id', 60)->nullable()->after('shipment_id');
            $table->string('shipment_status', 40)->nullable()->after('shipment_tracking_id');
            $table->string('shipment_method', 20)->nullable()->after('shipment_status');
            $table->unsignedInteger('shipment_price')->nullable()->after('shipment_method');
            $table->string('shipment_link', 500)->nullable()->after('shipment_price');
            $table->timestamp('shipment_booked_at')->nullable()->after('shipment_link');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'destination_postal_code', 'destination_latitude', 'destination_longitude',
                'shipment_id', 'shipment_tracking_id', 'shipment_status', 'shipment_method', 'shipment_price', 'shipment_link', 'shipment_booked_at',
            ]);
        });
    }
};
