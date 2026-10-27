<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Services\Activity\ActivityLogger;
use App\Services\Billing\BillingSettings;
use App\Services\Billing\FeeCalculator;
use App\Services\Billing\InvoiceNumberer;
use App\Services\Billing\PriceCalculator;
use App\Services\Settings\StoreSettings;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin › Pajak & Biaya: PPN, biaya layanan, pembulatan, biaya transaksi
 * Midtrans per metode, serta identitas & penomoran invoice.
 */
class BillingSettingController extends Controller
{
    /** Kolom form (= kunci di bawah shop.billing). */
    public const FIELDS = [
        'tax_enabled', 'tax_label', 'tax_rate', 'tax_dpp', 'tax_mode', 'tax_shipping', 'tax_price_note',
        'service_fee', 'service_fee_label',
        'payment_fee_label', 'payment_fee_gross_up', 'payment_fee_vat', 'payment_methods',
        'rounding_mode', 'rounding_unit',
        'invoice_prefix', 'invoice_format', 'invoice_padding', 'invoice_reset', 'invoice_issue_on',
        'company_name', 'company_npwp', 'company_address', 'company_phone', 'company_email',
        'is_pkp', 'buyer_tax_info', 'invoice_notes', 'invoice_footer',
    ];

    public const BOOLEANS = ['tax_enabled', 'tax_shipping', 'tax_price_note', 'payment_fee_gross_up', 'is_pkp', 'buyer_tax_info'];

    /** Label untuk log aktivitas. */
    public const LABELS = [
        'tax_enabled' => 'PPN aktif', 'tax_label' => 'Nama pajak', 'tax_rate' => 'Tarif pajak (%)', 'tax_dpp' => 'Dasar pengenaan pajak',
        'tax_mode' => 'Cara hitung pajak', 'tax_shipping' => 'Pajak atas ongkir', 'tax_price_note' => 'Catatan pajak di harga',
        'service_fee' => 'Biaya layanan', 'service_fee_label' => 'Nama biaya layanan',
        'payment_fee_label' => 'Nama biaya transaksi', 'payment_fee_gross_up' => 'Toko terima utuh (gross-up)',
        'payment_fee_vat' => 'PPN atas biaya Midtrans (%)', 'payment_methods' => 'Tarif metode pembayaran',
        'rounding_mode' => 'Pembulatan', 'rounding_unit' => 'Kelipatan pembulatan',
        'invoice_prefix' => 'Awalan invoice', 'invoice_format' => 'Format nomor invoice', 'invoice_padding' => 'Panjang nomor urut',
        'invoice_reset' => 'Nomor urut diulang', 'invoice_issue_on' => 'Invoice terbit saat',
        'company_name' => 'Nama penerbit', 'company_npwp' => 'NPWP toko', 'company_address' => 'Alamat penerbit',
        'company_phone' => 'Telepon penerbit', 'company_email' => 'Email penerbit', 'is_pkp' => 'Status PKP',
        'buyer_tax_info' => 'Invoice atas nama perusahaan', 'invoice_notes' => 'Catatan invoice', 'invoice_footer' => 'Kaki invoice',
    ];

    public const ROUNDING_UNITS = [1 => 'Rp1', 100 => 'Rp100', 500 => 'Rp500', 1000 => 'Rp1.000'];

    public function __construct(private readonly StoreSettings $settings) {}

    public function edit(InvoiceNumberer $numberer): View
    {
        $billing = BillingSettings::current();

        return view('admin.billing', [
            'values' => collect(self::FIELDS)->mapWithKeys(fn (string $f) => [$f => $billing->get($f)])->all(),
            'methods' => $billing->methods(),
            'invoicePreview' => $numberer->preview($billing),
            'company' => $billing->company(),
            'paymentDriver' => config('shop.payment.driver'),
        ]);
    }

    public function update(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $data = $this->validated($request);

        $changes = $this->settings->save(collect($data)->mapWithKeys(fn ($v, $k) => ["shop.billing.{$k}" => $v])->all());

        if ($changes) {
            $logger->log('settings.billing', 'Mengubah pengaturan pajak & biaya', properties: [
                'perubahan' => collect($changes)->mapWithKeys(function (array $pair, string $key) {
                    $field = str_replace('shop.billing.', '', $key);

                    return [self::LABELS[$field] ?? $field => $field === 'payment_methods'
                        ? 'Tarif diperbarui'
                        : ['dari' => $this->display($pair[0]), 'menjadi' => $this->display($pair[1])]];
                })->all(),
            ]);
        }

        return back()->with('success', $changes ? 'Pengaturan pajak & biaya disimpan. Berlaku untuk pesanan baru.' : 'Tidak ada perubahan.');
    }

    /**
     * Simulasi dari isian form yang belum disimpan: rincian tagihan contoh,
     * biaya tiap metode, dan contoh nomor invoice.
     */
    public function preview(Request $request, PriceCalculator $prices, FeeCalculator $fees, InvoiceNumberer $numberer): JsonResponse
    {
        try {
            $data = $this->validated($request);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Periksa kembali isian yang ditandai.', 'errors' => $e->errors()], 422);
        }

        $settings = BillingSettings::withOverrides($data);
        $subtotal = max(1, min(1_000_000_000, (int) preg_replace('/\D/', '', (string) $request->input('sample_subtotal', 1000000))));
        $shipping = max(0, min(100_000_000, (int) preg_replace('/\D/', '', (string) $request->input('sample_shipping', 25000))));

        $charges = $prices->calculate($subtotal, 0, $shipping, 0, $settings);
        $rows = [['Subtotal produk', $subtotal], ['Ongkir', $shipping]];

        if ($charges['service_fee']) {
            $rows[] = [$settings->serviceFeeLabel(), $charges['service_fee']];
        }
        if ($charges['tax_amount']) {
            $label = $settings->taxLabel().' '.BillingSettings::percent($settings->taxRate()).($settings->taxDppOther() ? ' × DPP 11/12' : '');
            $rows[] = [($settings->taxInclusive() ? 'Termasuk ' : '').$label, $charges['tax_amount'], $settings->taxInclusive()];
        }
        if ($charges['rounding']) {
            $rows[] = ['Pembulatan', $charges['rounding']];
        }

        $methods = [];
        foreach ($settings->enabledMethods() as $method) {
            $charge = $fees->charge($charges['total'], $method, $settings);
            $methods[] = [
                'label' => $method->shortLabel(),
                'buyer' => $charge->fee,
                'paid' => $charge->amount,
                'gateway' => $charge->gatewayFee,
                'net' => $charge->amount - $charge->gatewayFee,
            ];
        }

        return response()->json([
            'rows' => array_map(fn (array $r) => ['label' => $r[0], 'amount' => Money::rupiah(abs($r[1])), 'negative' => $r[1] < 0, 'info' => $r[2] ?? false], $rows),
            'total' => Money::rupiah($charges['total']),
            'dpp' => $charges['tax_amount'] ? Money::rupiah($charges['tax_base']) : null,
            'methods' => array_map(fn (array $m) => [
                'label' => $m['label'],
                'buyer' => $m['buyer'] ? Money::rupiah($m['buyer']) : '—',
                'paid' => Money::rupiah($m['paid']),
                'gateway' => Money::rupiah($m['gateway']),
                'net' => Money::rupiah($m['net']),
            ], $methods),
            'choice' => $fees->requiresMethodChoice($settings),
            'invoice' => $numberer->preview($settings),
            'priceNote' => $settings->priceNote(),
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        foreach (self::BOOLEANS as $field) {
            $request->merge([$field => $request->boolean($field)]);
        }

        $request->merge([
            'service_fee' => $this->digits($request->input('service_fee')) ?? 0,
            'company_npwp' => preg_replace('/\D+/', '', (string) $request->input('company_npwp')) ?: null,
            'tax_rate' => $this->decimal($request->input('tax_rate')),
            'payment_fee_vat' => $this->decimal($request->input('payment_fee_vat')) ?? 0,
            'invoice_prefix' => strtoupper(trim((string) $request->input('invoice_prefix'))),
            'methods' => collect(PaymentMethod::cases())->mapWithKeys(fn (PaymentMethod $m) => [$m->value => [
                'enabled' => (bool) $request->input("methods.{$m->value}.enabled"),
                'percent' => $this->decimal($request->input("methods.{$m->value}.percent")) ?? 0,
                'flat' => $this->digits($request->input("methods.{$m->value}.flat")) ?? 0,
                'charge_to' => $request->input("methods.{$m->value}.charge_to"),
            ]])->all(),
        ]);

        $data = $request->validate([
            'tax_enabled' => ['boolean'],
            'tax_label' => ['required', 'string', 'max:20'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'tax_dpp' => ['required', Rule::in(['full', 'other'])],
            'tax_mode' => ['required', Rule::in(['exclusive', 'inclusive'])],
            'tax_shipping' => ['boolean'],
            'tax_price_note' => ['boolean'],
            'service_fee' => ['integer', 'min:0', 'max:10000000'],
            'service_fee_label' => ['required', 'string', 'max:40'],
            'payment_fee_label' => ['required', 'string', 'max:40'],
            'payment_fee_gross_up' => ['boolean'],
            'payment_fee_vat' => ['numeric', 'min:0', 'max:100'],
            'methods' => ['array'],
            'methods.*.enabled' => ['boolean'],
            'methods.*.percent' => ['numeric', 'min:0', 'max:50'],
            'methods.*.flat' => ['integer', 'min:0', 'max:1000000'],
            'methods.*.charge_to' => ['required', Rule::in([BillingSettings::CHARGE_BUYER, BillingSettings::CHARGE_STORE])],
            'rounding_mode' => ['required', Rule::in(['none', 'nearest', 'up', 'down'])],
            'rounding_unit' => ['required', 'integer', Rule::in(array_keys(self::ROUNDING_UNITS))],
            'invoice_prefix' => ['nullable', 'string', 'max:12', 'regex:/^[A-Z0-9\-]*$/'],
            'invoice_format' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9\/\-_.{}]+$/', function ($attribute, $value, $fail) {
                if (! str_contains($value, '{SEQ}') && ! str_contains($value, '{ORDER}')) {
                    $fail('Format nomor invoice wajib memuat {SEQ} (nomor urut) atau {ORDER} (kode pesanan).');
                }
                if (preg_match_all('/\{[A-Z]+\}/', $value, $m) && array_diff($m[0], InvoiceNumberer::TOKENS)) {
                    $fail('Token yang dikenal: '.implode(' ', InvoiceNumberer::TOKENS).'.');
                }
            }],
            'invoice_padding' => ['required', 'integer', 'min:1', 'max:10'],
            'invoice_reset' => ['required', Rule::in(['yearly', 'monthly', 'never'])],
            'invoice_issue_on' => ['required', Rule::in(['paid', 'created'])],
            'company_name' => ['nullable', 'string', 'max:150'],
            'company_npwp' => ['nullable', 'regex:/^\d{15,16}$/'],
            'company_address' => ['nullable', 'string', 'max:500'],
            'company_phone' => ['nullable', 'string', 'max:40'],
            'company_email' => ['nullable', 'email', 'max:150'],
            'is_pkp' => ['boolean'],
            'buyer_tax_info' => ['boolean'],
            'invoice_notes' => ['nullable', 'string', 'max:1000'],
            'invoice_footer' => ['nullable', 'string', 'max:300'],
        ], [
            'invoice_prefix.regex' => 'Awalan hanya boleh huruf, angka, dan tanda hubung.',
            'invoice_format.regex' => 'Format hanya boleh huruf, angka, / - _ . dan token seperti {SEQ}.',
            'company_npwp.regex' => 'NPWP berisi 15 atau 16 digit angka.',
            'methods.*.percent.max' => 'Persentase biaya maksimal 50%.',
        ], [
            'tax_label' => 'nama pajak', 'tax_rate' => 'tarif pajak', 'service_fee' => 'biaya layanan',
            'service_fee_label' => 'nama biaya layanan', 'payment_fee_label' => 'nama biaya transaksi',
            'payment_fee_vat' => 'PPN atas biaya Midtrans', 'rounding_unit' => 'kelipatan pembulatan',
            'invoice_format' => 'format nomor invoice', 'invoice_padding' => 'panjang nomor urut',
            'company_email' => 'email penerbit',
        ]);

        if (! collect($data['methods'])->contains('enabled', true)) {
            throw ValidationException::withMessages(['methods' => 'Aktifkan minimal satu metode pembayaran.']);
        }

        $data['payment_methods'] = $data['methods'];
        unset($data['methods']);

        return $data;
    }

    private function digits(mixed $value): ?int
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits === '' ? null : (int) $digits;
    }

    private function decimal(mixed $value): ?float
    {
        $value = trim(str_replace(',', '.', (string) $value));

        return is_numeric($value) ? (float) $value : null;
    }

    private function display(mixed $value): mixed
    {
        return match (true) {
            is_bool($value) => $value ? 'Aktif' : 'Nonaktif',
            is_array($value) => json_encode($value),
            default => $value,
        };
    }
}
