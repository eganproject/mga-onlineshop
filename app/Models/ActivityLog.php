<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Jejak aksi staf di panel admin. Hanya ditulis lewat ActivityLogger. */
class ActivityLog extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** Log lama dibersihkan oleh `model:prune` (jadwal harian). */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(max(30, (int) config('shop.activity_log_days', 365))));
    }

    /** Grup aksi untuk filter & warna, diambil dari awalan nama aksi ("orders.status" → "orders"). */
    public function group(): string
    {
        return strtok($this->action, '.') ?: $this->action;
    }
}
