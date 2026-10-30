<?php

namespace App\Services\Catalog\Transfer;

use App\Enums\StockMovementType;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ProductVariant;
use App\Services\Catalog\ProductService;
use App\Services\Catalog\StockService;
use App\Services\Catalog\Wholesale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Impor produk dari file. Satu baris = satu produk, atau satu varian untuk
 * produk bervarian (baris-baris varian dikelompokkan lewat slug / nama produk).
 *
 * Produk yang sudah ada dicocokkan lewat slug lalu SKU produk dan hanya kolom
 * yang diisi yang diubah. Kategori yang belum ada dibuat otomatis (maks 2 tingkat).
 * Bila satu baris produk error, seluruh produk itu dilewati.
 */
class ProductImporter extends Importer
{
    public const COLUMNS = [
        'slug', 'nama_produk', 'kategori', 'deskripsi', 'sku_produk', 'aktif', 'unggulan',
        'label_varian', 'nama_varian', 'sku_varian', 'varian_aktif',
        'harga', 'harga_coret', 'berat_gram', 'stok', 'url_gambar',
        'harga_grosir', 'minimal_beli',
    ];

    public const MAX_VARIANTS = 50;

    public const MAX_IMAGES = 10;

    public function __construct(
        private readonly ProductService $products,
        private readonly StockService $stock,
        private readonly ImageDownloader $images,
    ) {}

    public static function aliases(): array
    {
        return [
            'nama' => 'nama_produk',
            'produk' => 'nama_produk',
            'sku' => 'sku_produk',
            'varian' => 'nama_varian',
            'berat' => 'berat_gram',
            'gambar' => 'url_gambar',
            'foto' => 'url_gambar',
            'url_foto' => 'url_gambar',
            'harga_normal' => 'harga_coret',
            'stock' => 'stok',
            'status' => 'aktif',
            'grosir' => 'harga_grosir',
            'min_beli' => 'minimal_beli',
            'minimal_pembelian' => 'minimal_beli',
        ];
    }

    protected function requiredHeaders(): array
    {
        return [['slug', 'nama_produk']];
    }

    protected function run(ProductImport $import, iterable $rows, bool $apply): array
    {
        [$groups, $total, $errors] = $this->group($rows);
        $lookup = $this->lookup($groups);

        $plans = [];
        $errorRows = 0;
        $claimedProducts = [];
        $claimedProductSkus = [];
        $claimedVariantSkus = [];

        foreach ($groups as $key => $group) {
            $groupErrors = $group['errors'];
            $plan = null;

            if (! $groupErrors) {
                [$plan, $groupErrors] = $this->plan($group, $lookup);
            }

            // Konflik antar produk di file yang sama.
            if ($plan) {
                if ($plan['product_id'] && isset($claimedProducts[$plan['product_id']])) {
                    $groupErrors[] = self::error($plan['line'], 'Produk ini sudah muncul di baris '.$claimedProducts[$plan['product_id']].' (cocok lewat slug/SKU yang sama).');
                }
                if ($plan['sku'] && isset($claimedProductSkus[mb_strtolower($plan['sku'])])) {
                    $groupErrors[] = self::error($plan['line'], "SKU produk {$plan['sku']} dipakai lebih dari satu produk di file.");
                }
                foreach ($plan['variants'] as $variant) {
                    if ($variant['sku'] && isset($claimedVariantSkus[mb_strtolower($variant['sku'])])) {
                        $groupErrors[] = self::error($variant['line'], "SKU varian {$variant['sku']} dipakai lebih dari satu kali di file.");
                    }
                }
            }

            if ($groupErrors) {
                $errors = [...$errors, ...$groupErrors];
                $errorRows += count($group['rows']);

                continue;
            }

            if ($plan['product_id']) {
                $claimedProducts[$plan['product_id']] = $plan['line'];
            }
            if ($plan['sku']) {
                $claimedProductSkus[mb_strtolower($plan['sku'])] = true;
            }
            foreach ($plan['variants'] as $variant) {
                if ($variant['sku']) {
                    $claimedVariantSkus[mb_strtolower($variant['sku'])] = true;
                }
            }

            $plans[$key] = $plan;
        }

        $plans = collect($plans);
        usort($errors, fn ($a, $b) => $a['row'] <=> $b['row']);

        return [
            'total' => $total,
            'error_rows' => $errorRows,
            'errors' => $errors,
            'summary' => [
                'products' => count($groups),
                'create' => $plans->whereNull('product_id')->count(),
                'update' => $plans->whereNotNull('product_id')->count(),
                'skipped' => count($groups) - $plans->count(),
                'variants' => $plans->sum(fn ($p) => count($p['variants'])),
                'new_categories' => $plans->pluck('category.new')->filter()->unique()->values()->all(),
                'images' => $plans->sum(fn ($p) => count($p['images'])),
            ],
            'preview' => $plans->take(self::MAX_PREVIEW)->map(fn ($p) => [
                'row' => $p['line'],
                'action' => $p['product_id'] ? 'update' : 'create',
                'name' => $p['name'] ?? $p['current_name'],
                'category' => $p['category']['label'] ?? null,
                'variants' => count($p['variants']),
                'price' => $p['variants'] ? collect($p['variants'])->pluck('price')->filter(fn ($v) => $v !== null)->min() : $p['price'],
                'stock' => $p['variants'] ? collect($p['variants'])->pluck('stock')->filter(fn ($v) => $v !== null)->whenNotEmpty(fn ($c) => collect([$c->sum()]))->first() : $p['stock'],
            ])->values()->all(),
            'result' => $apply ? $this->apply($import, $plans) : null,
        ];
    }

    /**
     * Kelompokkan baris per produk. Kunci: slug bila diisi, selain itu nama produk.
     *
     * @return array{0: array<string, array>, 1: int, 2: list<array>}
     */
    private function group(iterable $rows): array
    {
        $groups = [];
        $errors = [];
        $total = 0;

        foreach ($rows as $line => $row) {
            $total++;
            $slug = is_scalar($row['slug'] ?? null) ? mb_strtolower(trim((string) $row['slug'])) : '';
            $name = is_scalar($row['nama_produk'] ?? null) ? mb_strtolower(trim((string) $row['nama_produk'])) : '';

            if ($slug === '' && $name === '') {
                $errors[] = self::error($line, 'Isi slug (untuk produk yang sudah ada) atau nama produk.');

                continue;
            }

            $key = $slug !== '' ? "slug:{$slug}" : "nama:{$name}";
            $groups[$key] ??= ['rows' => [], 'errors' => []];
            $groups[$key]['rows'][$line] = $row;
        }

        return [$groups, $total, $errors];
    }

    /** Muat data pembanding sekaligus (bukan per baris) supaya validasi ribuan baris tetap cepat. */
    private function lookup(array $groups): array
    {
        $all = collect($groups)->flatMap(fn ($g) => $g['rows']);
        $values = fn (string $column) => $all->pluck($column)->filter(fn ($v) => is_scalar($v) && trim((string) $v) !== '')
            ->map(fn ($v) => trim((string) $v))->unique()->values();

        $products = collect();
        foreach ($values('slug')->chunk(500) as $chunk) {
            $products = $products->merge(Product::without('coverImage')->whereIn('slug', $chunk->all())->get());
        }
        foreach ($values('sku_produk')->chunk(500) as $chunk) {
            $products = $products->merge(Product::without('coverImage')->whereIn('sku', $chunk->all())->get());
        }
        $byName = collect();
        foreach ($values('nama_produk')->chunk(500) as $chunk) {
            $byName = $byName->merge(Product::without('coverImage')->whereIn('name', $chunk->all())->get());
        }
        $products = $products->merge($byName)->unique('id');

        $variantSkus = collect();
        foreach ($values('sku_varian')->chunk(500) as $chunk) {
            $variantSkus = $variantSkus->merge(ProductVariant::whereIn('sku', $chunk->all())->get(['id', 'product_id', 'sku']));
        }

        $variantsByProduct = ProductVariant::whereIn('product_id', $products->pluck('id'))
            ->get(['id', 'product_id', 'name', 'sku'])->groupBy('product_id');

        return [
            'by_slug' => $products->keyBy(fn ($p) => mb_strtolower($p->slug)),
            'by_name' => $byName->unique('id')->groupBy(fn ($p) => mb_strtolower($p->name)),
            'by_sku' => $products->filter(fn ($p) => $p->sku)->keyBy(fn ($p) => mb_strtolower($p->sku)),
            'variant_skus' => $variantSkus->keyBy(fn ($v) => mb_strtolower($v->sku)),
            'variants' => $variantsByProduct,
            'categories' => Category::get(['id', 'parent_id', 'name']),
        ];
    }

    /**
     * Validasi satu produk dan susun rencana penyimpanannya.
     *
     * @return array{0: ?array, 1: list<array>}
     */
    private function plan(array $group, array $lookup): array
    {
        $errors = [];
        $rows = $group['rows'];
        $firstLine = array_key_first($rows);
        $first = fn (string $column) => collect($rows)->pluck($column)->first(fn ($v) => $v !== null && $v !== '');

        $read = function (int $line, callable $parse) use (&$errors) {
            try {
                return $parse();
            } catch (InvalidCell $e) {
                $errors[] = self::error($line, $e->getMessage());

                return null;
            }
        };

        // Produk yang dituju.
        $slug = $read($firstLine, fn () => CellValue::string($first('slug'), 255, 'Slug'));
        $sku = $read($firstLine, fn () => CellValue::string($first('sku_produk'), 64, 'SKU produk'));
        $product = $slug ? $lookup['by_slug']->get(mb_strtolower($slug)) : null;
        $bySku = $sku ? $lookup['by_sku']->get(mb_strtolower($sku)) : null;

        if ($slug && ! $product && ! preg_match('/^[a-z0-9_-]+$/i', $slug)) {
            $errors[] = self::error($firstLine, 'Slug hanya boleh huruf, angka, tanda hubung (-) dan garis bawah (_).');
        }

        if ($product && $bySku && $bySku->id !== $product->id) {
            $errors[] = self::error($firstLine, "SKU produk {$sku} sudah dipakai produk lain ({$bySku->name}).");
        }
        $product ??= $bySku;

        $name = $read($firstLine, fn () => CellValue::string($first('nama_produk'), 255, 'Nama produk'));

        // Tanpa slug/SKU yang cocok: pakai nama produk, asal tidak ada nama kembar.
        // Mencegah produk dobel saat file yang sama diimpor ulang.
        if (! $product && ! $slug && $name) {
            $sameName = $lookup['by_name']->get(mb_strtolower($name), collect());
            if ($sameName->count() > 1) {
                $errors[] = self::error($firstLine, "Ada beberapa produk bernama \"{$name}\". Isi kolom slug untuk menentukan produk yang dimaksud.");
            }
            $product = $sameName->count() === 1 ? $sameName->first() : null;
        }
        $isVariant = collect($rows)->contains(fn ($r) => filled($r['nama_varian'] ?? null) || filled($r['sku_varian'] ?? null));

        if (! $product && ! $name) {
            $errors[] = self::error($firstLine, $slug ? "Produk dengan slug \"{$slug}\" tidak ditemukan; isi nama produk untuk membuat produk baru." : 'Nama produk wajib diisi.');
        }

        $plan = [
            'line' => $firstLine,
            'lines' => array_keys($rows),
            'product_id' => $product?->id,
            'current_name' => $product?->name,
            'slug' => $product ? null : $slug,
            'name' => $name,
            'sku' => $sku,
            'description' => $read($firstLine, fn () => CellValue::string($first('deskripsi'), 10000, 'Deskripsi')),
            'is_active' => $read($firstLine, fn () => CellValue::boolean($first('aktif'), 'Aktif')),
            'is_featured' => $read($firstLine, fn () => CellValue::boolean($first('unggulan'), 'Unggulan')),
            'variant_label' => $read($firstLine, fn () => CellValue::string($first('label_varian'), 50, 'Label varian')),
            'category' => $read($firstLine, fn () => $this->category($first('kategori'), $lookup['categories'])),
            'images' => $read($firstLine, fn () => CellValue::urls($first('url_gambar'), self::MAX_IMAGES)) ?? [],
            'weight' => null,
            'price' => null,
            'compare_at_price' => null,
            'stock' => null,
            'variants' => [],
            'wholesale_tiers' => null,
            'min_order_qty' => $read($firstLine, fn () => CellValue::integer($first('minimal_beli'), 'Minimal beli', 1, 999)),
        ];

        if ($product && $product->has_variants !== $isVariant) {
            $errors[] = self::error($firstLine, $product->has_variants
                ? 'Produk ini memakai varian; isi kolom nama_varian di setiap barisnya.'
                : 'Produk ini tidak memakai varian. Aktifkan varian lewat form produk terlebih dahulu.');

            return [null, $errors];
        }

        if (! $isVariant) {
            if (count($rows) > 1) {
                foreach (array_slice(array_keys($rows), 1) as $line) {
                    $errors[] = self::error($line, "Produk yang sama sudah ada di baris {$firstLine}. Untuk varian, isi kolom nama_varian.");
                }
            }

            $row = $rows[$firstLine];
            $plan['price'] = $read($firstLine, fn () => CellValue::integer($row['harga'] ?? null, 'Harga', 0, 1_000_000_000_000));
            $plan['compare_at_price'] = $read($firstLine, fn () => CellValue::integer($row['harga_coret'] ?? null, 'Harga coret', 0, 1_000_000_000_000));
            $plan['weight'] = $read($firstLine, fn () => CellValue::integer($row['berat_gram'] ?? null, 'Berat', 1, 100_000));
            $plan['stock'] = $read($firstLine, fn () => CellValue::integer($row['stok'] ?? null, 'Stok', 0, 1_000_000));
            $plan['wholesale_tiers'] = $read($firstLine, fn () => self::tiers($row['harga_grosir'] ?? null, $plan['price'] ?? $product?->price));

            if (! $product && $plan['price'] === null) {
                $errors[] = self::error($firstLine, 'Harga wajib diisi untuk produk baru.');
            }
        } else {
            $plan['weight'] = $product ? null : $read($firstLine, fn () => CellValue::integer($first('berat_gram'), 'Berat', 1, 100_000));
            $plan['variants'] = $this->planVariants($rows, $product, $lookup, $read, $errors);
        }

        return [$errors ? null : $plan, $errors];
    }

    private function planVariants(array $rows, ?Product $product, array $lookup, callable $read, array &$errors): array
    {
        $existing = $product ? $lookup['variants']->get($product->id, collect()) : collect();
        $variants = [];
        $names = [];

        if (count($rows) > self::MAX_VARIANTS) {
            $errors[] = self::error(array_key_first($rows), 'Maksimal '.self::MAX_VARIANTS.' varian per produk.');
        }

        foreach ($rows as $line => $row) {
            $name = $read($line, fn () => CellValue::string($row['nama_varian'] ?? null, 100, 'Nama varian'));
            $sku = $read($line, fn () => CellValue::string($row['sku_varian'] ?? null, 64, 'SKU varian'));
            $match = null;

            if ($sku && ($owner = $lookup['variant_skus']->get(mb_strtolower($sku)))) {
                if ($owner->product_id !== $product?->id) {
                    $errors[] = self::error($line, "SKU varian {$sku} sudah dipakai produk lain.");

                    continue;
                }
                $match = $existing->firstWhere('id', $owner->id);
            }
            if (! $match && $name) {
                $match = $existing->first(fn ($v) => mb_strtolower($v->name) === mb_strtolower($name));
            }

            if (! $match && ! $name) {
                $errors[] = self::error($line, 'Nama varian wajib diisi untuk varian baru.');

                continue;
            }

            $finalName = $name ?? $match->name;
            if (isset($names[mb_strtolower($finalName)])) {
                $errors[] = self::error($line, "Varian \"{$finalName}\" muncul dua kali untuk produk yang sama.");

                continue;
            }
            $names[mb_strtolower($finalName)] = true;

            $variant = [
                'line' => $line,
                'id' => $match?->id,
                'name' => $name,
                'sku' => $sku,
                'price' => $read($line, fn () => CellValue::integer($row['harga'] ?? null, 'Harga', 0, 1_000_000_000_000)),
                'compare_at_price' => $read($line, fn () => CellValue::integer($row['harga_coret'] ?? null, 'Harga coret', 0, 1_000_000_000_000)),
                'weight' => $read($line, fn () => CellValue::integer($row['berat_gram'] ?? null, 'Berat', 1, 100_000)),
                'stock' => $read($line, fn () => CellValue::integer($row['stok'] ?? null, 'Stok', 0, 1_000_000)),
                'is_active' => $read($line, fn () => CellValue::boolean($row['varian_aktif'] ?? null, 'Varian aktif')),
            ];
            $variant['wholesale_tiers'] = $read($line, fn () => self::tiers($row['harga_grosir'] ?? null, $variant['price'] ?? $match?->price));

            if (! $match && $variant['price'] === null) {
                $errors[] = self::error($line, 'Harga wajib diisi untuk varian baru.');
            }

            $variants[] = $variant;
        }

        return $variants;
    }

    /**
     * "Tempat Brosur > Brosur Tempel" atau "Brosur Tempel". Mengembalikan
     * ['id' => ?int, 'path' => [induk, anak], 'new' => ?label, 'label' => label].
     */
    private function category(mixed $value, Collection $categories): ?array
    {
        $text = CellValue::string($value, 255, 'Kategori');
        if (! $text) {
            return null;
        }

        $parts = array_values(array_filter(array_map('trim', preg_split('/\s*[>\/]\s*/', $text)), 'strlen'));
        if (count($parts) > 2) {
            throw new InvalidCell('Kategori maksimal 2 tingkat, contoh: "Tempat Brosur > Brosur Tempel".');
        }
        foreach ($parts as $part) {
            if (mb_strlen($part) > 100) {
                throw new InvalidCell('Nama kategori maksimal 100 karakter.');
            }
        }

        $same = fn ($category, string $name) => mb_strtolower($category->name) === mb_strtolower($name);
        $label = implode(' > ', $parts);

        if (count($parts) === 1) {
            $matches = $categories->filter(fn ($c) => $same($c, $parts[0]));
            if ($matches->count() > 1) {
                throw new InvalidCell("Ada beberapa kategori bernama \"{$parts[0]}\". Tulis lengkap, contoh: \"Induk > {$parts[0]}\".");
            }

            return ['id' => $matches->first()?->id, 'path' => $parts, 'new' => $matches->isEmpty() ? $label : null, 'label' => $label];
        }

        $parent = $categories->first(fn ($c) => $c->parent_id === null && $same($c, $parts[0]));
        if (! $parent && $categories->contains(fn ($c) => $c->parent_id !== null && $same($c, $parts[0]))) {
            throw new InvalidCell("\"{$parts[0]}\" adalah subkategori, tidak bisa punya subkategori lagi.");
        }

        $child = $parent ? $categories->first(fn ($c) => $c->parent_id === $parent->id && $same($c, $parts[1])) : null;

        return ['id' => $child?->id, 'path' => $parts, 'new' => $child ? null : $label, 'label' => $label];
    }

    /** Simpan semua rencana yang valid. Error saat menyimpan dicatat per produk. */
    private function apply(ProductImport $import, Collection $plans): array
    {
        $result = ['created' => 0, 'updated' => 0, 'failed' => [], 'warnings' => [], 'categories' => 0];
        $note = 'Impor produk: '.Str::limit($import->filename, 120);
        $categoryCache = [];

        foreach ($plans as $plan) {
            try {
                $product = DB::transaction(fn () => $this->save($plan, $note, $categoryCache, $result));
            } catch (Throwable $e) {
                report($e);
                $result['failed'][] = self::error($plan['line'], 'Gagal disimpan: '.Str::limit($e->getMessage(), 200));

                continue;
            }

            $plan['product_id'] ? $result['updated']++ : $result['created']++;

            // Foto diunduh di luar transaksi; hanya untuk produk yang belum punya foto.
            if ($plan['images'] && ! $product->images()->exists()) {
                foreach ($this->images->attach($product, $plan['images']) as $warning) {
                    $result['warnings'][] = self::error($plan['line'], $warning);
                }
            }
        }

        return $result;
    }

    private function save(array $plan, string $note, array &$categoryCache, array &$result): Product
    {
        $product = $plan['product_id']
            ? Product::without('coverImage')->lockForUpdate()->findOrFail($plan['product_id'])
            : new Product(['weight' => 100, 'is_active' => true, 'is_featured' => false]);
        $isNew = ! $product->exists;
        $isVariant = (bool) $plan['variants'];

        if ($isNew) {
            $product->slug = $plan['slug'] && ! Product::where('slug', $plan['slug'])->exists()
                ? Str::lower($plan['slug'])
                : $this->products->uniqueSlug($plan['name'], $product);
        }

        foreach (['name', 'sku', 'description', 'is_active', 'is_featured', 'weight', 'min_order_qty'] as $field) {
            if ($plan[$field] !== null) {
                $product->{$field} = $plan[$field];
            }
        }
        if ($isVariant && $plan['variant_label'] !== null) {
            $product->variant_label = $plan['variant_label'];
        }
        if ($plan['category']) {
            $product->category_id = $this->resolveCategory($plan['category'], $categoryCache, $result);
        }

        if (! $isVariant) {
            if ($plan['price'] !== null) {
                $product->price = $plan['price'];
            }
            if ($plan['compare_at_price'] !== null) {
                $product->compare_at_price = $plan['compare_at_price'] ?: null;
            }
            if ($plan['wholesale_tiers'] !== null) {
                $product->wholesale_tiers = $plan['wholesale_tiers'];
            }
        } else {
            $product->price ??= (int) collect($plan['variants'])->min('price');
        }

        $product->save();

        if ($isVariant) {
            $this->saveVariants($product, $plan['variants'], $note);
        } elseif ($plan['stock'] !== null) {
            $isNew
                ? $this->stock->adjust($product, null, $plan['stock'], StockMovementType::Initial, $note)
                : $this->stock->set($product, null, $plan['stock'], StockMovementType::Adjustment, $note);
        }

        return $product;
    }

    private function saveVariants(Product $product, array $rows, string $note): void
    {
        $existing = $product->variants()->get()->keyBy('id');
        $position = (int) $existing->max('sort_order') + 1;

        foreach ($rows as $row) {
            $variant = $row['id'] ? $existing->get($row['id']) : null;
            $isNew = ! $variant;
            $variant ??= new ProductVariant(['product_id' => $product->id, 'sort_order' => $position++, 'is_active' => true]);

            foreach (['name', 'sku', 'price', 'weight', 'is_active'] as $field) {
                if ($row[$field] !== null) {
                    $variant->{$field} = $row[$field];
                }
            }
            if ($row['compare_at_price'] !== null) {
                $variant->compare_at_price = $row['compare_at_price'] ?: null;
            }
            if ($row['wholesale_tiers'] !== null) {
                $variant->wholesale_tiers = $row['wholesale_tiers'];
            }

            $variant->save();
            $variant->setRelation('product', $product);

            if ($row['stock'] !== null) {
                $isNew
                    ? $this->stock->adjust($product, $variant, $row['stock'], StockMovementType::Initial, $note)
                    : $this->stock->set($product, $variant, $row['stock'], StockMovementType::Adjustment, $note);
            }
        }

        $product->syncVariantSummary();
    }

    /**
     * Kolom harga_grosir: "10=65000; 50=60000". Kosong = tidak diubah, 0/- = hapus grosir.
     *
     * @return list<array{min_qty: int, price: int}>|null
     */
    private static function tiers(mixed $value, ?int $regularPrice): ?array
    {
        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        if (in_array(mb_strtolower($text), ['0', '-', 'tidak', 'hapus'], true)) {
            return [];
        }

        $tiers = Wholesale::fromText($text) ?? throw new InvalidCell('Harga grosir harus berformat "10=65000; 50=60000" (jumlah=harga per pcs).');

        if (count($tiers) > Wholesale::MAX_TIERS) {
            throw new InvalidCell('Harga grosir maksimal '.Wholesale::MAX_TIERS.' tingkat.');
        }

        if ($problem = ProductRequest::tierProblem($tiers, $regularPrice ?? PHP_INT_MAX)) {
            throw new InvalidCell("Harga grosir: {$problem}");
        }

        return $tiers;
    }

    private function resolveCategory(array $category, array &$cache, array &$result): int
    {
        if ($category['id']) {
            return $category['id'];
        }

        $key = mb_strtolower($category['label']);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $parentId = null;
        foreach ($category['path'] as $name) {
            $found = Category::where('parent_id', $parentId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->first();

            if (! $found) {
                $found = new Category(['name' => $name, 'parent_id' => $parentId, 'is_active' => true]);
                $found->slug = $this->categorySlug($name);
                $found->save();
                $result['categories']++;
            }

            $parentId = $found->id;
        }

        return $cache[$key] = $parentId;
    }

    private function categorySlug(string $name): string
    {
        $base = Str::slug($name) ?: 'kategori';
        $slug = $base;
        $i = 2;

        while (Category::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
