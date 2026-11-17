<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Penjualan manual boleh tanpa akun pelanggan (cukup nama + no. HP).
            $table->foreignId('user_id')->nullable()->change();
            // website = checkout pelanggan, manual = diinput staf di admin.
            $table->string('source', 20)->default('website')->after('status')->index();
            // Staf yang membuat penjualan manual.
            $table->foreignId('created_by')->nullable()->after('source')->constrained('users')->nullOnDelete();
            // Diskon khusus dari staf (di luar voucher), mengurangi total.
            $table->unsignedBigInteger('manual_discount')->default(0)->after('shipping_discount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'manual_discount']);
        });
    }
};
