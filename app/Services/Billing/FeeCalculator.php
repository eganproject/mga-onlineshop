<?php

namespace App\Services\Billing;

use App\Data\PaymentCharge;
use App\Enums\PaymentMethod;

/**
 * Biaya transaksi Midtrans per metode bayar.
 *
 * - Biaya Midtrans (gateway fee) = (nominal × persen + biaya tetap) × (1 + PPN atas biaya).
 *   Ini perkiraan potongan yang ditanggung toko dari dana yang diterima.
 * - Bila metode diatur "ditanggung pembeli", biaya tersebut ditambahkan ke
 *   tagihan sebagai biaya transaksi. Dengan opsi "toko terima utuh" (gross-up)
 *   biaya dihitung dari total yang sudah termasuk biaya itu sendiri, jadi dana
 *   bersih yang diterima toko = total pesanan.
 */
class FeeCalculator
{
    public function settings(?BillingSettings $settings = null): BillingSettings
    {
        return $settings ?? BillingSettings::current();
    }

    /** Perkiraan potongan Midtrans untuk nominal yang dibayar. */
    public function gatewayFee(?PaymentMethod $method, int $amount, ?BillingSettings $settings = null): int
    {
        if (! $method || $amount <= 0) {
            return 0;
        }

        $settings = $this->settings($settings);
        $rate = $settings->method($method);
        $fee = ($amount * $rate['percent'] / 100 + $rate['flat']) * (1 + $settings->feeVat() / 100);

        return (int) ceil(round($fee, 4));
    }

    /** Biaya transaksi yang dibebankan ke pembeli untuk total pesanan ini. */
    public function buyerFee(PaymentMethod $method, int $orderTotal, ?BillingSettings $settings = null): int
    {
        $settings = $this->settings($settings);
        $rate = $settings->method($method);

        if ($rate['charge_to'] !== BillingSettings::CHARGE_BUYER || $orderTotal <= 0) {
            return 0;
        }

        if (! $settings->grossUp()) {
            return $this->gatewayFee($method, $orderTotal, $settings);
        }

        $vat = 1 + $settings->feeVat() / 100;
        $percent = $rate['percent'] / 100 * $vat;
        $fee = (int) ceil(round(($orderTotal * $percent + $rate['flat'] * $vat) / max(0.01, 1 - $percent), 4));

        // Pastikan setelah dipotong Midtrans toko tetap menerima minimal total pesanan.
        while ($orderTotal + $fee - $this->gatewayFee($method, $orderTotal + $fee, $settings) < $orderTotal) {
            $fee++;
        }

        return $fee;
    }

    /** Rincian tagihan untuk metode tertentu, atau untuk "semua metode" (pilih di Midtrans). */
    public function charge(int $orderTotal, ?PaymentMethod $method = null, ?BillingSettings $settings = null): PaymentCharge
    {
        $settings = $this->settings($settings);

        if (! $method) {
            $enabled = $settings->enabledMethods();

            return new PaymentCharge(
                amount: $orderTotal,
                method: null,
                fee: 0,
                gatewayFee: 0,
                enabledPayments: count($enabled) === count(PaymentMethod::cases()) ? null : array_map(fn (PaymentMethod $m) => $m->snapCode(), $enabled),
                feeLabel: $settings->paymentFeeLabel(),
            );
        }

        $fee = $this->buyerFee($method, $orderTotal, $settings);

        return new PaymentCharge(
            amount: $orderTotal + $fee,
            method: $method,
            fee: $fee,
            gatewayFee: $this->gatewayFee($method, $orderTotal + $fee, $settings),
            enabledPayments: [$method->snapCode()],
            feeLabel: $settings->paymentFeeLabel(),
        );
    }

    /**
     * Pembeli wajib memilih metode di halaman toko sebelum membayar, karena
     * ada metode aktif yang biayanya dibebankan ke pembeli.
     */
    public function requiresMethodChoice(?BillingSettings $settings = null): bool
    {
        $settings = $this->settings($settings);

        foreach ($settings->enabledMethods() as $method) {
            $rate = $settings->method($method);

            if ($rate['charge_to'] === BillingSettings::CHARGE_BUYER && ($rate['percent'] > 0 || $rate['flat'] > 0)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pilihan metode untuk halaman bayar, dikelompokkan.
     *
     * @return array<string, list<array{method: PaymentMethod, fee: int, amount: int}>>
     */
    public function options(int $orderTotal, ?BillingSettings $settings = null): array
    {
        $settings = $this->settings($settings);
        $groups = [];

        foreach ($settings->enabledMethods() as $method) {
            $fee = $this->buyerFee($method, $orderTotal, $settings);
            $groups[$method->group()][] = ['method' => $method, 'fee' => $fee, 'amount' => $orderTotal + $fee];
        }

        return $groups;
    }
}
