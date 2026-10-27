<?php

namespace App\Services\Billing;

use App\Services\Promo\VoucherService;

/**
 * Menghitung tagihan pesanan: biaya layanan, PPN, dan pembulatan.
 *
 *   total = subtotal − diskon + ongkir − potongan ongkir + biaya layanan
 *           + PPN (bila belum termasuk harga) + pembulatan
 *
 * Biaya transaksi pembayaran tidak masuk total pesanan karena bergantung pada
 * metode yang dipilih pembeli (lihat FeeCalculator).
 */
class PriceCalculator
{
    /**
     * @return array{service_fee:int, tax_label:?string, tax_rate:float, tax_inclusive:bool, tax_dpp_other:bool, tax_base:int, tax_amount:int, rounding:int, total:int}
     */
    public function calculate(int $subtotal, int $discount, int $shipping, int $shippingDiscount, ?BillingSettings $settings = null): array
    {
        $settings ??= BillingSettings::current();

        $serviceFee = $settings->serviceFee();
        $tax = $this->tax($settings, max(0, $subtotal - $discount) + $serviceFee
            + ($settings->taxShipping() ? max(0, $shipping - $shippingDiscount) : 0));

        $beforeRounding = $subtotal - $discount + $shipping - $shippingDiscount + $serviceFee
            + ($tax['tax_inclusive'] ? 0 : $tax['tax_amount']);
        $beforeRounding = max(VoucherService::MIN_PAYABLE, $beforeRounding);

        $total = $this->round($beforeRounding, $settings);

        return [
            'service_fee' => $serviceFee,
            ...$tax,
            'rounding' => $total - $beforeRounding,
            'total' => $total,
        ];
    }

    /**
     * PPN atas nilai kena pajak. Belum termasuk harga: PPN = DPP × tarif.
     * Sudah termasuk: harga jual = nilai / (1 + tarif efektif), PPN = selisihnya.
     *
     * @return array{tax_label:?string, tax_rate:float, tax_inclusive:bool, tax_dpp_other:bool, tax_base:int, tax_amount:int}
     */
    public function tax(BillingSettings $settings, int $taxable): array
    {
        if (! $settings->taxEnabled() || $taxable <= 0) {
            return ['tax_label' => null, 'tax_rate' => 0.0, 'tax_inclusive' => false, 'tax_dpp_other' => false, 'tax_base' => 0, 'tax_amount' => 0];
        }

        $rate = $settings->taxRate();
        $dppRatio = $settings->taxDppOther() ? 11 / 12 : 1;

        if ($settings->taxInclusive()) {
            $sellingPrice = (int) round($taxable / (1 + $rate * $dppRatio / 100));
            $amount = $taxable - $sellingPrice;
        } else {
            $sellingPrice = $taxable;
            $amount = (int) round((int) round($sellingPrice * $dppRatio) * $rate / 100);
        }

        return [
            'tax_label' => $settings->taxLabel(),
            'tax_rate' => $rate,
            'tax_inclusive' => $settings->taxInclusive(),
            'tax_dpp_other' => $settings->taxDppOther(),
            'tax_base' => (int) round($sellingPrice * $dppRatio),
            'tax_amount' => $amount,
        ];
    }

    public function round(int $amount, BillingSettings $settings): int
    {
        $unit = $settings->roundingUnit();

        $rounded = match ($settings->roundingMode()) {
            'nearest' => (int) (round($amount / $unit) * $unit),
            'up' => (int) (ceil($amount / $unit) * $unit),
            'down' => (int) (floor($amount / $unit) * $unit),
            default => $amount,
        };

        // Pembulatan ke bawah tidak boleh membuat tagihan nol.
        return $rounded > 0 ? $rounded : $amount;
    }
}
