<?php

namespace App\Services\Warehouse;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Kirim penjualan manual yang sudah lunas ke warehouse29 sebagai Outbound
 * Manual (status pending → disetujui admin gudang → stok gudang terpotong),
 * lalu pantau statusnya. ref_no = kode pesanan, jadi kirim ulang aman
 * (409 DUPLICATE_REF_NO = sudah tercatat).
 */
class WarehouseOutbound
{
    public function __construct(private readonly WarehouseClient $client) {}

    public function enabled(): bool
    {
        return (bool) config('services.warehouse.outbound_enabled') && $this->client->isConfigured();
    }

    /** Pesanan ini perlu dikirim ke gudang (manual, lunas, belum tercatat di gudang). */
    public function shouldSend(Order $order): bool
    {
        return $order->isManual()
            && $order->paid_at !== null
            && ! in_array($order->warehouse_outbound_status, [Order::OUTBOUND_PENDING, Order::OUTBOUND_APPROVED], true);
    }

    /** Tandai antre (dipanggil sebelum job dikirim, supaya terlihat di admin). */
    public function markQueued(Order $order): void
    {
        $order->forceFill(['warehouse_outbound_status' => Order::OUTBOUND_QUEUED, 'warehouse_outbound_error' => null])->saveQuietly();
    }

    /**
     * Kirim ke gudang. True = tercatat di gudang. Error data (SKU belum
     * terhubung, 422) langsung ditandai gagal; error koneksi/5xx dilempar
     * supaya job mencoba lagi.
     *
     * @throws WarehouseApiException
     */
    public function send(Order $order): bool
    {
        $order->loadMissing('items.product', 'items.variant');

        [$items, $unmapped] = $this->items($order);

        if ($unmapped) {
            return $this->fail($order, 'SKU gudang belum terhubung: '.implode(', ', $unmapped).'. Hubungkan di Stok › Sinkron gudang lalu kirim ulang.');
        }

        if ($items === []) {
            return $this->fail($order, 'Tidak ada barang untuk dikirim ke gudang.');
        }

        $response = $this->client->createOutbound([
            'ref_no' => $order->code,
            'transacted_at' => ($order->paid_at ?? now())->copy()->timezone('Asia/Jakarta')->toIso8601String(),
            'note' => $this->note($order),
            'items' => $items,
        ]);

        if ($response->status() === 201 || $response->json('error.code') === 'DUPLICATE_REF_NO') {
            $this->record($order, (array) $response->json('data'));

            return true;
        }

        if ($response->status() === 422) {
            $fields = collect((array) $response->json('error.fields'))->flatten()->unique()->take(3)->implode(' ');

            return $this->fail($order, 'Data ditolak gudang: '.Str::limit($fields ?: (string) $response->json('error.message'), 400));
        }

        if (in_array($response->status(), [401, 403], true)) {
            return $this->fail($order, $this->client->describe($response));
        }

        // 409 CONFLICT, 429, 5xx: coba lagi nanti.
        throw new WarehouseApiException($this->client->describe($response));
    }

    /** Perbarui status dari gudang. Mengembalikan status terbaru. */
    public function refresh(Order $order): ?string
    {
        $response = $this->client->outbound($order->warehouse_outbound_code ?: $order->code);

        if ($response->status() === 404) {
            if (in_array($order->warehouse_outbound_status, [Order::OUTBOUND_PENDING, Order::OUTBOUND_APPROVED], true)) {
                $order->forceFill([
                    'warehouse_outbound_status' => Order::OUTBOUND_DELETED,
                    'warehouse_outbound_error' => 'Transaksi tidak ditemukan di gudang (kemungkinan dihapus admin gudang). Kirim ulang bila perlu.',
                ])->saveQuietly();
            }

            return $order->warehouse_outbound_status;
        }

        if (! $response->successful()) {
            throw new WarehouseApiException($this->client->describe($response));
        }

        $this->record($order, (array) $response->json('data'));

        return $order->warehouse_outbound_status;
    }

    /**
     * Baris outbound: SKU gudang (warehouse_sku, cadangan SKU toko), qty
     * digabung per SKU karena satu SKU hanya boleh muncul sekali.
     *
     * @return array{0: list<array{sku: string, qty: int}>, 1: list<string>}
     */
    public function items(Order $order): array
    {
        $qty = [];
        $unmapped = [];

        foreach ($order->items as $item) {
            $sku = $this->skuFor($item);

            if ($sku === null) {
                $unmapped[] = $item->product_name.($item->variant_name ? " ({$item->variant_name})" : '');

                continue;
            }

            $key = Str::upper($sku);
            $qty[$key] ??= ['sku' => $sku, 'qty' => 0];
            $qty[$key]['qty'] += (int) $item->quantity;
        }

        return [array_values($qty), $unmapped];
    }

    private function skuFor(OrderItem $item): ?string
    {
        $variant = $item->variant;
        $product = $item->product;

        $sku = $variant?->warehouse_sku ?: $variant?->sku
            ?: (! $item->product_variant_id ? ($product?->warehouse_sku ?: $product?->sku) : null)
            ?: $item->sku;

        return filled($sku) ? trim($sku) : null;
    }

    private function note(Order $order): string
    {
        return Str::limit(collect([
            'Penjualan manual '.config('shop.name').' '.$order->code,
            $order->recipient_name.' '.$order->recipient_phone,
            $order->courierLabel() ?? ($order->shipping_address === 'Ambil di toko' ? 'Ambil di toko' : null),
            $order->notes,
        ])->filter()->implode(' · '), 990);
    }

    private function record(Order $order, array $data): void
    {
        $status = ($data['status'] ?? null) === 'approved' ? Order::OUTBOUND_APPROVED : Order::OUTBOUND_PENDING;

        $order->forceFill([
            'warehouse_outbound_status' => $status,
            'warehouse_outbound_code' => isset($data['code']) ? Str::limit((string) $data['code'], 60, '') : $order->warehouse_outbound_code,
            'warehouse_outbound_error' => null,
            'warehouse_outbound_sent_at' => $order->warehouse_outbound_sent_at ?? now(),
            'warehouse_outbound_approved_at' => $status === Order::OUTBOUND_APPROVED
                ? ($this->time($data['approved_at'] ?? null) ?? now())
                : null,
        ])->saveQuietly();
    }

    private function fail(Order $order, string $message): bool
    {
        Log::warning('Outbound gudang gagal', ['order' => $order->code, 'error' => $message]);

        $order->forceFill([
            'warehouse_outbound_status' => Order::OUTBOUND_FAILED,
            'warehouse_outbound_error' => Str::limit($message, 500),
        ])->saveQuietly();

        return false;
    }

    private function time(mixed $value): ?Carbon
    {
        try {
            return is_string($value) && $value !== '' ? Carbon::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
