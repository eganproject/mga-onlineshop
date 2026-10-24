<?php

namespace App\Services\Cart;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Keranjang belanja berbasis session, jadi tamu pun bisa menambah produk
 * sebelum login. Isi session: [kunci => quantity], dengan kunci "produk"
 * atau "produk-varian" (lihat CartLine::key()).
 */
class CartService
{
    private const KEY = 'cart';

    /** Pesan perubahan isi keranjang (stok berubah/produk nonaktif) yang belum ditampilkan. */
    private const NOTICES_KEY = 'cart_notices';

    private ?Collection $lines = null;

    public function __construct(private readonly Session $session) {}

    public static function key(int $productId, ?int $variantId = null): string
    {
        return $variantId ? "{$productId}-{$variantId}" : (string) $productId;
    }

    /** @return array{0: int, 1: ?int} */
    public static function parseKey(string $key): array
    {
        [$productId, $variantId] = array_pad(explode('-', $key, 2), 2, null);

        return [(int) $productId, $variantId !== null ? (int) $variantId : null];
    }

    /** @return Collection<int, CartLine> */
    public function lines(): Collection
    {
        if ($this->lines !== null) {
            return $this->lines;
        }

        $raw = $this->raw();

        if ($raw === []) {
            return $this->lines = collect();
        }

        $parsed = collect($raw)->keys()->mapWithKeys(fn ($key) => [(string) $key => self::parseKey((string) $key)]);

        $products = Product::active()->whereKey($parsed->pluck(0)->unique()->all())->get()->keyBy('id');
        $variants = ProductVariant::with('image')->whereKey($parsed->pluck(1)->filter()->all())->get()->keyBy('id');

        // Buang item yang produk/variannya sudah tidak aktif/terhapus, dan sesuaikan dengan stok.
        $clean = [];
        $notices = [];
        $lines = collect();

        foreach ($raw as $key => $qty) {
            [$productId, $variantId] = $parsed[(string) $key];
            $product = $products->get($productId);
            $variant = $variantId ? $variants->get($variantId) : null;

            if (! $this->purchasable($product, $variantId, $variant)) {
                $notices[] = $product
                    ? ($variant ? "{$product->name} ({$variant->name})" : $product->name).' dihapus dari keranjang karena stok habis atau tidak tersedia.'
                    : 'Satu produk dihapus dari keranjang karena sudah tidak dijual.';

                continue;
            }

            $variant?->setRelation('product', $product);
            $line = new CartLine($product, min($qty, $variant?->stock ?? $product->stock), $variant);
            $clean[$line->key()] = $line->quantity;
            $lines->push($line);

            if ($line->quantity < $qty) {
                $notices[] = "Jumlah {$line->name()} disesuaikan menjadi {$line->quantity} karena stok tersisa {$line->availableStock()}.";
            }
        }

        if ($clean != $raw) {
            $this->session->put(self::KEY, $clean);
        }

        if ($notices !== []) {
            $this->session->put(self::NOTICES_KEY, [...(array) $this->session->get(self::NOTICES_KEY, []), ...$notices]);
        }

        return $this->lines = $lines;
    }

    /** Tambah produk/varian. Mengembalikan jumlah akhir di keranjang. */
    public function add(Product $product, ?ProductVariant $variant = null, int $quantity = 1): int
    {
        $key = self::key($product->id, $variant?->id);
        $current = $this->raw()[$key] ?? 0;

        return $this->set($key, min($current + max(1, $quantity), $variant?->stock ?? $product->stock));
    }

    /** Ubah jumlah sebuah baris. Mengembalikan jumlah akhir (dibatasi stok). */
    public function update(string $key, int $quantity): int
    {
        $line = $this->find($key);

        if (! $line) {
            return 0;
        }

        return $this->set($key, min(max(0, $quantity), $line->availableStock()));
    }

    public function remove(string $key): void
    {
        $this->set($key, 0);
    }

    /** Jumlah yang tersimpan untuk sebuah baris (0 bila belum ada). */
    public function quantity(string $key): int
    {
        return (int) ($this->raw()[$key] ?? 0);
    }

    public function find(string $key): ?CartLine
    {
        return $this->lines()->first(fn (CartLine $line) => $line->key() === $key);
    }

    /**
     * Ambil (lalu hapus) pesan penyesuaian keranjang agar bisa ditampilkan sekali.
     *
     * @return list<string>
     */
    public function adjustments(): array
    {
        $this->lines();

        return array_values(array_unique((array) $this->session->pull(self::NOTICES_KEY, [])));
    }

    public function clear(): void
    {
        $this->session->forget([self::KEY, self::NOTICES_KEY]);
        $this->lines = null;
    }

    public function count(): int
    {
        return array_sum($this->raw());
    }

    public function isEmpty(): bool
    {
        return $this->lines()->isEmpty();
    }

    public function subtotal(): int
    {
        return $this->lines()->sum(fn (CartLine $line) => $line->subtotal());
    }

    public function totalWeight(): int
    {
        return $this->lines()->sum(fn (CartLine $line) => $line->weight());
    }

    private function purchasable(?Product $product, ?int $variantId, ?ProductVariant $variant): bool
    {
        if (! $product) {
            return false;
        }

        if ($product->has_variants) {
            // Produk bervarian wajib punya varian aktif yang valid.
            return $variant && (int) $variant->product_id === $product->id && $variant->is_active && $variant->stock > 0;
        }

        return $variantId === null && $product->stock > 0;
    }

    private function set(string $key, int $quantity): int
    {
        $raw = $this->raw();

        if ($quantity > 0) {
            $raw[$key] = $quantity;
        } else {
            unset($raw[$key]);
        }

        $this->session->put(self::KEY, $raw);
        $this->lines = null;

        return $quantity;
    }

    /** @return array<string, int> */
    private function raw(): array
    {
        return array_map('intval', (array) $this->session->get(self::KEY, []));
    }
}
