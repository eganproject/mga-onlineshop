<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu kali tarikan stok dari warehouse29. */
#[Fillable(['mode', 'status', 'user_id', 'fetched', 'updated', 'unmatched', 'server_time', 'message', 'started_at', 'finished_at'])]
class WarehouseSyncRun extends Model
{
    public const MODE_INCREMENTAL = 'incremental';

    public const MODE_FULL = 'full';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'fetched' => 'integer',
            'updated' => 'integer',
            'unmatched' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function modeLabel(): string
    {
        return $this->mode === self::MODE_FULL ? 'Penuh' : 'Perubahan';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_SUCCESS => 'Berhasil',
            self::STATUS_FAILED => 'Gagal',
            default => 'Berjalan',
        };
    }

    public function statusClass(): string
    {
        return match ($this->status) {
            self::STATUS_SUCCESS => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            self::STATUS_FAILED => 'bg-rose-50 text-rose-700 ring-rose-600/20',
            default => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        };
    }
}
