<?php

namespace App\Services\Orders;

use App\Contracts\PaymentGateway;
use App\Data\ShippingRate;
use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Cart\CartService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CheckoutService
{
    public function __construct(
        private readonly CartService $cart,
        private readonly PaymentGateway $payments,
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
            $products = Product::whereKey($lines->pluck('product.id'))->lockForUpdate()->get()->keyBy('id');

            $subtotal = 0;
            $weight = 0;
            $items = [];

            foreach ($lines as $line) {
                $product = $products->get($line->product->id);

                if (! $product || ! $product->is_active || $product->stock < $line->quantity) {
                    throw ValidationException::withMessages([
                        'cart' => "Stok {$line->product->name} tidak mencukupi. Silakan perbarui keranjang.",
                    ]);
                }

                $product->decrement('stock', $line->quantity);

                $lineSubtotal = $product->price * $line->quantity;
                $subtotal += $lineSubtotal;
                $weight += $product->weight * $line->quantity;

                $items[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'price' => $product->price,
                    'quantity' => $line->quantity,
                    'weight' => $product->weight,
                    'subtotal' => $lineSubtotal,
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
                'destination_id' => $address->destination_id,
                'courier' => $rate->courier,
                'courier_service' => $rate->service,
                'shipping_etd' => $rate->etd,
                'notes' => $notes,
            ]);

            $order->items()->createMany($items);

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
