<?php

namespace App\Enums;

enum ImportStatus: string
{
    case Validating = 'validating';
    case Ready = 'ready';
    case Importing = 'importing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Validating => 'Memeriksa file',
            self::Ready => 'Siap diimpor',
            self::Importing => 'Sedang diimpor',
            self::Completed => 'Selesai',
            self::Failed => 'Gagal',
        };
    }

    /** Masih diproses di antrean; halaman pratinjau memuat ulang otomatis. */
    public function isRunning(): bool
    {
        return in_array($this, [self::Validating, self::Importing], true);
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Validating, self::Importing => 'bg-sky-50 text-sky-700 ring-sky-600/20',
            self::Ready => 'bg-amber-50 text-amber-700 ring-amber-600/20',
            self::Completed => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            self::Failed => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        };
    }
}
