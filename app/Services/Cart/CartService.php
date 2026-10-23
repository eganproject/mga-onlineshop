<?php

namespace App\Services\Cart;

use App\Models\Product;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Keranjang belanja berbasis session, jadi tamu pun bisa menambah produk
 * sebelum login. Isi session: [product_id => quantity].
 */
class CartService
{
    private const KEY = 'cart';

    private ?Collection $lines = null;

    public function __construct(private readonly Session $session) {}

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

        $products = Product::active()->whereKey(array_keys($raw))->get()->keyBy('id');

        // Buang produk yang sudah tidak aktif/terhapus, dan sesuaikan dengan stok.
        $clean = [];
        foreach ($raw as $id => $qty) {
            $product = $products->get($id);
            if ($product && $product->stock > 0) {
                $clean[$id] = min($qty, $product->stock);
            }
        }

        if ($clean !== $raw) {
            $this->session->put(self::KEY, $clean);
        }

        return $this->lines = collect($clean)
            ->map(fn (int $qty, int $id) => new CartLine($products->get($id), $qty))
            ->values();
    }

    /** Tambah produk. Mengembalikan jumlah akhir di keranjang. */
    public function add(Product $product, int $quantity = 1): int
    {
        $raw = $this->raw();
        $new = min(($raw[$product->id] ?? 0) + max(1, $quantity), $product->stock);

        return $this->set($product, $new);
    }

    public function update(Product $product, int $quantity): int
    {
        return $this->set($product, min(max(0, $quantity), $product->stock));
    }

    public function remove(Product $product): void
    {
        $this->set($product, 0);
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
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

    private function set(Product $product, int $quantity): int
    {
        $raw = $this->raw();

        if ($quantity > 0) {
            $raw[$product->id] = $quantity;
        } else {
            unset($raw[$product->id]);
        }

        $this->session->put(self::KEY, $raw);
        $this->lines = null;

        return $quantity;
    }

    /** @return array<int, int> */
    private function raw(): array
    {
        return array_map('intval', (array) $this->session->get(self::KEY, []));
    }
}
