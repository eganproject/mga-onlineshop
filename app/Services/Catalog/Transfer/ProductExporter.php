<?php

namespace App\Services\Catalog\Transfer;

use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\Wholesale;
use Generator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ekspor katalog & stok ke CSV/XLSX. Format ekspor produk sama persis dengan
 * template impor, jadi file hasil ekspor bisa diedit lalu diimpor ulang.
 */
class ProductExporter
{
    /** @return array<string, iterable<array>> */
    public function products(Builder $query): array
    {
        return [
            'Produk' => $this->productRows($query),
            'Petunjuk' => self::productGuide(),
        ];
    }

    /** @return array<string, iterable<array>> */
    public function stock(Builder $query): array
    {
        return [
            'Stok' => $this->stockRows($query),
            'Petunjuk' => self::stockGuide(),
        ];
    }

    /** @return array<string, iterable<array>> */
    public function productTemplate(): array
    {
        return [
            'Produk' => [
                ProductImporter::COLUMNS,
                ['', 'X Banner 60x160 cm', 'Tripod & Stand Banner', 'Rangka X banner fiber, tanpa poster.', 'XB-60160', 'ya', 'tidak', '', '', '', '', 15000, 29000, 700, 25, 'https://contoh.com/foto/x-banner.jpg'],
                ['', 'Brosur Tempel Akrilik A4', 'Tempat Brosur > Brosur Tempel', 'Akrilik bening 2 mm.', '', 'ya', 'ya', 'Orientasi', 'Portrait', 'BT-A4-P', 'ya', 19000, '', 250, 10, ''],
                ['', 'Brosur Tempel Akrilik A4', '', '', '', '', '', '', 'Landscape', 'BT-A4-L', 'ya', 19000, '', 250, 8, ''],
            ],
            'Petunjuk' => self::productGuide(),
        ];
    }

    private function productRows(Builder $query): Generator
    {
        yield ProductImporter::COLUMNS;

        $categories = Category::pluck('name', 'id');
        $parents = Category::pluck('parent_id', 'id');
        $categoryPath = fn (?int $id) => $id
            ? ($parents[$id] ? $categories[$parents[$id]].' > ' : '').$categories[$id]
            : '';

        foreach ($query->without('coverImage')->with(['variants', 'images'])->orderBy('id')->lazy(200) as $product) {
            /** @var Product $product */
            $images = $product->images->map->url()->implode(' | ');
            $base = [
                $product->slug,
                $product->name,
                $categoryPath($product->category_id),
                (string) $product->description,
                (string) $product->sku,
                $product->is_active ? 'ya' : 'tidak',
                $product->is_featured ? 'ya' : 'tidak',
                (string) $product->variant_label,
            ];

            if (! $product->has_variants) {
                yield [...$base, '', '', '', $product->price, $product->compare_at_price ?? '', $product->weight, $product->stock, $images,
                    Wholesale::toText($product->wholesale_tiers ?? []), $product->minOrderQty()];

                continue;
            }

            foreach ($product->variants as $i => $variant) {
                yield [
                    ...$base,
                    $variant->name,
                    (string) $variant->sku,
                    $variant->is_active ? 'ya' : 'tidak',
                    $variant->price,
                    $variant->compare_at_price ?? '',
                    $variant->weight ?? '',
                    $variant->stock,
                    $i === 0 ? $images : '',
                    Wholesale::toText($variant->wholesale_tiers ?? []),
                    $i === 0 ? $product->minOrderQty() : '',
                ];
            }
        }
    }

    private function stockRows(Builder $query): Generator
    {
        yield [...StockImporter::COLUMNS, 'kategori', 'aktif'];

        foreach ($query->without('coverImage')->with(['variants', 'category'])->orderBy('name')->lazy(200) as $product) {
            /** @var Product $product */
            if (! $product->has_variants) {
                yield [(string) $product->sku, $product->slug, $product->name, '', $product->stock, '', $product->category?->name ?? '', $product->is_active ? 'ya' : 'tidak'];

                continue;
            }

            foreach ($product->variants as $variant) {
                yield [(string) $variant->sku, $product->slug, $product->name, $variant->name, $variant->stock, '', $product->category?->name ?? '', $variant->is_active && $product->is_active ? 'ya' : 'tidak'];
            }
        }
    }

    public static function productGuide(): array
    {
        return [
            ['Kolom', 'Keterangan'],
            ['slug', 'Kosongkan untuk produk baru. Untuk mengubah produk yang sudah ada, isi slug-nya (lihat file ekspor). Produk juga dicocokkan lewat sku_produk atau nama produk.'],
            ['nama_produk', 'Wajib untuk produk baru. Produk bervarian: satu baris per varian, nama produk / slug sama.'],
            ['kategori', 'Nama kategori, atau "Induk > Subkategori". Kategori yang belum ada akan dibuat otomatis.'],
            ['deskripsi', 'Opsional.'],
            ['sku_produk', 'Opsional, harus unik.'],
            ['aktif / unggulan', '"ya" atau "tidak". Kosong = produk baru aktif & bukan unggulan; produk lama tidak berubah.'],
            ['label_varian', 'Judul pilihan varian, mis. "Warna" atau "Tipe HP".'],
            ['nama_varian', 'Isi hanya untuk produk bervarian, mis. "Hitam".'],
            ['sku_varian', 'Opsional, harus unik. Dipakai untuk mencocokkan varian yang sudah ada.'],
            ['varian_aktif', '"ya" atau "tidak".'],
            ['harga', 'Rupiah tanpa desimal, mis. 150000 atau 150.000. Wajib untuk produk/varian baru.'],
            ['harga_coret', 'Harga sebelum diskon (opsional). Isi 0 untuk menghapus harga coret.'],
            ['berat_gram', 'Berat dalam gram untuk hitung ongkir. Kosong = 100 gram untuk produk baru.'],
            ['stok', 'Stok saat ini. Perubahan stok tercatat di riwayat stok. Kosong = stok produk lama tidak berubah.'],
            ['url_gambar', 'Opsional. Satu atau beberapa URL foto (pisahkan dengan |). Hanya diunduh bila produk belum punya foto.'],
            ['harga_grosir', 'Opsional. Tingkat harga grosir per pcs, format jumlah=harga dipisah titik koma, mis. "10=65000; 50=60000". Produk bervarian: per baris varian (jumlah dihitung gabungan semua varian). Kosong = tidak diubah, 0 = hapus grosir.'],
            ['minimal_beli', 'Opsional. Minimal pembelian per pesanan (gabungan semua varian). 1 = bisa beli satuan.'],
            ['', ''],
            ['Catatan', 'Hanya sheet pertama yang dibaca. Kolom kosong pada produk lama tidak mengubah data. Produk yang salah satu barisnya error akan dilewati seluruhnya.'],
        ];
    }

    public static function stockGuide(): array
    {
        return [
            ['Kolom', 'Keterangan'],
            ['sku', 'SKU produk atau SKU varian. Bila kosong, produk dicocokkan lewat slug (+ nama_varian untuk produk bervarian).'],
            ['slug / nama_varian', 'Dipakai bila SKU kosong.'],
            ['nama_produk', 'Hanya informasi, tidak dibaca saat impor.'],
            ['stok', 'Mode "Ganti stok": stok menjadi angka ini. Mode "Tambah / kurangi": stok ditambah angka ini (pakai minus untuk mengurangi).'],
            ['catatan', 'Opsional, tampil di riwayat stok.'],
            ['kategori / aktif', 'Hanya informasi, tidak dibaca saat impor.'],
        ];
    }
}
