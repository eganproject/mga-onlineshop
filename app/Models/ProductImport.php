<?php

namespace App\Models;

use App\Enums\ImportStatus;
use App\Enums\ImportType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Satu kali unggah file impor. Semua kolom diisi oleh controller/job
 * (tidak ada input pengguna yang di-mass-assign), jadi pakai forceFill.
 */
class ProductImport extends Model
{
    use Prunable;

    /** Disk penyimpanan file impor (privat, tidak bisa diakses publik). */
    public const DISK = 'local';

    protected function casts(): array
    {
        return [
            'type' => ImportType::class,
            'status' => ImportStatus::class,
            'total_rows' => 'integer',
            'error_rows' => 'integer',
            'summary' => 'array',
            'preview' => 'array',
            'errors' => 'array',
            'result' => 'array',
            'validated_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(fn (ProductImport $import) => $import->deleteFile());
    }

    /** Riwayat impor (beserta filenya) dibersihkan otomatis setelah 30 hari oleh `model:prune`. */
    public function prunable()
    {
        return static::where('created_at', '<', now()->subDays(30));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function filePath(): ?string
    {
        return $this->path ? Storage::disk(self::DISK)->path($this->path) : null;
    }

    public function deleteFile(): void
    {
        if ($this->path) {
            Storage::disk(self::DISK)->delete($this->path);
        }
    }

    public function canCommit(): bool
    {
        return $this->status === ImportStatus::Ready && $this->validRows() > 0;
    }

    public function validRows(): int
    {
        return max(0, $this->total_rows - $this->error_rows);
    }

    /** Impor yang tertahan di antrean lebih dari sejam dianggap macet. */
    public function isStale(): bool
    {
        return $this->status->isRunning() && $this->updated_at?->lt(now()->subHour());
    }
}
