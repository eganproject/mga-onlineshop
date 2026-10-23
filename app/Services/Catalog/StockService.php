<?php

namespace App\Services\Catalog;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Satu-satunya pintu untuk mengubah stok produk/varian. Setiap perubahan
 * dikunci per baris (anti overselling) dan dicatat di stock_movements.
 */
class StockService
{
    /** Tambah/kurangi stok sebanyak $change (negatif = berkurang). */
    public function adjust(
        Product $product,
        ?ProductVariant $variant,
        int $change,
        StockMovementType $type,
        ?string $note = null,
        ?Order $order = null,
    ): ?StockMovement {
        $this->guard($product, $variant);

        if ($change === 0) {
            return null;
        }

        return DB::transaction(function () use ($product, $variant, $change, $type, $note, $order) {
            $current = $this->lockedStock($product, $variant);
            $after = $current + $change;

            if ($after < 0) {
                throw new InsufficientStockException($this->label($product, $variant), $current);
            }

            $target = $variant ?? $product;
            $target->newQuery()->whereKey($target->getKey())->update(['stock' => $after]);
            $target->forceFill(['stock' => $after])->syncOriginalAttribute('stock');

            if ($variant) {
                $product->syncVariantSummary();
            }

            return StockMovement::create([
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'variant_name' => $variant?->name,
                'order_id' => $order?->id,
                'user_id' => auth()->id(),
                'type' => $type,
                'quantity' => $change,
                'stock_after' => $after,
                'note' => $note,
            ]);
        });
    }

    /** Set stok ke angka tertentu (mis. hasil stock opname). */
    public function set(
        Product $product,
        ?ProductVariant $variant,
        int $stock,
        StockMovementType $type = StockMovementType::Adjustment,
        ?string $note = null,
    ): ?StockMovement {
        $this->guard($product, $variant);

        return DB::transaction(fn () => $this->adjust(
            $product, $variant, max(0, $stock) - $this->lockedStock($product, $variant), $type, $note,
        ));
    }

    private function lockedStock(Product $product, ?ProductVariant $variant): int
    {
        $target = $variant ?? $product;

        return (int) $target->newQuery()->whereKey($target->getKey())->lockForUpdate()->value('stock');
    }

    private function guard(Product $product, ?ProductVariant $variant): void
    {
        if ($variant && (int) $variant->product_id !== (int) $product->id) {
            throw new InvalidArgumentException('Varian bukan milik produk ini.');
        }

        if (! $variant && $product->has_variants) {
            throw new InvalidArgumentException('Produk ini memakai varian; ubah stok per varian.');
        }
    }

    private function label(Product $product, ?ProductVariant $variant): string
    {
        return $variant ? "{$product->name} ({$variant->name})" : $product->name;
    }
}
