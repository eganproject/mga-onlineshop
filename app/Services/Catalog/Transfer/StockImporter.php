<?php

namespace App\Services\Catalog\Transfer;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ProductVariant;
use App\Services\Catalog\StockService;
use Illuminate\Support\Str;
use Throwable;

/**
 * Update stok massal dari file. Baris dicocokkan lewat SKU (varian atau produk),
 * atau slug + nama varian. Mode "set" = stock opname (stok jadi angka di file),
 * mode "add" = barang masuk/keluar (stok ditambah angka di file, boleh negatif).
 * Setiap perubahan lewat StockService sehingga tercatat di riwayat stok.
 */
class StockImporter extends Importer
{
    public const MODES = ['set' => 'Ganti stok (stock opname)', 'add' => 'Tambah / kurangi stok'];

    public const COLUMNS = ['sku', 'slug', 'nama_produk', 'nama_varian', 'stok', 'catatan'];

    public function __construct(private readonly StockService $stock) {}

    public static function aliases(): array
    {
        return [
            'sku_produk' => 'sku',
            'sku_varian' => 'sku',
            'kode' => 'sku',
            'varian' => 'nama_varian',
            'produk' => 'nama_produk',
            'nama' => 'nama_produk',
            'stock' => 'stok',
            'stok_baru' => 'stok',
            'jumlah' => 'stok',
            'keterangan' => 'catatan',
        ];
    }

    protected function requiredHeaders(): array
    {
        return [['stok'], ['sku', 'slug']];
    }

    protected function run(ProductImport $import, iterable $rows, bool $apply): array
    {
        $add = $import->mode === 'add';
        $rows = iterator_to_array($rows, true);
        $lookup = $this->lookup($rows);

        $errors = [];
        $plans = [];
        $seen = [];

        foreach ($rows as $line => $row) {
            try {
                [$product, $variant] = $this->target($row, $lookup);
                $quantity = CellValue::integer($row['stok'] ?? null, 'Stok', $add ? -1_000_000 : 0, 1_000_000, allowNegative: $add);
                $note = CellValue::string($row['catatan'] ?? null, 255, 'Catatan');
            } catch (InvalidCell $e) {
                $errors[] = self::error($line, $e->getMessage());

                continue;
            }

            if ($quantity === null) {
                $errors[] = self::error($line, 'Kolom stok wajib diisi.');

                continue;
            }

            $key = $variant ? "v{$variant->id}" : "p{$product->id}";
            if (isset($seen[$key])) {
                $errors[] = self::error($line, "Produk/varian ini sudah ada di baris {$seen[$key]}.");

                continue;
            }
            $seen[$key] = $line;

            $current = (int) ($variant ?? $product)->stock;
            $after = $add ? $current + $quantity : $quantity;

            if ($after < 0) {
                $errors[] = self::error($line, "Stok saat ini {$current}, tidak bisa dikurangi ".abs($quantity).'.');

                continue;
            }

            $plans[] = compact('line', 'product', 'variant', 'quantity', 'note', 'current', 'after');
        }

        $plans = collect($plans);
        $changed = $plans->filter(fn ($p) => $p['after'] !== $p['current']);

        return [
            'total' => count($rows),
            'error_rows' => count($errors),
            'errors' => $errors,
            'summary' => [
                'matched' => $plans->count(),
                'changed' => $changed->count(),
                'unchanged' => $plans->count() - $changed->count(),
                'units_in' => $changed->sum(fn ($p) => max(0, $p['after'] - $p['current'])),
                'units_out' => $changed->sum(fn ($p) => max(0, $p['current'] - $p['after'])),
            ],
            'preview' => $changed->take(self::MAX_PREVIEW)->map(fn ($p) => [
                'row' => $p['line'],
                'name' => $p['product']->name,
                'variant' => $p['variant']?->name,
                'sku' => $p['variant']?->sku ?? $p['product']->sku,
                'current' => $p['current'],
                'after' => $p['after'],
            ])->values()->all(),
            'result' => $apply ? $this->apply($import, $changed) : null,
        ];
    }

    private function apply(ProductImport $import, $plans): array
    {
        $result = ['changed' => 0, 'failed' => []];
        $default = 'Update stok massal: '.Str::limit($import->filename, 120);

        foreach ($plans as $plan) {
            $note = $plan['note'] ?? $default;

            try {
                $movement = $import->mode === 'add'
                    ? $this->stock->adjust($plan['product'], $plan['variant'], $plan['quantity'],
                        $plan['quantity'] > 0 ? StockMovementType::Restock : StockMovementType::Adjustment, $note)
                    : $this->stock->set($plan['product'], $plan['variant'], $plan['quantity'], StockMovementType::Adjustment, $note);

                if ($movement) {
                    $result['changed']++;
                }
            } catch (InsufficientStockException $e) {
                $result['failed'][] = self::error($plan['line'], "Stok tinggal {$e->available}, tidak bisa dikurangi ".abs($plan['quantity']).'.');
            } catch (Throwable $e) {
                report($e);
                $result['failed'][] = self::error($plan['line'], 'Gagal disimpan: '.Str::limit($e->getMessage(), 200));
            }
        }

        return $result;
    }

    /** @return array{0: Product, 1: ?ProductVariant} */
    private function target(array $row, array $lookup): array
    {
        $sku = CellValue::string($row['sku'] ?? null, 64, 'SKU');
        $slug = CellValue::string($row['slug'] ?? null, 255, 'Slug');
        $variantName = CellValue::string($row['nama_varian'] ?? null, 100, 'Nama varian');

        if ($sku) {
            if ($variant = $lookup['variants_by_sku']->get(mb_strtolower($sku))) {
                return [$variant->product, $variant];
            }

            $product = $lookup['products_by_sku']->get(mb_strtolower($sku));
            if (! $product) {
                throw new InvalidCell("SKU {$sku} tidak ditemukan.");
            }
        } elseif ($slug) {
            $product = $lookup['products_by_slug']->get(mb_strtolower($slug));
            if (! $product) {
                throw new InvalidCell("Produk dengan slug \"{$slug}\" tidak ditemukan.");
            }
        } else {
            throw new InvalidCell('Isi SKU, atau slug produk.');
        }

        if (! $product->has_variants) {
            return [$product, null];
        }

        if (! $variantName) {
            throw new InvalidCell("{$product->name} memakai varian; isi SKU varian atau nama varian.");
        }

        $variant = $product->variants->first(fn ($v) => mb_strtolower($v->name) === mb_strtolower($variantName));
        if (! $variant) {
            throw new InvalidCell("Varian \"{$variantName}\" tidak ditemukan di {$product->name}.");
        }

        return [$product, $variant];
    }

    private function lookup(array $rows): array
    {
        $values = fn (string $column) => collect($rows)->pluck($column)
            ->filter(fn ($v) => is_scalar($v) && trim((string) $v) !== '')
            ->map(fn ($v) => is_float($v) && floor($v) === $v ? (string) (int) $v : trim((string) $v))
            ->unique()->values();

        $skus = $values('sku');
        $variants = collect();
        $products = collect();

        foreach ($skus->chunk(500) as $chunk) {
            $variants = $variants->merge(ProductVariant::with(['product' => fn ($q) => $q->without('coverImage')])->whereIn('sku', $chunk->all())->get());
            $products = $products->merge(Product::without('coverImage')->with('variants')->whereIn('sku', $chunk->all())->get());
        }

        $bySlug = collect();
        foreach ($values('slug')->chunk(500) as $chunk) {
            $bySlug = $bySlug->merge(Product::without('coverImage')->with('variants')->whereIn('slug', $chunk->all())->get());
        }

        return [
            'variants_by_sku' => $variants->keyBy(fn ($v) => mb_strtolower($v->sku)),
            'products_by_sku' => $products->keyBy(fn ($p) => mb_strtolower($p->sku)),
            'products_by_slug' => $bySlug->keyBy(fn ($p) => mb_strtolower($p->slug)),
        ];
    }
}
