<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'from_status', 'status', 'note', 'user_id', 'created_at'])]
class OrderStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'from_status' => OrderStatus::class,
            'status' => OrderStatus::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Catatan tanpa perubahan status (mis. resi diperbarui). */
    public function isNoteOnly(): bool
    {
        return $this->from_status === $this->status;
    }

    /** Siapa yang mengubah: nama admin, "Pelanggan", atau "Sistem". */
    public function actorLabel(): string
    {
        if (! $this->user) {
            return 'Sistem';
        }

        return $this->user->isAdmin() ? $this->user->name : 'Pelanggan';
    }
}
