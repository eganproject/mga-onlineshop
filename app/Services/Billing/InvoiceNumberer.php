<?php

namespace App\Services\Billing;

use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Nomor invoice berurutan tanpa celah per periode (tahun/bulan/selamanya),
 * diambil dengan mengunci baris counter di tabel invoice_sequences.
 */
class InvoiceNumberer
{
    public const TOKENS = ['{PREFIX}', '{YYYY}', '{YY}', '{MM}', '{DD}', '{SEQ}', '{ORDER}'];

    /** Terbitkan nomor invoice untuk pesanan (idempoten). */
    public function issue(Order $order, ?BillingSettings $settings = null): string
    {
        if ($order->invoice_number) {
            return $order->invoice_number;
        }

        $settings ??= BillingSettings::current();

        return DB::transaction(function () use ($order, $settings) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if ($locked->invoice_number) {
                $order->forceFill($locked->only('invoice_number', 'invoice_issued_at'))->syncOriginalAttributes(['invoice_number', 'invoice_issued_at']);

                return $locked->invoice_number;
            }

            // Tanggal invoice = tanggal lunas (atau tanggal pesanan bila terbit saat dibuat).
            $date = ($locked->paid_at ?? $locked->created_at ?? now())->copy();
            $date = $date->isFuture() ? now() : $date;
            $scope = $this->scope($settings, $date);

            DB::table('invoice_sequences')->insertOrIgnore(['scope' => $scope, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $last = (int) DB::table('invoice_sequences')->where('scope', $scope)->lockForUpdate()->value('last_number');

            $attempts = 0;

            do {
                $last++;
                $number = $this->format($settings, $date, $last, $locked->code);

                // Format tanpa {SEQ} yang bentrok (mis. diubah di tengah jalan): beri akhiran.
                if (++$attempts > 20) {
                    $number = Str::limit($number, 50, '').'-'.$last;
                }
            } while (Order::where('invoice_number', $number)->exists());

            DB::table('invoice_sequences')->where('scope', $scope)->update(['last_number' => $last, 'updated_at' => now()]);

            Order::whereKey($locked->getKey())->update(['invoice_number' => $number, 'invoice_issued_at' => $date]);
            $order->forceFill(['invoice_number' => $number, 'invoice_issued_at' => $date])->syncOriginalAttributes(['invoice_number', 'invoice_issued_at']);

            return $number;
        });
    }

    /**
     * Terbitkan nomor untuk pesanan lunas yang belum punya (pesanan lama atau
     * data contoh), berurutan sesuai tanggal bayar. Mengembalikan jumlahnya.
     */
    public function backfill(): int
    {
        $count = 0;

        Order::query()
            ->whereNotNull('paid_at')
            ->whereNull('invoice_number')
            ->orderBy('paid_at')
            ->orderBy('id')
            ->lazyById(200)
            ->each(function (Order $order) use (&$count) {
                $this->issue($order);
                $count++;
            });

        return $count;
    }

    /** Contoh nomor untuk pratinjau di halaman pengaturan (tanpa memakai counter). */
    public function preview(BillingSettings $settings, ?int $sequence = null): string
    {
        $sequence ??= (int) DB::table('invoice_sequences')->where('scope', $this->scope($settings, now()))->value('last_number') + 1;

        return $this->format($settings, now(), $sequence, 'MGA-'.now()->format('ymd').'-AB12C');
    }

    public function format(BillingSettings $settings, CarbonInterface $date, int $sequence, string $orderCode): string
    {
        $format = (string) ($settings->get('invoice_format') ?: '{PREFIX}/{YYYY}/{MM}/{SEQ}');
        $padding = max(1, min(10, (int) $settings->get('invoice_padding', 5)));

        $number = strtr($format, [
            '{PREFIX}' => Str::upper(trim((string) $settings->get('invoice_prefix'))),
            '{YYYY}' => $date->format('Y'),
            '{YY}' => $date->format('y'),
            '{MM}' => $date->format('m'),
            '{DD}' => $date->format('d'),
            '{SEQ}' => str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT),
            '{ORDER}' => $orderCode,
        ]);

        return Str::limit(trim($number), 60, '');
    }

    private function scope(BillingSettings $settings, CarbonInterface $date): string
    {
        return match ($settings->get('invoice_reset')) {
            'monthly' => $date->format('Y-m'),
            'daily' => $date->format('Y-m-d'),
            'never' => 'all',
            default => $date->format('Y'),
        };
    }
}
