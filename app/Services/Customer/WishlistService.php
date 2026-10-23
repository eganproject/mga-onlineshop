<?php

namespace App\Services\Customer;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Wishlist pelanggan. ID produk di-cache per request supaya kartu produk
 * di listing tidak memicu satu query per kartu.
 */
class WishlistService
{
    /** @var array<int, true>|null */
    private ?array $ids = null;

    public function has(Product|int $product): bool
    {
        $id = $product instanceof Product ? $product->id : $product;

        return isset($this->ids()[$id]);
    }

    /** @return array<int, true> */
    public function ids(): array
    {
        if ($this->ids !== null) {
            return $this->ids;
        }

        $user = Auth::user();

        if (! $user?->isCustomer()) {
            return $this->ids = [];
        }

        return $this->ids = array_fill_keys(
            $user->wishlist()->pluck('products.id')->all(),
            true,
        );
    }

    public function count(): int
    {
        return count($this->ids());
    }

    /** Tambah/hapus produk dari wishlist. Mengembalikan status akhirnya. */
    public function toggle(User $user, Product $product): bool
    {
        $changes = $user->wishlist()->toggle([$product->id]);
        $this->ids = null;

        return in_array($product->id, $changes['attached'], true);
    }
}
