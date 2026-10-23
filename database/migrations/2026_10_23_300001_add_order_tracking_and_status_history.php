<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');
            $table->string('cancellation_reason')->nullable()->after('cancelled_at');
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('status', 30);
            $table->string('note')->nullable();
            // Kosong = perubahan oleh sistem (webhook, jadwal otomatis).
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable()->index();
        });

        $this->backfill();
    }

    /** Isi riwayat pesanan lama dari kolom waktu yang sudah ada. */
    private function backfill(): void
    {
        $steps = [
            'created_at' => 'pending_payment',
            'paid_at' => 'paid',
            'shipped_at' => 'shipped',
            'completed_at' => 'completed',
            'cancelled_at' => 'cancelled',
        ];

        DB::table('orders')->orderBy('id')->chunkById(200, function ($orders) use ($steps) {
            $rows = [];

            foreach ($orders as $order) {
                $previous = null;

                foreach ($steps as $column => $status) {
                    if ($order->{$column}) {
                        $rows[] = [
                            'order_id' => $order->id,
                            'from_status' => $previous,
                            'status' => $status,
                            'note' => null,
                            'user_id' => null,
                            'created_at' => $order->{$column},
                        ];
                        $previous = $status;
                    }
                }
            }

            if ($rows) {
                DB::table('order_status_histories')->insert($rows);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivered_at', 'cancellation_reason']);
        });
    }
};
