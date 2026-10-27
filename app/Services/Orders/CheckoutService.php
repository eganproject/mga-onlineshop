<?php

namespace App\Services\Orders;

use App\Data\ShippingRate;
use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Exceptions\VoucherException;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Billing\PriceCalculator;
use App\Services\Cart\CartService;
use App\Services\Catalog\StockService;
use App\Services\Payment\PaymentService;
use App\Services\Promo\VoucherService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CheckoutService
{
    public function __construct(
        private readonly CartService $cart,
        private readonly PaymentService $payments,
        private readonly StockService $stock,
        private readonly VoucherService $vouchers,
        private readonly PriceCalculator $prices,
    ) {}

    /**
     * Buat pesanan dari isi keranjang. Stok dikunci dan dikurangi dalam satu
     * transaksi agar tidak terjadi overselling. Voucher (bila ada) diperiksa
     * ulang di dalam transaksi yang sama dengan baris voucher dikunci, jadi
     * kuota tidak bisa terlampaui oleh checkout bersamaan.
     *
     * @param  array{billing_name?: ?string, billing_npwp?: ?string, billing_address?: ?string, billing_email?: ?string}  $billing
     */
    public function placeOrder(User $user, Address $address, ShippingRate $rate, ?string $notes = null, ?string $voucherCode = null, array $billing = []): Order
    {
        $lines = $this->cart->lines();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Keranjang belanja masih kosong.']);
        }

        $order = DB::transaction(function () use ($user, $address, $rate, $notes, $lines, $voucherCode, $billing) {
            // Kunci baris produk & varian agar dua pembeli tidak mengambil stok yang sama.
            $products = Product::whereKey($lines->pluck('product.id')->unique())->lockForUpdate()->get()->keyBy('id');
            $variants = ProductVariant::whereKey($lines->pluck('variant.id')->filter())->lockForUpdate()->get()->keyBy('id');

            $subtotal = 0;
            $weight = 0;
            $items = [];

            foreach ($lines as $line) {
                $product = $products->get($line->product->id);
                $variant = $line->variant ? $variants->get($line->variant->id) : null;
                $available = $variant ? ($variant->is_active ? $variant->stock : 0) : $product?->stock;

                if (! $product || ! $product->is_active || ($line->variant && ! $variant) || $available < $line->quantity) {
                    $name = $line->variant ? "{$line->product->name} ({$line->variant->name})" : $line->product->name;

                    throw ValidationException::withMessages([
                        'cart' => "Stok {$name} tidak mencukupi. Silakan perbarui keranjang.",
                    ]);
                }

                $price = $variant?->price ?? $product->price;
                $unitWeight = $variant?->weight ?: $product->weight;
                $lineSubtotal = $price * $line->quantity;
                $subtotal += $lineSubtotal;
                $weight += $unitWeight * $line->quantity;

                $items[] = [
                    'product' => $product,
                    'variant' => $variant,
                    'quantity' => $line->quantity,
                    'attributes' => [
                        'product_id' => $product->id,
                        'product_variant_id' => $variant?->id,
                        'product_name' => $product->name,
                        'variant_name' => $variant?->name,
                        'sku' => $variant?->sku ?: $product->sku,
                        'price' => $price,
                        'quantity' => $line->quantity,
                        'weight' => $unitWeight,
                        'subtotal' => $lineSubtotal,
                    ],
                ];
            }

            [$voucher, $discounts] = $this->applyVoucher($voucherCode, $user, $subtotal, $rate->cost);

            // Biaya layanan, PPN & pembulatan sesuai Admin › Pajak & Biaya (disalin ke pesanan).
            $charges = $this->prices->calculate($subtotal, $discounts['discount'], $rate->cost, $discounts['shipping_discount']);

            $order = $user->orders()->create([
                'code' => Order::generateCode(),
                'status' => OrderStatus::PendingPayment,
                'subtotal' => $subtotal,
                'shipping_cost' => $rate->cost,
                'discount' => $discounts['discount'],
                'shipping_discount' => $discounts['shipping_discount'],
                ...$charges,
                ...array_map(fn ($v) => filled($v) ? trim((string) $v) : null, Arr::only($billing, ['billing_name', 'billing_npwp', 'billing_address', 'billing_email'])),
                'voucher_id' => $voucher?->id,
                'voucher_code' => $voucher?->code,
                'total_weight' => $weight,
                'recipient_name' => $address->recipient_name,
                'recipient_phone' => $address->phone,
                'shipping_address' => $address->fullAddress(),
                'destination_id' => $address->district_id,
                'courier' => $rate->courier,
                'courier_service' => $rate->service,
                'shipping_etd' => $rate->etd,
                'notes' => $notes,
                'payment_due_at' => now()->addHours((int) config('shop.payment.expiry_hours')),
            ]);

            $order->items()->createMany(array_column($items, 'attributes'));

            foreach ($items as $item) {
                $this->stock->adjust(
                    $item['product'], $item['variant'], -$item['quantity'],
                    StockMovementType::Sale, order: $order,
                );
            }

            return $order;
        });

        $this->cart->clear();
        $this->vouchers->forget();

        try {
            // Bila pembeli harus memilih metode (ada biaya transaksi), tagihan dibuat di halaman bayar.
            if (! $this->payments->requiresMethodChoice()) {
                $this->payments->createFor($order);
            }
        } catch (Throwable $e) {
            // Pesanan tetap tersimpan; tagihan dibuat ulang saat halaman bayar dibuka.
            report($e);
        }

        return $order;
    }

    /** @return array{0: ?Voucher, 1: array{discount: int, shipping_discount: int}} */
    private function applyVoucher(?string $code, User $user, int $subtotal, int $shipping): array
    {
        if (Voucher::normalizeCode($code) === '') {
            return [null, ['discount' => 0, 'shipping_discount' => 0]];
        }

        $voucher = Voucher::where('code', Voucher::normalizeCode($code))->lockForUpdate()->first();

        try {
            if (! $voucher) {
                throw new VoucherException('Kode voucher tidak ditemukan.');
            }

            $this->vouchers->check($voucher, $user, $subtotal);
        } catch (VoucherException $e) {
            throw ValidationException::withMessages(['voucher' => $e->getMessage()]);
        }

        return [$voucher, $this->vouchers->calculate($voucher, $subtotal, $shipping)];
    }
}
