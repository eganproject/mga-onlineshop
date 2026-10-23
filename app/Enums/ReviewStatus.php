<?php

namespace App\Enums;

enum ReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu moderasi',
            self::Approved => 'Tayang',
            self::Rejected => 'Ditolak',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-50 text-amber-700 ring-amber-600/20',
            self::Approved => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            self::Rejected => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        };
    }
}
