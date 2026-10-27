<?php

namespace App\Services\Billing;

use App\Enums\PaymentMethod;

/**
 * Pengaturan pajak, biaya, pembulatan & invoice yang sedang berlaku
 * (config `shop.billing`, bisa ditimpa dari Admin › Pajak & Biaya).
 *
 * Dibungkus objek supaya kalkulator bisa dipakai juga untuk simulasi dengan
 * nilai yang belum disimpan (pratinjau di halaman pengaturan).
 */
final class BillingSettings
{
    public const CHARGE_BUYER = 'buyer';

    public const CHARGE_STORE = 'store';

    public function __construct(private readonly array $values) {}

    public static function current(): self
    {
        return new self((array) config('shop.billing', []));
    }

    /** Pengaturan berlaku yang ditimpa sebagian (untuk pratinjau). */
    public static function withOverrides(array $overrides): self
    {
        return new self(array_replace((array) config('shop.billing', []), $overrides));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function taxEnabled(): bool
    {
        return (bool) $this->get('tax_enabled') && $this->taxRate() > 0;
    }

    public function taxLabel(): string
    {
        return trim((string) $this->get('tax_label')) ?: 'PPN';
    }

    public function taxRate(): float
    {
        return max(0.0, min(100.0, (float) $this->get('tax_rate', 0)));
    }

    public function taxInclusive(): bool
    {
        return $this->get('tax_mode') === 'inclusive';
    }

    /** DPP nilai lain 11/12 (PMK 131/2024). */
    public function taxDppOther(): bool
    {
        return $this->get('tax_dpp') === 'other';
    }

    public function taxShipping(): bool
    {
        return (bool) $this->get('tax_shipping');
    }

    /** Tarif efektif terhadap harga jual, mis. 12% × 11/12 = 11%. */
    public function effectiveTaxRate(): float
    {
        return $this->taxRate() * ($this->taxDppOther() ? 11 / 12 : 1);
    }

    /** Catatan harga di halaman produk/keranjang, mis. "Harga belum termasuk PPN 11%". */
    public function priceNote(): ?string
    {
        if (! $this->taxEnabled() || ! $this->get('tax_price_note')) {
            return null;
        }

        return ($this->taxInclusive() ? 'Harga sudah termasuk ' : 'Harga belum termasuk ')
            .$this->taxLabel().' '.self::percent($this->effectiveTaxRate());
    }

    public function serviceFee(): int
    {
        return max(0, (int) $this->get('service_fee', 0));
    }

    public function serviceFeeLabel(): string
    {
        return trim((string) $this->get('service_fee_label')) ?: 'Biaya layanan';
    }

    public function paymentFeeLabel(): string
    {
        return trim((string) $this->get('payment_fee_label')) ?: 'Biaya transaksi';
    }

    public function grossUp(): bool
    {
        return (bool) $this->get('payment_fee_gross_up');
    }

    /** PPN atas biaya Midtrans (%), ditambahkan ke perkiraan biaya. */
    public function feeVat(): float
    {
        return max(0.0, min(100.0, (float) $this->get('payment_fee_vat', 0)));
    }

    /**
     * Tarif satu metode, dilengkapi nilai standar bila belum pernah diatur.
     *
     * @return array{enabled: bool, percent: float, flat: int, charge_to: string}
     */
    public function method(PaymentMethod $method): array
    {
        $stored = (array) (((array) $this->get('payment_methods', []))[$method->value] ?? []);
        $default = $method->defaultRate();

        return [
            'enabled' => (bool) ($stored['enabled'] ?? true),
            'percent' => max(0.0, min(50.0, (float) ($stored['percent'] ?? $default['percent']))),
            'flat' => max(0, (int) ($stored['flat'] ?? $default['flat'])),
            'charge_to' => ($stored['charge_to'] ?? self::CHARGE_STORE) === self::CHARGE_BUYER ? self::CHARGE_BUYER : self::CHARGE_STORE,
        ];
    }

    /** @return array<string, array{enabled: bool, percent: float, flat: int, charge_to: string}> */
    public function methods(): array
    {
        $rates = [];

        foreach (PaymentMethod::cases() as $method) {
            $rates[$method->value] = $this->method($method);
        }

        return $rates;
    }

    /** @return list<PaymentMethod> */
    public function enabledMethods(): array
    {
        return array_values(array_filter(PaymentMethod::cases(), fn (PaymentMethod $m) => $this->method($m)['enabled']));
    }

    public function roundingMode(): string
    {
        $mode = (string) $this->get('rounding_mode', 'none');

        return in_array($mode, ['nearest', 'up', 'down'], true) ? $mode : 'none';
    }

    public function roundingUnit(): int
    {
        return max(1, (int) $this->get('rounding_unit', 1));
    }

    public function invoiceIssueOn(): string
    {
        return $this->get('invoice_issue_on') === 'created' ? 'created' : 'paid';
    }

    public function buyerTaxInfo(): bool
    {
        return (bool) $this->get('buyer_tax_info', true);
    }

    /** Data penerbit invoice (jatuh ke identitas toko bila kosong). */
    public function company(): array
    {
        return [
            'name' => $this->get('company_name') ?: config('shop.full_name'),
            'npwp' => $this->get('company_npwp'),
            'address' => $this->get('company_address') ?: config('shop.address'),
            'phone' => $this->get('company_phone') ?: (config('shop.whatsapp') ? '+'.config('shop.whatsapp') : null),
            'email' => $this->get('company_email') ?: config('shop.email'),
            'is_pkp' => (bool) $this->get('is_pkp'),
        ];
    }

    /** Data untuk perhitungan ulang di browser (checkout). */
    public function toClient(): array
    {
        return [
            'tax' => $this->taxEnabled(),
            'taxRate' => $this->taxRate(),
            'taxDppOther' => $this->taxDppOther(),
            'taxInclusive' => $this->taxInclusive(),
            'taxShipping' => $this->taxShipping(),
            'taxLabel' => $this->taxLabel().' '.self::percent($this->effectiveTaxRate()),
            'serviceFee' => $this->serviceFee(),
            'roundingMode' => $this->roundingMode(),
            'roundingUnit' => $this->roundingUnit(),
        ];
    }

    /** NPWP 15 digit → 01.234.567.8-901.234; 16 digit (NIK) ditampilkan apa adanya. */
    public static function formatNpwp(?string $npwp): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $npwp);

        if (strlen($digits) !== 15) {
            return $npwp ?: null;
        }

        return vsprintf('%s.%s.%s.%s-%s.%s', [substr($digits, 0, 2), substr($digits, 2, 3), substr($digits, 5, 3), substr($digits, 8, 1), substr($digits, 9, 3), substr($digits, 12, 3)]);
    }

    /** 11 → "11%", 0.7 → "0,7%", 11.0000001 → "11%". */
    public static function percent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',').'%';
    }
}
