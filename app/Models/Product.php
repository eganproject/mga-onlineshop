<?php

namespace App\Models;

use App\Services\Catalog\Wholesale;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

#[Fillable([
    'category_id', 'name', 'slug', 'sku', 'description', 'meta_title', 'meta_description', 'price', 'compare_at_price',
    'weight', 'variant_label', 'is_active', 'is_featured', 'wholesale_tiers', 'min_order_qty',
])]
class Product extends Model
{
    use HasFactory;

    // `wholesale_min_qty` adalah ringkasan (syncWholesaleSummary), tidak mass-assignable.
    // `stock`, `has_variants`, `rating_avg`, `review_count`, `sold_count` sengaja tidak mass-assignable. Stok diubah lewat
    // StockService (tercatat di riwayat), ringkasan varian lewat syncVariantSummary(),
    // ringkasan rating lewat ReviewService::syncSummary(), jumlah terjual lewat SoldCountService.

    /** Foto sampul selalu ikut dimuat karena dipakai di hampir semua tampilan produk. */
    protected $with = ['coverImage'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'compare_at_price' => 'integer',
            'wholesale_tiers' => 'array',
            'wholesale_min_qty' => 'integer',
            'min_order_qty' => 'integer',
            'stock' => 'integer',
            'weight' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'has_variants' => 'boolean',
            'rating_avg' => 'float',
            'review_count' => 'integer',
            'sold_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Tingkat grosir selalu dirapikan; ringkasan produk tanpa varian dihitung di sini,
        // produk bervarian lewat syncVariantSummary().
        static::saving(function (Product $product) {
            if ($product->has_variants) {
                $product->wholesale_tiers = null;

                return;
            }

            $tiers = Wholesale::normalize($product->wholesale_tiers, $product->price);
            $product->wholesale_tiers = $tiers ?: null;
            $product->wholesale_min_qty = $tiers[0]['min_qty'] ?? null;
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Halaman detail produk nonaktif hanya bisa dibuka staf (pratinjau dari admin).
     * Pelanggan & tamu mendapat 404, jadi jangan tampilkan link ke produk seperti itu.
     */
    public function isViewableBy(?User $user): bool
    {
        return $this->is_active || (bool) $user?->isAdmin();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function coverImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->ofMany(['sort_order' => 'min', 'id' => 'min']);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function activeVariants(): HasMany
    {
        return $this->variants()->where('is_active', true);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function wholesale(Builder $query): void
    {
        $query->whereNotNull('wholesale_min_qty');
    }

    #[Scope]
    protected function onSale(Builder $query): void
    {
        $query->whereNotNull('compare_at_price')->whereColumn('compare_at_price', '>', 'price');
    }

    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when($term, fn (Builder $q) => $q->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhereHas('variants', fn (Builder $v) => $v->where('sku', 'like', "%{$term}%"));
        }));
    }

    public function imageUrl(): ?string
    {
        return $this->coverImage?->url();
    }

    /**
     * Samakan harga & stok produk dengan varian aktifnya: harga = varian termurah,
     * stok = total stok varian. Dipanggil setiap kali varian atau stoknya berubah.
     */
    public function syncVariantSummary(): void
    {
        $variants = $this->variants()->get();
        $active = $variants->where('is_active', true);

        $this->has_variants = $variants->isNotEmpty();

        if ($this->has_variants) {
            $this->stock = (int) $active->sum('stock');

            if ($cheapest = $active->sortBy('price')->first()) {
                $this->price = $cheapest->price;
                $this->compare_at_price = $cheapest->compare_at_price;
            }
        }

        $this->wholesale_min_qty = $this->computeWholesaleMinQty($active);
        $this->save();
        $this->unsetRelation('variants');
    }

    /**
     * Jumlah terkecil yang mendapat harga grosir (null = tanpa grosir), untuk badge & filter katalog.
     *
     * @param  Collection<int, ProductVariant>|null  $activeVariants
     */
    public function computeWholesaleMinQty($activeVariants = null): ?int
    {
        if ($this->has_variants) {
            $activeVariants ??= $this->variants()->where('is_active', true)->get();
            $min = $activeVariants
                ->map(fn (ProductVariant $v) => Wholesale::tiers($this, $v)[0]['min_qty'] ?? null)
                ->filter()->min();

            return $min ? (int) $min : null;
        }

        return Wholesale::tiers($this)[0]['min_qty'] ?? null;
    }

    public function hasWholesale(): bool
    {
        return $this->wholesale_min_qty !== null;
    }

    /** Minimal pembelian (gabungan semua varian), minimal 1. */
    public function minOrderQty(): int
    {
        return max(1, (int) $this->min_order_qty);
    }

    public function formattedPrice(): string
    {
        return Money::rupiah($this->price);
    }

    public function isOnSale(): bool
    {
        return $this->compare_at_price && $this->compare_at_price > $this->price;
    }

    public function discountPercent(): int
    {
        return $this->isOnSale()
            ? (int) round(100 - ($this->price / $this->compare_at_price * 100))
            : 0;
    }

    public function inStock(): bool
    {
        return $this->stock > 0;
    }

    public function hasReviews(): bool
    {
        return $this->review_count > 0;
    }

    /** Jumlah terjual ringkas gaya marketplace: 87, 1,2rb, 10rb+. */
    public function soldLabel(): string
    {
        $sold = (int) $this->sold_count;

        return match (true) {
            $sold >= 10000 => floor($sold / 10000) * 10 .'rb+',
            $sold >= 1000 => rtrim(rtrim(number_format(floor($sold / 100) / 10, 1, ',', '.'), '0'), ',').'rb',
            default => (string) $sold,
        };
    }

    /** Judul di hasil pencarian Google (diisi admin, atau nama produk). */
    public function seoTitle(): string
    {
        return $this->meta_title ?: $this->name;
    }

    /** Ringkasan singkat untuk meta description / Open Graph. */
    public function metaDescription(): string
    {
        if (filled($this->meta_description)) {
            return $this->meta_description;
        }

        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $this->description)));

        return Str::limit($text ?: $this->name.' original di '.config('shop.full_name').'. '.config('shop.tagline').'.', 155);
    }

    public function isLowStock(): bool
    {
        return $this->stock <= config('shop.low_stock_threshold');
    }
}
