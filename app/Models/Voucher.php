<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\VoucherType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'name', 'description', 'type', 'value', 'max_discount', 'min_spend',
    'quota', 'per_user_limit', 'starts_at', 'ends_at', 'is_active', 'is_public',
])]
class Voucher extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => VoucherType::class,
            'value' => 'integer',
            'max_discount' => 'integer',
            'min_spend' => 'integer',
            'quota' => 'integer',
            'per_user_limit' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    public static function normalizeCode(?string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $code));
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Pesanan yang memakai kuota voucher (pesanan batal mengembalikan kuota). */
    public function redemptions(): HasMany
    {
        return $this->orders()->where('status', '!=', OrderStatus::Cancelled);
    }

    /** Aktif dan sedang dalam masa berlaku. */
    #[Scope]
    protected function running(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    /** Status untuk admin: aktif, terjadwal, berakhir, habis, nonaktif. */
    public function state(?int $used = null): string
    {
        $used ??= $this->redemptions_count ?? $this->redemptions()->count();

        return match (true) {
            ! $this->is_active => 'inactive',
            $this->ends_at?->isPast() ?? false => 'ended',
            $this->starts_at?->isFuture() ?? false => 'scheduled',
            $this->quota !== null && $used >= $this->quota => 'exhausted',
            default => 'running',
        };
    }

    public static function stateLabel(string $state): array
    {
        return match ($state) {
            'running' => ['Berjalan', 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'],
            'scheduled' => ['Terjadwal', 'bg-sky-50 text-sky-700 ring-sky-600/20'],
            'ended' => ['Berakhir', 'bg-stone-100 text-stone-600 ring-stone-500/20'],
            'exhausted' => ['Kuota habis', 'bg-amber-50 text-amber-700 ring-amber-600/20'],
            default => ['Nonaktif', 'bg-stone-100 text-stone-600 ring-stone-500/20'],
        };
    }

    /** Ringkasan manfaat, contoh: "Diskon 10% s.d. Rp25.000". */
    public function benefitLabel(): string
    {
        return match ($this->type) {
            VoucherType::Percent => "Diskon {$this->value}%".($this->max_discount ? ' s.d. '.Money::rupiah($this->max_discount) : ''),
            VoucherType::Fixed => 'Potongan '.Money::rupiah($this->value),
            VoucherType::FreeShipping => $this->value > 0 ? 'Gratis ongkir s.d. '.Money::rupiah($this->value) : 'Gratis ongkir',
        };
    }

    /** Syarat singkat untuk pelanggan. */
    public function termsLabel(): string
    {
        $terms = [$this->min_spend > 0 ? 'Min. belanja '.Money::rupiah($this->min_spend) : 'Tanpa minimum belanja'];

        if ($this->ends_at) {
            $terms[] = 's.d. '.$this->ends_at->translatedFormat('j M Y');
        }

        return implode(' · ', $terms);
    }
}
