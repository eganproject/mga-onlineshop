<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Payment;

/**
 * Baris rincian tagihan pesanan, dipakai bersama oleh halaman pesanan,
 * halaman bayar, invoice (HTML & PDF), dan email supaya angkanya selalu sama.
 */
class OrderSummary
{
    /**
     * @param  ?Payment  $payment  tagihan yang sedang dibayar (untuk menampilkan biaya transaksi sebelum lunas)
     * @return list<array{label: string, amount: int, tone: string, note?: string}>
     *                                                                              tone: normal | discount | info | subtotal | total
     */
    public static function rows(Order $order, ?Payment $payment = null): array
    {
        $rows = [];
        $quantity = $order->relationLoaded('items') ? (int) $order->items->sum('quantity') : null;

        $rows[] = ['label' => 'Subtotal'.($quantity ? " ({$quantity} item)" : ''), 'amount' => (int) $order->subtotal, 'tone' => 'normal'];

        if ($order->courier || $order->shipping_cost > 0) {
            $rows[] = ['label' => trim('Ongkir '.$order->courierLabel()), 'amount' => (int) $order->shipping_cost, 'tone' => 'normal'];
        }

        if ($order->discount > 0) {
            $rows[] = ['label' => 'Diskon voucher', 'amount' => -$order->discount, 'tone' => 'discount', 'note' => $order->voucher_code];
        }

        if ($order->shipping_discount > 0) {
            $rows[] = ['label' => 'Potongan ongkir', 'amount' => -$order->shipping_discount, 'tone' => 'discount', 'note' => $order->discount ? null : $order->voucher_code];
        }

        if ($order->service_fee > 0) {
            $rows[] = ['label' => config('shop.billing.service_fee_label') ?: 'Biaya layanan', 'amount' => (int) $order->service_fee, 'tone' => 'normal'];
        }

        if ($order->hasTax()) {
            $rows[] = $order->tax_inclusive
                ? ['label' => 'Termasuk '.$order->taxLabel(), 'amount' => (int) $order->tax_amount, 'tone' => 'info']
                : ['label' => $order->taxLabel(), 'amount' => (int) $order->tax_amount, 'tone' => 'normal'];
        }

        if ($order->rounding !== 0 && $order->rounding !== null) {
            $rows[] = ['label' => 'Pembulatan', 'amount' => (int) $order->rounding, 'tone' => 'normal'];
        }

        // Biaya transaksi: dari pesanan yang sudah lunas, atau dari tagihan yang sedang dibayar.
        $fee = $order->paid_at ? (int) $order->payment_fee : (int) ($payment?->fee ?? 0);
        $method = $order->paid_at ? $order->paymentMethodLabel() : $payment?->methodLabel();

        if ($fee > 0) {
            $rows[] = ['label' => 'Total belanja', 'amount' => (int) $order->total, 'tone' => 'subtotal'];
            $rows[] = ['label' => (config('shop.billing.payment_fee_label') ?: 'Biaya transaksi').($method ? " ({$method})" : ''), 'amount' => $fee, 'tone' => 'normal'];
            $rows[] = ['label' => 'Total bayar', 'amount' => (int) $order->total + $fee, 'tone' => 'total'];
        } else {
            $rows[] = ['label' => 'Total', 'amount' => (int) $order->total, 'tone' => 'total'];
        }

        return $rows;
    }

    /** Nominal yang harus/sudah dibayar pembeli. */
    public static function payable(Order $order, ?Payment $payment = null): int
    {
        return $order->paid_at ? $order->amountPaid() : (int) ($payment?->amount ?? $order->total);
    }

    public static function format(array $row): string
    {
        if ($row['tone'] === 'info') {
            return Money::rupiah($row['amount']);
        }

        return ($row['amount'] < 0 ? '−' : '').Money::rupiah(abs($row['amount']));
    }
}
