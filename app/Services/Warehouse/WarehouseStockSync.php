<?php

namespace App\Services\Warehouse;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WarehouseStockItem;
use App\Models\WarehouseSyncRun;
use App\Services\Catalog\StockService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Tarik stok dari warehouse29 lalu samakan stok toko.
 *
 * Stok toko = stok gudang − jumlah di pesanan website yang belum dikirim
 * (stoknya sudah dipotong toko saat checkout, tetapi gudang baru memotong saat
 * resinya diproses). Produk/varian dicocokkan lewat `warehouse_sku`; bila
 * kosong, lewat SKU toko yang sama persis dengan SKU gudang.
 */
class WarehouseStockSync
{
    public const LOCK = 'warehouse-stock-sync';

    /** Pengaman: 400 halaman × 500 baris = 200 ribu SKU. */
    private const MAX_PAGES = 400;

    public function __construct(
        private readonly WarehouseClient $client,
        private readonly StockService $stock,
    ) {}

    /**
     * @param  bool  $full  true = tarik semua SKU; false = hanya yang berubah sejak tarikan terakhir yang berhasil.
     *
     * @throws WarehouseApiException bila sinkron lain sedang berjalan
     */
    public function run(bool $full = false, ?User $user = null): WarehouseSyncRun
    {
        $lock = Cache::lock(self::LOCK, 900);

        if (! $lock->get()) {
            throw new WarehouseApiException('Sinkron stok lain sedang berjalan. Tunggu sebentar lalu muat ulang halaman.');
        }

        try {
            $since = $full ? null : $this->lastCursor();

            $run = WarehouseSyncRun::create([
                'mode' => $since ? WarehouseSyncRun::MODE_INCREMENTAL : WarehouseSyncRun::MODE_FULL,
                'status' => WarehouseSyncRun::STATUS_RUNNING,
                'user_id' => $user?->id,
                'started_at' => now(),
            ]);

            try {
                [$rows, $serverTime] = $this->fetch($since);
                $skus = $this->store($rows);
                $updated = $this->apply($since ? $skus : null);

                $run->update([
                    'status' => WarehouseSyncRun::STATUS_SUCCESS,
                    'fetched' => count($rows),
                    'updated' => $updated,
                    'unmatched' => WarehouseStockItem::unlinked()->where('status', 'active')->count(),
                    'server_time' => $serverTime,
                    'finished_at' => now(),
                ]);
            } catch (Throwable $e) {
                if (! $e instanceof WarehouseApiException) {
                    report($e);
                }

                $run->update([
                    'status' => WarehouseSyncRun::STATUS_FAILED,
                    'message' => $e instanceof WarehouseApiException ? $e->getMessage() : 'Terjadi kesalahan saat sinkron: '.class_basename($e).'. Detail ada di log server.',
                    'finished_at' => now(),
                ]);
            }

            return $run->refresh();
        } finally {
            $lock->release();
        }
    }

    /**
     * Hitung ulang stok toko dari salinan stok gudang terakhir (tanpa memanggil API).
     * Dipakai saat SKU baru dihubungkan.
     *
     * @param  list<string>|null  $skus  null = semua SKU
     * @return int jumlah produk/varian yang stoknya berubah
     */
    public function apply(?array $skus = null): int
    {
        $items = collect();
        $query = WarehouseStockItem::query();

        if ($skus === null) {
            $items = $query->get();
        } else {
            foreach (array_chunk($skus, 500) as $chunk) {
                $items = $items->merge((clone $query)->whereIn('sku', $chunk)->get());
            }
        }

        if ($items->isEmpty()) {
            return 0;
        }

        $items = $items->keyBy(fn (WarehouseStockItem $item) => $this->key($item->sku));
        $reserved = $this->reservedQuantities();
        $updated = 0;

        foreach ($this->targets($items->pluck('sku')->all()) as $key => $targets) {
            $item = $items->get($key);

            foreach ($item ? $targets : [] as [$product, $variant]) {
                $held = $reserved[$variant ? 'v'.$variant->id : 'p'.$product->id] ?? 0;
                $stock = max(0, $item->sellableQty() - $held);

                if ($stock === (int) ($variant ?? $product)->stock) {
                    continue;
                }

                $note = $item->status === 'active'
                    ? 'Stok gudang '.$this->number($item->qty).($held ? ", dipesan di website (belum dikirim) {$held}" : '')
                    : 'Barang '.mb_strtolower($item->statusLabel());

                if ($this->stock->set($product, $variant, $stock, StockMovementType::WarehouseSync, "{$note} [{$item->sku}]")) {
                    $updated++;
                }
            }
        }

        return $updated;
    }

    /**
     * Produk tanpa varian & varian yang terhubung ke SKU gudang, dikelompokkan per SKU (huruf kecil).
     * SKU yang dihubungkan manual (`warehouse_sku`) menang atas SKU toko yang kebetulan sama.
     *
     * @param  list<string>  $skus  SKU gudang
     * @return array<string, list<array{0: Product, 1: ?ProductVariant}>>
     */
    public function targets(array $skus): array
    {
        $explicit = [];
        $fallback = [];

        foreach (array_chunk(array_values(array_unique($skus)), 500) as $chunk) {
            Product::without('coverImage')->where('has_variants', false)
                ->where(fn ($q) => $q->whereIn('warehouse_sku', $chunk)->orWhere(fn ($q) => $q->whereNull('warehouse_sku')->whereIn('sku', $chunk)))
                ->get()
                ->each(function (Product $product) use (&$explicit, &$fallback) {
                    $product->warehouse_sku
                        ? $explicit[$this->key($product->warehouse_sku)][] = [$product, null]
                        : $fallback[$this->key($product->sku)][] = [$product, null];
                });

            ProductVariant::with(['product' => fn ($q) => $q->without('coverImage')])
                ->where(fn ($q) => $q->whereIn('warehouse_sku', $chunk)->orWhere(fn ($q) => $q->whereNull('warehouse_sku')->whereIn('sku', $chunk)))
                ->get()
                ->each(function (ProductVariant $variant) use (&$explicit, &$fallback) {
                    if (! $variant->product?->has_variants) {
                        return;
                    }

                    $variant->warehouse_sku
                        ? $explicit[$this->key($variant->warehouse_sku)][] = [$variant->product, $variant]
                        : $fallback[$this->key($variant->sku)][] = [$variant->product, $variant];
                });
        }

        return array_merge($fallback, $explicit);
    }

    /**
     * Jumlah per produk/varian di pesanan website yang belum dikirim.
     *
     * @return array<string, int> "p{product_id}" / "v{variant_id}" => qty
     */
    public function reservedQuantities(): array
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', array_map(fn (OrderStatus $s) => $s->value, OrderStatus::awaitingShipmentStatuses()))
            ->whereNotNull('order_items.product_id')
            ->groupBy('order_items.product_id', 'order_items.product_variant_id')
            ->get([
                'order_items.product_id',
                'order_items.product_variant_id',
                DB::raw('SUM(order_items.quantity) as qty'),
            ])
            ->mapWithKeys(fn ($row) => [
                ($row->product_variant_id ? 'v'.$row->product_variant_id : 'p'.$row->product_id) => (int) $row->qty,
            ])
            ->all();
    }

    /** Waktu server gudang dari tarikan terakhir yang berhasil (dipakai sebagai updated_since). */
    public function lastCursor(): ?string
    {
        return WarehouseSyncRun::where('status', WarehouseSyncRun::STATUS_SUCCESS)
            ->whereNotNull('server_time')
            ->latest('id')
            ->value('server_time');
    }

    /** @return array{0: list<array<string, mixed>>, 1: ?string} baris stok & server_time halaman pertama */
    private function fetch(?string $since): array
    {
        $rows = [];
        $until = null;
        $page = 1;

        do {
            $response = $this->client->stocks([
                'updated_since' => $since,
                // Halaman 2 dst. memakai server_time halaman pertama agar urutannya tidak bergeser.
                'updated_until' => $until,
                'page' => $page,
                'per_page' => WarehouseClient::MAX_PER_PAGE,
            ]);

            $until ??= is_string($response['meta']['server_time'] ?? null) ? $response['meta']['server_time'] : null;
            array_push($rows, ...$response['data']);
            $totalPages = (int) ($response['meta']['total_pages'] ?? 1);
            $page++;
        } while ($page <= $totalPages && $page <= self::MAX_PAGES && $until !== null);

        return [$rows, $until];
    }

    /**
     * Simpan salinan stok gudang.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<string> SKU yang diterima
     */
    private function store(array $rows): array
    {
        $records = [];
        $now = now();

        foreach ($rows as $row) {
            $sku = is_scalar($row['sku'] ?? null) ? trim((string) $row['sku']) : '';

            if ($sku === '' || mb_strlen($sku) > 100) {
                continue;
            }

            $records[$this->key($sku)] = [
                'sku' => $sku,
                'name' => $this->text($row['name'] ?? null),
                'category' => $this->text($row['category'] ?? null),
                'uom' => $this->text($row['uom'] ?? null, 30),
                'qty' => is_numeric($row['qty'] ?? null) ? round((float) $row['qty'], 2) : 0,
                'status' => in_array($row['status'] ?? null, ['active', 'inactive', 'deleted'], true) ? $row['status'] : 'active',
                'warehouse_updated_at' => $this->time($row['updated_at'] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk(array_values($records), 500) as $chunk) {
            WarehouseStockItem::upsert($chunk, ['sku'], ['name', 'category', 'uom', 'qty', 'status', 'warehouse_updated_at', 'updated_at']);
        }

        return array_column($records, 'sku');
    }

    private function key(?string $sku): string
    {
        return mb_strtolower(trim((string) $sku));
    }

    private function text(mixed $value, int $max = 255): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? mb_substr(trim((string) $value), 0, $max) : null;
    }

    private function time(mixed $value): ?Carbon
    {
        try {
            return is_string($value) && $value !== '' ? Carbon::parse($value)->setTimezone(config('app.timezone')) : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function number(float $qty): string
    {
        return rtrim(rtrim(number_format($qty, 2, ',', '.'), '0'), ',');
    }
}
