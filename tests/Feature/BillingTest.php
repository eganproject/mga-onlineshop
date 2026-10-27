<?php

namespace Tests\Feature;

use App\Data\PaymentCharge;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\StaffRole;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Billing\BillingSettings;
use App\Services\Billing\FeeCalculator;
use App\Services\Billing\PriceCalculator;
use App\Services\Payment\MidtransPaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** PPN, biaya layanan, pembulatan, biaya transaksi, nomor invoice & PDF. */
class BillingTest extends TestCase
{
    use RefreshDatabase;

    private function settings(array $values): BillingSettings
    {
        return BillingSettings::withOverrides($values);
    }

    public function test_exclusive_tax_is_added_to_total(): void
    {
        $result = app(PriceCalculator::class)->calculate(1_000_000, 100_000, 20_000, 0, $this->settings([
            'tax_enabled' => true, 'tax_rate' => 11, 'tax_mode' => 'exclusive', 'tax_dpp' => 'full',
        ]));

        $this->assertSame(900_000, $result['tax_base']);
        $this->assertSame(99_000, $result['tax_amount']);
        $this->assertSame(900_000 + 20_000 + 99_000, $result['total']);
        $this->assertFalse($result['tax_inclusive']);
    }

    public function test_twelve_percent_with_other_dpp_equals_eleven_percent_burden(): void
    {
        $result = app(PriceCalculator::class)->calculate(1_200_000, 0, 0, 0, $this->settings([
            'tax_enabled' => true, 'tax_rate' => 12, 'tax_dpp' => 'other', 'tax_mode' => 'exclusive',
        ]));

        $this->assertSame(1_100_000, $result['tax_base']);
        $this->assertSame(132_000, $result['tax_amount']);
        $this->assertTrue($result['tax_dpp_other']);
    }

    public function test_inclusive_tax_does_not_change_total(): void
    {
        $result = app(PriceCalculator::class)->calculate(1_110_000, 0, 15_000, 0, $this->settings([
            'tax_enabled' => true, 'tax_rate' => 11, 'tax_mode' => 'inclusive',
        ]));

        $this->assertSame(110_000, $result['tax_amount']);
        $this->assertSame(1_000_000, $result['tax_base']);
        $this->assertSame(1_125_000, $result['total']);
    }

    public function test_service_fee_shipping_tax_and_rounding(): void
    {
        $calc = app(PriceCalculator::class);
        $base = ['tax_enabled' => true, 'tax_rate' => 11, 'tax_shipping' => true, 'service_fee' => 2_500];

        $result = $calc->calculate(100_000, 0, 12_000, 2_000, $this->settings($base + ['rounding_mode' => 'up', 'rounding_unit' => 1000]));
        // Kena pajak: 100.000 + 2.500 + (12.000 − 2.000) = 112.500 → PPN 12.375.
        $this->assertSame(12_375, $result['tax_amount']);
        $this->assertSame(2_500, $result['service_fee']);
        $this->assertSame(125_000, $result['total']);
        $this->assertSame(125_000 - (100_000 + 10_000 + 2_500 + 12_375), $result['rounding']);

        $down = $calc->calculate(100_000, 0, 12_000, 2_000, $this->settings($base + ['rounding_mode' => 'down', 'rounding_unit' => 1000]));
        $this->assertSame(124_000, $down['total']);
        $this->assertSame(-875, $down['rounding']);
    }

    public function test_gateway_and_buyer_fees(): void
    {
        $fees = app(FeeCalculator::class);
        $settings = $this->settings(['payment_methods' => [
            'qris' => ['enabled' => true, 'percent' => 0.7, 'flat' => 0, 'charge_to' => 'buyer'],
            'bca_va' => ['enabled' => true, 'percent' => 0, 'flat' => 4000, 'charge_to' => 'store'],
        ]]);

        $this->assertSame(7_000, $fees->gatewayFee(PaymentMethod::Qris, 1_000_000, $settings));
        $this->assertSame(4_000, $fees->gatewayFee(PaymentMethod::BcaVa, 1_000_000, $settings));
        $this->assertSame(7_000, $fees->buyerFee(PaymentMethod::Qris, 1_000_000, $settings));
        $this->assertSame(0, $fees->buyerFee(PaymentMethod::BcaVa, 1_000_000, $settings));
        $this->assertTrue($fees->requiresMethodChoice($settings));

        // PPN atas biaya Midtrans 11%.
        $withVat = $this->settings(['payment_fee_vat' => 11] + ['payment_methods' => ['bca_va' => ['flat' => 4000]]]);
        $this->assertSame(4_440, $fees->gatewayFee(PaymentMethod::BcaVa, 50_000, $withVat));

        // Gross-up: setelah dipotong Midtrans, toko tetap menerima total pesanan.
        $grossUp = $this->settings(['payment_fee_gross_up' => true, 'payment_methods' => ['gopay' => ['percent' => 2, 'charge_to' => 'buyer']]]);
        $charge = $fees->charge(1_000_000, PaymentMethod::Gopay, $grossUp);
        $this->assertGreaterThanOrEqual(1_000_000, $charge->amount - $charge->gatewayFee);
        $this->assertSame(['gopay'], $charge->enabledPayments);
    }

    public function test_store_absorbed_fees_do_not_require_method_choice(): void
    {
        $this->assertFalse(app(FeeCalculator::class)->requiresMethodChoice(BillingSettings::current()));

        $charge = app(FeeCalculator::class)->charge(50_000, null, $this->settings(['payment_methods' => ['akulaku' => ['enabled' => false]]]));
        $this->assertSame(50_000, $charge->amount);
        $this->assertNotContains('akulaku', $charge->enabledPayments);
        $this->assertContains('other_qris', $charge->enabledPayments);
    }

    public function test_payment_method_is_detected_from_midtrans_payload(): void
    {
        $this->assertSame(PaymentMethod::BcaVa, PaymentMethod::fromMidtrans('bank_transfer', ['va_numbers' => [['bank' => 'bca', 'va_number' => '1']]]));
        $this->assertSame(PaymentMethod::PermataVa, PaymentMethod::fromMidtrans('bank_transfer', ['permata_va_number' => '1']));
        $this->assertSame(PaymentMethod::MandiriBill, PaymentMethod::fromMidtrans('echannel'));
        $this->assertSame(PaymentMethod::Alfamart, PaymentMethod::fromMidtrans('cstore', ['store' => 'alfamart']));
        $this->assertSame(PaymentMethod::Qris, PaymentMethod::fromMidtrans('qris'));
        $this->assertNull(PaymentMethod::fromMidtrans('unknown_type'));
    }

    public function test_checkout_saves_tax_and_company_billing_info(): void
    {
        config(['shop.billing.tax_enabled' => true, 'shop.billing.tax_rate' => 11, 'shop.billing.service_fee' => 1000]);

        [$order] = $this->checkout(extra: [
            'company_invoice' => '1', 'billing_name' => 'PT Maju Jaya', 'billing_npwp' => '01.234.567.8-901.234',
        ]);

        $taxable = $order->subtotal + 1000;
        $this->assertSame((int) round($taxable * 0.11), $order->tax_amount);
        $this->assertSame($order->subtotal + $order->shipping_cost + 1000 + $order->tax_amount, $order->total);
        $this->assertSame('PT Maju Jaya', $order->billing_name);
        $this->assertSame('012345678901234', $order->billing_npwp);
        $this->assertSame('01.234.567.8-901.234', BillingSettings::formatNpwp($order->billing_npwp));
        $this->assertSame($order->total, $order->latestPayment->amount);
    }

    public function test_company_fields_are_ignored_when_checkbox_is_off(): void
    {
        [$order] = $this->checkout(extra: ['company_invoice' => '0', 'billing_name' => 'PT Abaikan']);

        $this->assertNull($order->billing_name);
    }

    public function test_midtrans_item_details_match_gross_amount_with_tax_rounding_and_fee(): void
    {
        config([
            'shop.billing.tax_enabled' => true, 'shop.billing.tax_rate' => 11, 'shop.billing.service_fee' => 1500,
            'shop.billing.rounding_mode' => 'nearest', 'shop.billing.rounding_unit' => 500,
            'shop.billing.payment_methods' => ['qris' => ['percent' => 0.7, 'charge_to' => 'buyer']],
        ]);

        [$order] = $this->checkout();
        $charge = app(FeeCalculator::class)->charge($order->total, PaymentMethod::Qris);
        $items = MidtransPaymentGateway::itemDetails($order->load('items'), $charge);

        $this->assertSame($charge->amount, array_sum(array_map(fn ($i) => $i['price'] * $i['quantity'], $items)));
        $ids = array_column($items, 'id');
        $this->assertContains('TAX', $ids);
        $this->assertContains('SERVICE', $ids);
        $this->assertContains('FEE', $ids);
        $this->assertNotContains('ADJUSTMENT', $ids);
    }

    public function test_buyer_fee_flow_chooses_method_then_records_fees_and_invoice(): void
    {
        config(['shop.billing.payment_methods' => [
            'qris' => ['percent' => 0.7, 'charge_to' => 'buyer'],
            'bca_va' => ['flat' => 4000, 'charge_to' => 'store'],
        ]]);

        [$order, $customer] = $this->checkout();
        $this->assertSame(0, $order->payments()->count(), 'Tagihan dibuat setelah metode dipilih.');

        $this->get(route('account.orders.payment', $order))
            ->assertOk()
            ->assertSee('Pilih metode pembayaran')
            ->assertSee('QRIS')
            ->assertSee('Tanpa biaya');

        $this->post(route('account.orders.payment.method', $order), ['method' => 'qris'])
            ->assertRedirect(route('account.orders.payment', $order));

        $payment = $order->latestPayment()->first();
        $fee = (int) ceil($order->total * 0.007);
        $this->assertSame('qris', $payment->method);
        $this->assertSame($fee, $payment->fee);
        $this->assertSame($order->total + $fee, $payment->amount);

        $this->get(route('account.orders.payment', $order))->assertOk()->assertSee('Biaya transaksi (QRIS)');

        // Ganti ke VA (ditanggung toko): tagihan lama batal, tagihan baru tanpa biaya.
        $this->post(route('account.orders.payment.method', $order), ['method' => 'bca_va']);
        $this->assertSame(2, $order->payments()->count());
        $this->assertSame($order->total, $order->latestPayment()->first()->amount);
        $this->post(route('account.orders.payment.method', $order), ['method' => 'qris']);

        $this->post(route('account.orders.simulate-payment', $order));
        $order->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('qris', $order->payment_method);
        $this->assertSame($fee, $order->payment_fee);
        $this->assertSame((int) ceil(($order->total + $fee) * 0.007), $order->gateway_fee);
        $this->assertSame($order->total + $fee - $order->gateway_fee, $order->netReceived());
        $this->assertSame('INV/'.now()->format('Y/m').'/00001', $order->invoice_number);

        $this->get(route('account.orders.invoice', $order))
            ->assertOk()
            ->assertSee('INVOICE')
            ->assertSee($order->invoice_number)
            ->assertSee('Total bayar');

        $pdf = $this->get(route('account.orders.invoice.pdf', $order))->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringContainsString('invoice-inv-', $pdf->headers->get('Content-Disposition'));
    }

    public function test_invalid_or_disabled_method_is_rejected(): void
    {
        config(['shop.billing.payment_methods' => [
            'qris' => ['percent' => 0.7, 'charge_to' => 'buyer'],
            'kredivo' => ['enabled' => false],
        ]]);
        [$order] = $this->checkout();

        $this->post(route('account.orders.payment.method', $order), ['method' => 'bitcoin'])->assertSessionHasErrors('method');
        $this->post(route('account.orders.payment.method', $order), ['method' => 'kredivo'])->assertSessionHasErrors('method');
        $this->assertSame(0, $order->payments()->count());
    }

    public function test_invoice_numbers_are_sequential_and_follow_format(): void
    {
        config([
            'shop.billing.invoice_prefix' => 'MGA',
            'shop.billing.invoice_format' => '{PREFIX}-{YY}{MM}-{SEQ}',
            'shop.billing.invoice_padding' => 3,
            'shop.billing.invoice_reset' => 'monthly',
        ]);

        $first = Order::factory()->create(['paid_at' => null]);
        $this->assertNull($first->invoice_number, 'Belum lunas: belum ada nomor invoice.');

        $first->update(['status' => OrderStatus::Paid, 'paid_at' => now()]);
        $second = Order::factory()->create(['status' => OrderStatus::Paid, 'paid_at' => now()]);

        $prefix = 'MGA-'.now()->format('ym').'-';
        $this->assertSame($prefix.'001', $first->refresh()->invoice_number);
        $this->assertSame($prefix.'002', $second->refresh()->invoice_number);

        $this->travel(1)->months();
        $third = Order::factory()->create(['status' => OrderStatus::Paid, 'paid_at' => now()]);
        $this->assertSame('MGA-'.now()->format('ym').'-001', $third->refresh()->invoice_number);
    }

    public function test_invoice_can_be_issued_when_order_is_created(): void
    {
        config(['shop.billing.invoice_issue_on' => 'created']);

        $order = Order::factory()->create(['paid_at' => null]);

        $this->assertNotNull($order->refresh()->invoice_number);
    }

    public function test_admin_sees_proforma_and_finance_for_order(): void
    {
        config(['shop.billing.tax_enabled' => true, 'shop.billing.tax_rate' => 11]);
        [$order] = $this->checkout();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.orders.invoice', $order))
            ->assertOk()->assertSee('TAGIHAN')->assertSee('BELUM DIBAYAR')->assertSee('Rincian pajak');
        $this->get(route('admin.orders.invoice.pdf', $order))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Belum terbit')->assertSee('PPN 11%');
    }

    public function test_billing_settings_page_saves_values(): void
    {
        $admin = User::factory()->admin(StaffRole::Admin)->create();

        $this->actingAs($admin)->get(route('admin.billing.edit'))->assertOk()->assertSee('Pajak & Biaya')->assertSee('BCA Virtual Account');

        $this->put(route('admin.billing.update'), $this->form([
            'tax_enabled' => '1', 'tax_rate' => '12', 'tax_dpp' => 'other', 'service_fee' => '2.000',
            'company_npwp' => '01.234.567.8-901.234', 'is_pkp' => '1',
        ], ['qris' => ['charge_to' => 'buyer', 'percent' => '0,7']]))->assertSessionHasNoErrors()->assertSessionHas('success');

        $billing = BillingSettings::current();
        $this->assertTrue($billing->taxEnabled());
        $this->assertSame(12.0, $billing->taxRate());
        $this->assertTrue($billing->taxDppOther());
        $this->assertSame(2000, $billing->serviceFee());
        $this->assertSame('012345678901234', $billing->get('company_npwp'));
        $this->assertSame('buyer', $billing->method(PaymentMethod::Qris)['charge_to']);
        $this->assertSame(0.7, $billing->method(PaymentMethod::Qris)['percent']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'settings.billing']);
    }

    public function test_billing_settings_validation_and_preview(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->put(route('admin.billing.update'), $this->form(['invoice_format' => '{PREFIX}/{YYYY}']))->assertSessionHasErrors('invoice_format');
        $this->put(route('admin.billing.update'), $this->form([], array_fill_keys(array_column(PaymentMethod::cases(), 'value'), ['enabled' => '0'])))->assertSessionHasErrors('methods');

        $this->postJson(route('admin.billing.preview'), $this->form(['tax_enabled' => '1', 'tax_rate' => '11', 'sample_subtotal' => '1.000.000', 'sample_shipping' => '0']))
            ->assertOk()
            ->assertJsonPath('total', 'Rp1.110.000')
            ->assertJsonPath('invoice', 'INV/'.now()->format('Y/m').'/00001')
            ->assertJsonPath('methods.0.label', 'QRIS');
    }

    public function test_only_billing_permission_can_open_settings(): void
    {
        $warehouse = User::factory()->staff(StaffRole::Warehouse)->create();

        $this->actingAs($warehouse)->get(route('admin.billing.edit'))->assertForbidden();
    }

    public function test_finance_report_and_tax_exports(): void
    {
        config(['shop.billing.tax_enabled' => true, 'shop.billing.tax_rate' => 11]);
        [$order, $customer] = $this->checkout(extra: ['company_invoice' => '1', 'billing_name' => 'CV Laporan']);
        $this->actingAs($customer)->post(route('account.orders.simulate-payment', $order));
        $order->refresh();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.reports.index', ['tab' => 'keuangan']))
            ->assertOk()->assertSee('Rincian keuangan')->assertSee('Dana bersih diterima')->assertSee('PPN dipungut');

        $csv = $this->get(route('admin.reports.export', 'pajak'))->assertOk()->streamedContent();
        $this->assertStringContainsString($order->invoice_number, $csv);
        $this->assertStringContainsString('CV Laporan', $csv);
        $this->assertStringContainsString((string) $order->tax_amount, $csv);

        $this->get(route('admin.reports.export', 'metode'))->assertOk();
    }

    public function test_product_page_shows_tax_note(): void
    {
        config(['shop.billing.tax_enabled' => true, 'shop.billing.tax_rate' => 11]);
        $product = Product::factory()->create();

        $this->get(route('products.show', $product))->assertOk()->assertSee('Harga belum termasuk PPN 11%');
    }

    public function test_payment_charge_defaults_to_order_total(): void
    {
        $charge = new PaymentCharge(amount: 10_000);

        $this->assertNull($charge->method);
        $this->assertSame(0, $charge->fee);
    }

    /** @return array{0: Order, 1: User} */
    private function checkout(array $extra = []): array
    {
        $product = Product::factory()->create(['price' => 100_000, 'stock' => 5]);
        $customer = User::factory()->create();
        $address = Address::factory()->for($customer)->create();

        $this->actingAs($customer)->post(route('cart.store', $product), ['quantity' => 1]);
        $this->post(route('checkout.store'), ['address_id' => $address->id, 'shipping' => 'jne|REG'] + $extra)
            ->assertSessionHasNoErrors()->assertRedirect();

        return [$customer->orders()->latest('id')->firstOrFail(), $customer];
    }

    /** Isian lengkap form Pajak & Biaya dengan nilai bawaan + perubahan. */
    private function form(array $overrides = [], array $methods = []): array
    {
        $base = [
            'tax_enabled' => '0', 'tax_label' => 'PPN', 'tax_rate' => '11', 'tax_dpp' => 'full', 'tax_mode' => 'exclusive',
            'tax_shipping' => '0', 'tax_price_note' => '1', 'service_fee' => '0', 'service_fee_label' => 'Biaya layanan',
            'payment_fee_label' => 'Biaya transaksi', 'payment_fee_gross_up' => '0', 'payment_fee_vat' => '0',
            'rounding_mode' => 'none', 'rounding_unit' => '100',
            'invoice_prefix' => 'INV', 'invoice_format' => '{PREFIX}/{YYYY}/{MM}/{SEQ}', 'invoice_padding' => '5',
            'invoice_reset' => 'yearly', 'invoice_issue_on' => 'paid', 'is_pkp' => '0', 'buyer_tax_info' => '1',
        ];

        $rows = [];
        foreach (PaymentMethod::cases() as $method) {
            $rate = $method->defaultRate();
            $rows[$method->value] = array_replace(
                ['enabled' => '1', 'percent' => (string) $rate['percent'], 'flat' => (string) $rate['flat'], 'charge_to' => 'store'],
                $methods[$method->value] ?? [],
            );
        }

        return array_replace($base, $overrides, ['methods' => $rows]);
    }
}
