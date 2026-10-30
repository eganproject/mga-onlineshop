<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;

/**
 * Aturan harga grosir bertingkat.
 *
 * Tingkat disimpan di `products.wholesale_tiers` (produk tanpa varian) atau
 * `product_variants.wholesale_tiers` (per varian) sebagai [{min_qty, price}].
 * Jumlah yang dipakai untuk menentukan tingkat = gabungan semua varian dari
 * produk yang sama di keranjang (5 Hitam + 5 Putih = 10 pcs).
 */
final class Wholesale
{
    /** Batas jumlah tingkat per produk/varian. */
    public const MAX_TIERS = 5;

    /**
     * Rapikan isian tingkat: buang baris kosong/tidak valid, urutkan dari jumlah terkecil,
     * dan hanya simpan harga yang lebih murah dari harga normal & tingkat sebelumnya.
     *
     * @return list<array{min_qty: int, price: int}>
     */
    public static function normalize(mixed $tiers, ?int $regularPrice = null): array
    {
        $rows = collect(is_array($tiers) ? $tiers : [])
            ->filter(fn ($t) => is_array($t))
            ->map(fn ($t) => ['min_qty' => (int) ($t['min_qty'] ?? 0), 'price' => (int) ($t['price'] ?? 0)])
            ->filter(fn ($t) => $t['min_qty'] >= 2 && $t['price'] > 0)
            ->sortBy('min_qty')
            ->unique('min_qty')
            ->values();

        $result = [];
        $ceiling = $regularPrice ?? PHP_INT_MAX;

        foreach ($rows as $row) {
            if ($row['price'] >= $ceiling) {
                continue;
            }
            $result[] = $row;
            $ceiling = $row['price'];
        }

        return array_slice($result, 0, self::MAX_TIERS);
    }

    /** @return list<array{min_qty: int, price: int}> */
    public static function tiers(Product $product, ?ProductVariant $variant = null): array
    {
        return $variant
            ? self::normalize($variant->wholesale_tiers, $variant->price)
            : self::normalize($product->wholesale_tiers, $product->price);
    }

    /** Tingkat yang berlaku untuk jumlah tertentu (null = harga normal). */
    public static function tierFor(array $tiers, int $quantity): ?array
    {
        $match = null;

        foreach ($tiers as $tier) {
            if ($quantity >= $tier['min_qty']) {
                $match = $tier;
            }
        }

        return $match;
    }

    /** Tingkat berikutnya yang belum tercapai (untuk ajakan "tambah X lagi"). */
    public static function nextTier(array $tiers, int $quantity): ?array
    {
        foreach ($tiers as $tier) {
            if ($quantity < $tier['min_qty']) {
                return $tier;
            }
        }

        return null;
    }

    public static function regularPrice(Product $product, ?ProductVariant $variant = null): int
    {
        return $variant?->price ?? $product->price;
    }

    /** Harga per pcs untuk jumlah gabungan produk tersebut. */
    public static function unitPrice(Product $product, ?ProductVariant $variant, int $productQuantity): int
    {
        return self::tierFor(self::tiers($product, $variant), $productQuantity)['price'] ?? self::regularPrice($product, $variant);
    }

    /** Label rentang jumlah tiap tingkat, mis. "10–49 pcs" / "50+ pcs". */
    public static function rangeLabel(array $tiers, int $index): string
    {
        $min = $tiers[$index]['min_qty'];
        $next = $tiers[$index + 1]['min_qty'] ?? null;

        return $next ? "{$min}–".($next - 1).' pcs' : "≥ {$min} pcs";
    }

    /**
     * Tabel harga untuk halaman produk: baris harga normal lalu tiap tingkat.
     *
     * @return list<array{min_qty: int, label: string, price: int, price_label: string, save: int}>
     */
    public static function table(array $tiers, int $regularPrice): array
    {
        if ($tiers === []) {
            return [];
        }

        $rows = [[
            'min_qty' => 1,
            'label' => '1–'.($tiers[0]['min_qty'] - 1).' pcs',
            'price' => $regularPrice,
            'price_label' => Money::rupiah($regularPrice),
            'save' => 0,
        ]];

        foreach ($tiers as $i => $tier) {
            $rows[] = [
                'min_qty' => $tier['min_qty'],
                'label' => self::rangeLabel($tiers, $i),
                'price' => $tier['price'],
                'price_label' => Money::rupiah($tier['price']),
                'save' => $regularPrice > 0 ? (int) round(100 - $tier['price'] / $regularPrice * 100) : 0,
            ];
        }

        return $rows;
    }

    /** Apakah voucher boleh dipakai untuk barang berharga grosir (Pengaturan Toko). */
    public static function voucherCombinable(): bool
    {
        return (bool) config('shop.wholesale.voucher_combinable', true);
    }

    /** Teks "Format impor" tingkat: "10=65000; 50=60000". */
    public static function toText(array $tiers): string
    {
        return collect($tiers)->map(fn ($t) => "{$t['min_qty']}={$t['price']}")->implode('; ');
    }

    /**
     * Baca teks impor "10=65000; 50=60000" (pemisah ; atau baris baru, = atau :).
     *
     * @return list<array{min_qty: int, price: int}>|null null bila formatnya salah.
     */
    public static function fromText(?string $text): ?array
    {
        $text = trim((string) $text);

        if ($text === '') {
            return [];
        }

        $tiers = [];

        foreach (preg_split('/[;\n|]+/', $text) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if (! preg_match('/^(\d+)\s*(?:pcs)?\s*[=:]\s*(?:rp\.?\s*)?([\d.,]+)$/i', $part, $m)) {
                return null;
            }

            $tiers[] = ['min_qty' => (int) $m[1], 'price' => (int) preg_replace('/\D/', '', $m[2])];
        }

        return $tiers;
    }
}
