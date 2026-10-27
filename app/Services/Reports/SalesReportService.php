<?php

namespace App\Services\Reports;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Category;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Angka penjualan untuk dashboard & laporan admin.
 *
 * Penjualan dihitung dari pesanan yang sudah dibayar dan tidak dibatalkan,
 * berdasarkan tanggal bayar (`paid_at`). Pendapatan kotor = total yang dibayar
 * pelanggan (termasuk ongkir, setelah potongan voucher); penjualan produk =
 * subtotal barang sebelum voucher; diskon = potongan voucher barang + ongkir.
 */
class SalesReportService
{
    /** Status pesanan yang dihitung sebagai penjualan. */
    public static function revenueStatuses(): array
    {
        return OrderStatus::revenueStatuses();
    }

    /** Query pesanan terjual dalam periode. */
    public function paidOrders(ReportPeriod $period): Builder
    {
        return Order::query()
            ->whereIn('orders.status', self::revenueStatuses())
            ->whereBetween('orders.paid_at', [$period->start(), $period->end()]);
    }

    /**
     * Ringkasan angka utama periode.
     *
     * Rincian keuangan: tax = PPN dipungut, service_fee = biaya layanan,
     * payment_fee = biaya transaksi yang dibayar pembeli, gateway_fee = perkiraan
     * potongan Midtrans, net = dana bersih (dibayar pembeli − potongan Midtrans).
     *
     * @return array{revenue:int, product_sales:int, shipping:int, discount:int, orders:int, items:int, average:int, customers:int, cancelled:int, tax:int, tax_included:int, service_fee:int, rounding:int, payment_fee:int, gateway_fee:int, paid:int, net:int, net_after_tax:int}
     */
    public function summary(ReportPeriod $period): array
    {
        $totals = $this->paidOrders($period)
            ->toBase()
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(total), 0) as revenue, COALESCE(SUM(subtotal), 0) as product_sales, COALESCE(SUM(shipping_cost), 0) as shipping, COALESCE(SUM(discount + shipping_discount), 0) as discount, COUNT(DISTINCT user_id) as customers')
            ->selectRaw('COALESCE(SUM(tax_amount), 0) as tax, COALESCE(SUM(CASE WHEN tax_inclusive = 1 THEN tax_amount ELSE 0 END), 0) as tax_included, COALESCE(SUM(service_fee), 0) as service_fee, COALESCE(SUM(rounding), 0) as rounding, COALESCE(SUM(payment_fee), 0) as payment_fee, COALESCE(SUM(gateway_fee), 0) as gateway_fee')
            ->first();

        $items = (int) DB::table('order_items')
            ->whereIn('order_id', $this->paidOrders($period)->select('orders.id'))
            ->sum('quantity');

        $orders = (int) $totals->orders;
        $revenue = (int) $totals->revenue;

        return [
            'revenue' => $revenue,
            'product_sales' => (int) $totals->product_sales,
            'shipping' => (int) $totals->shipping,
            'discount' => (int) $totals->discount,
            'orders' => $orders,
            'items' => $items,
            'average' => $orders > 0 ? intdiv($revenue, $orders) : 0,
            'customers' => (int) $totals->customers,
            'cancelled' => Order::where('status', OrderStatus::Cancelled)
                ->whereBetween('cancelled_at', [$period->start(), $period->end()])
                ->count(),
            'tax' => (int) $totals->tax,
            'tax_included' => (int) $totals->tax_included,
            'service_fee' => (int) $totals->service_fee,
            'rounding' => (int) $totals->rounding,
            'payment_fee' => (int) $totals->payment_fee,
            'gateway_fee' => (int) $totals->gateway_fee,
            'paid' => $revenue + (int) $totals->payment_fee,
            'net' => $revenue + (int) $totals->payment_fee - (int) $totals->gateway_fee,
            'net_after_tax' => $revenue + (int) $totals->payment_fee - (int) $totals->gateway_fee - (int) $totals->tax,
        ];
    }

    /**
     * Penjualan per metode bayar: dibayar pembeli, biaya transaksi dari pembeli,
     * perkiraan potongan Midtrans, dan dana bersih.
     *
     * @return Collection<int, object{method:?string, label:string, orders:int, paid:int, payment_fee:int, gateway_fee:int, net:int, share:float}>
     */
    public function byPaymentMethod(ReportPeriod $period): Collection
    {
        $rows = $this->paidOrders($period)
            ->toBase()
            ->groupBy('payment_method')
            ->selectRaw('payment_method, COUNT(*) as orders, SUM(total + payment_fee) as paid, SUM(payment_fee) as payment_fee, SUM(gateway_fee) as gateway_fee')
            ->orderByDesc('paid')
            ->get();

        $total = (int) $rows->sum('paid');

        return $rows->map(fn ($row) => (object) [
            'method' => $row->payment_method,
            'label' => PaymentMethod::labelFor($row->payment_method) ?? 'Tidak tercatat (pesanan lama)',
            'orders' => (int) $row->orders,
            'paid' => (int) $row->paid,
            'payment_fee' => (int) $row->payment_fee,
            'gateway_fee' => (int) $row->gateway_fee,
            'net' => (int) $row->paid - (int) $row->gateway_fee,
            'share' => $total > 0 ? round($row->paid / $total * 100, 1) : 0.0,
        ]);
    }

    /** Pesanan terjual yang dikenai PPN (daftar untuk rekap pajak keluaran). */
    public function taxedOrders(ReportPeriod $period): Builder
    {
        return $this->paidOrders($period)
            ->where('tax_amount', '>', 0)
            ->with('user:id,name,email')
            ->orderBy('paid_at')
            ->orderBy('orders.id');
    }

    /**
     * Persentase perubahan tiap angka ringkasan dibanding periode sebelumnya.
     * Null bila periode sebelumnya nol (tidak bisa dibandingkan).
     */
    public function compare(array $current, array $previous): array
    {
        return collect($current)->map(function (int $value, string $key) use ($previous) {
            $before = $previous[$key] ?? 0;

            return $before > 0 ? round(($value - $before) / $before * 100, 1) : null;
        })->all();
    }

    /**
     * Penjualan per bucket waktu (hari/minggu/bulan), termasuk bucket kosong.
     *
     * @return Collection<int, array{key:string, label:string, short:string, revenue:int, orders:int, items:int}>
     */
    public function timeseries(ReportPeriod $period): Collection
    {
        $daily = $this->paidOrders($period)
            ->toBase()
            ->selectRaw('DATE(paid_at) as day, COUNT(*) as orders, SUM(total) as revenue')
            ->groupBy('day')
            ->get();

        $itemsDaily = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', self::revenueStatuses())
            ->whereBetween('orders.paid_at', [$period->start(), $period->end()])
            ->selectRaw('DATE(orders.paid_at) as day, SUM(order_items.quantity) as items')
            ->groupBy('day')
            ->pluck('items', 'day');

        $buckets = collect($period->buckets())->map(fn (array $b, string $key) => [
            'key' => $key,
            'label' => $b['label'],
            'short' => $b['short'],
            'revenue' => 0,
            'orders' => 0,
            'items' => 0,
        ]);

        foreach ($daily as $row) {
            $key = $period->bucketKey(CarbonImmutable::parse($row->day));

            if ($buckets->has($key)) {
                $bucket = $buckets->get($key);
                $bucket['revenue'] += (int) $row->revenue;
                $bucket['orders'] += (int) $row->orders;
                $bucket['items'] += (int) ($itemsDaily[$row->day] ?? 0);
                $buckets->put($key, $bucket);
            }
        }

        return $buckets->values();
    }

    /**
     * Penjualan per produk, urut dari omzet terbesar. Produk yang sudah dihapus
     * tetap muncul memakai nama yang tersimpan di pesanan.
     *
     * @return Collection<int, object{product_id:?int, name:string, sku:?string, quantity:int, revenue:int, orders:int, share:float}>
     */
    public function byProduct(ReportPeriod $period, ?int $limit = null): Collection
    {
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->whereIn('orders.status', self::revenueStatuses())
            ->whereBetween('orders.paid_at', [$period->start(), $period->end()])
            ->groupBy('order_items.product_id', DB::raw('CASE WHEN order_items.product_id IS NULL THEN order_items.product_name END'))
            ->selectRaw('order_items.product_id, COALESCE(MAX(products.name), MAX(order_items.product_name)) as name, MAX(products.slug) as slug, COALESCE(MAX(products.sku), MAX(order_items.sku)) as sku, SUM(order_items.quantity) as quantity, SUM(order_items.subtotal) as revenue, COUNT(DISTINCT order_items.order_id) as orders')
            ->orderByDesc('revenue')
            ->orderByDesc('quantity')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get();

        $total = $limit ? $this->productSalesTotal($period) : (int) $rows->sum('revenue');

        return $rows->map(fn (object $row) => (object) [
            'product_id' => $row->product_id ? (int) $row->product_id : null,
            'slug' => $row->slug,
            'name' => $row->name,
            'sku' => $row->sku,
            'quantity' => (int) $row->quantity,
            'revenue' => (int) $row->revenue,
            'orders' => (int) $row->orders,
            'share' => $total > 0 ? round($row->revenue / $total * 100, 1) : 0.0,
        ]);
    }

    /**
     * Penjualan per kategori (kategori produk saat ini). Subkategori ditampilkan
     * dengan nama induknya, mis. "Tempat Brosur › Brosur Tempel".
     *
     * @return Collection<int, object{category_id:?int, name:string, quantity:int, revenue:int, orders:int, products:int, share:float}>
     */
    public function byCategory(ReportPeriod $period): Collection
    {
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->whereIn('orders.status', self::revenueStatuses())
            ->whereBetween('orders.paid_at', [$period->start(), $period->end()])
            ->groupBy('products.category_id')
            ->selectRaw('products.category_id, SUM(order_items.quantity) as quantity, SUM(order_items.subtotal) as revenue, COUNT(DISTINCT order_items.order_id) as orders, COUNT(DISTINCT order_items.product_id) as products')
            ->orderByDesc('revenue')
            ->get();

        $categories = Category::with('parent')
            ->whereIn('id', $rows->pluck('category_id')->filter())
            ->get()
            ->keyBy('id');

        $total = (int) $rows->sum('revenue');

        return $rows->map(function (object $row) use ($categories, $total) {
            $category = $row->category_id ? $categories->get($row->category_id) : null;

            return (object) [
                'category_id' => $category?->id,
                'name' => $category
                    ? ($category->parent ? $category->parent->name.' › '.$category->name : $category->name)
                    : 'Produk dihapus',
                'quantity' => (int) $row->quantity,
                'revenue' => (int) $row->revenue,
                'orders' => (int) $row->orders,
                'products' => (int) $row->products,
                'share' => $total > 0 ? round($row->revenue / $total * 100, 1) : 0.0,
            ];
        });
    }

    /** Jumlah pesanan per status untuk pesanan yang dibuat dalam periode. */
    public function statusBreakdown(ReportPeriod $period): Collection
    {
        $counts = Order::query()
            ->whereBetween('created_at', [$period->start(), $period->end()])
            ->toBase()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(OrderStatus::cases())->mapWithKeys(fn (OrderStatus $status) => [
            $status->value => ['status' => $status, 'count' => (int) ($counts[$status->value] ?? 0)],
        ]);
    }

    /**
     * Pemakaian voucher pada pesanan terjual dalam periode.
     *
     * @return Collection<int, object{voucher_code:string, orders:int, discount:int, revenue:int}>
     */
    public function byVoucher(ReportPeriod $period, int $limit = 10): Collection
    {
        return $this->paidOrders($period)
            ->whereNotNull('voucher_code')
            ->toBase()
            ->groupBy('voucher_code')
            ->selectRaw('voucher_code, COUNT(*) as orders, SUM(discount + shipping_discount) as discount, SUM(total) as revenue')
            ->orderByDesc('orders')
            ->orderByDesc('discount')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (object) [
                'voucher_code' => $row->voucher_code,
                'orders' => (int) $row->orders,
                'discount' => (int) $row->discount,
                'revenue' => (int) $row->revenue,
            ]);
    }

    /** Pesanan terjual dalam periode, untuk ekspor detail. */
    public function orders(ReportPeriod $period): Builder
    {
        return $this->paidOrders($period)
            ->with('user:id,name,email')
            ->withSum('items as items_quantity', 'quantity')
            ->orderBy('paid_at')
            ->orderBy('orders.id');
    }

    private function productSalesTotal(ReportPeriod $period): int
    {
        return (int) $this->paidOrders($period)->sum('subtotal');
    }
}
