<?php

namespace App\Services\Catalog;

use App\Enums\OrderStatus;
use Illuminate\Support\Facades\DB;

/**
 * Menghitung ulang `products.sold_count` (pcs terjual) dari item pesanan yang
 * statusnya termasuk pendapatan (sudah dibayar, tidak dibatalkan). Dipanggil
 * otomatis saat status pesanan berubah (AppServiceProvider).
 */
class SoldCountService
{
    /** @param  iterable<int>|null  $productIds  null = semua produk */
    public function sync(?iterable $productIds = null): void
    {
        $ids = $productIds === null ? null : collect($productIds)->filter()->unique()->values()->all();
        if ($ids === []) {
            return;
        }

        $sold = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', array_map(fn (OrderStatus $s) => $s->value, OrderStatus::revenueStatuses()))
            ->whereNotNull('order_items.product_id')
            ->when($ids, fn ($q) => $q->whereIn('order_items.product_id', $ids))
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id, SUM(order_items.quantity) as sold')
            ->pluck('sold', 'product_id');

        // Produk yang tidak punya penjualan lagi (mis. pesanannya dibatalkan) kembali ke 0.
        DB::table('products')
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))
            ->whereNotIn('id', $sold->keys()->all() ?: [0])
            ->where('sold_count', '!=', 0)
            ->update(['sold_count' => 0]);

        foreach ($sold as $productId => $qty) {
            DB::table('products')->where('id', $productId)->update(['sold_count' => (int) $qty]);
        }
    }
}
