<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rating', 'title', 'body'])]
class ProductReview extends Model
{
    use HasFactory;

    // `status`, `admin_reply`, `order_id` diatur lewat ReviewService / admin, bukan input pelanggan.

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'status' => ReviewStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->where('status', ReviewStatus::Approved);
    }

    /** Nama yang tampil publik: "Budi S." */
    public function authorName(): string
    {
        $parts = preg_split('/\s+/', trim($this->user?->name ?? 'Pelanggan'));
        $first = array_shift($parts);
        $last = $parts ? ' '.mb_strtoupper(mb_substr(end($parts), 0, 1)).'.' : '';

        return $first.$last;
    }
}
