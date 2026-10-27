<?php

use App\Services\Billing\InvoiceNumberer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Salinan perhitungan saat pesanan dibuat (pengaturan bisa berubah kemudian).
            $table->unsignedInteger('service_fee')->default(0)->after('shipping_discount');
            $table->string('tax_label', 20)->nullable()->after('service_fee');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('tax_label');
            $table->boolean('tax_inclusive')->default(false)->after('tax_rate');
            $table->boolean('tax_dpp_other')->default(false)->after('tax_inclusive');
            $table->unsignedBigInteger('tax_base')->default(0)->after('tax_dpp_other');
            $table->unsignedBigInteger('tax_amount')->default(0)->after('tax_base');
            $table->integer('rounding')->default(0)->after('tax_amount');

            // Diisi saat lunas: metode, biaya transaksi dari pembeli, perkiraan potongan Midtrans.
            $table->string('payment_method', 30)->nullable()->after('total');
            $table->unsignedInteger('payment_fee')->default(0)->after('payment_method');
            $table->unsignedInteger('gateway_fee')->default(0)->after('payment_fee');

            $table->string('invoice_number', 60)->nullable()->unique()->after('code');
            $table->timestamp('invoice_issued_at')->nullable()->after('invoice_number');

            // Data tagihan atas nama perusahaan (opsional, diisi pembeli saat checkout).
            $table->string('billing_name', 150)->nullable()->after('recipient_phone');
            $table->string('billing_npwp', 30)->nullable()->after('billing_name');
            $table->string('billing_address', 500)->nullable()->after('billing_npwp');
            $table->string('billing_email', 150)->nullable()->after('billing_address');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('method', 30)->nullable()->after('payment_type');
            $table->unsignedInteger('fee')->default(0)->after('amount');
            $table->unsignedInteger('gateway_fee')->default(0)->after('fee');
        });

        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->string('scope', 20)->primary();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        // Pesanan yang sudah lunas sebelum fitur ini langsung mendapat nomor invoice (urut tanggal bayar).
        app(InvoiceNumberer::class)->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['method', 'fee', 'gateway_fee']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['invoice_number']);
            $table->dropColumn([
                'service_fee', 'tax_label', 'tax_rate', 'tax_inclusive', 'tax_dpp_other', 'tax_base', 'tax_amount', 'rounding',
                'payment_method', 'payment_fee', 'gateway_fee', 'invoice_number', 'invoice_issued_at',
                'billing_name', 'billing_npwp', 'billing_address', 'billing_email',
            ]);
        });
    }
};
