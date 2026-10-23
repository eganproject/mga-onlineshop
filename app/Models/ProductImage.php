<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[Fillable(['product_id', 'path', 'sort_order'])]
class ProductImage extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        // Hapus file fisik ketika record dihapus lewat Eloquent, setelah transaksi sukses.
        static::deleted(fn (ProductImage $image) => DB::afterCommit(
            fn () => Storage::disk('public')->delete($image->path)
        ));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
