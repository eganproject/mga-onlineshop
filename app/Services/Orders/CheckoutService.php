<?php

namespace App\Services\Orders;

use App\Contracts\PaymentGateway;
use App\Data\ShippingRate;
use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Catalog\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CheckoutService
{
    public function __construct(
        private readonly CartService $cart,
        private readonly PaymentGateway $payments,
        private readonly StockService $stock,
    ) {}

    /**
     * Buat pesanan dari isi keranjang. Stok dikunci dan dikurangi dalam satu
     * transaksi agar tidak terjadi overselling.
     */
    public function placeOrder(User $user, Address $address, ShippingRate $rate, ?string $notes = null): Order
    {
        $lines = $this->cart->lines();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Keranjang belanja masih kosong.']);
        }

        $order = DB::transaction(function () use ($user, $address, $rate, $notes, $lines) {
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

            $order = $user->orders()->create([
                'code' => Order::generateCode(),
                'status' => OrderStatus::PendingPayment,
                'subtotal' => $subtotal,
                'shipping_cost' => $rate->cost,
                'total' => $subtotal + $rate->cost,
                'total_weight' => $weight,
                'recipient_name' => $address->recipient_name,
                'recipient_phone' => $address->phone,
                'shipping_address' => $address->fullAddress(),
                'destination_id' => $address->district_id,
                'courier' => $rate->courier,
                'courier_service' => $rate->service,
                'shipping_etd' => $rate->etd,
                'notes' => $notes,
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

        try {
            $this->payments->createPayment($order);
        } catch (Throwable $e) {
            // Pesanan tetap tersimpan; pembayaran bisa dibuat ulang nanti.
            report($e);
        }

        return $order;
    }
}
