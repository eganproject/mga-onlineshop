<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'title', 'subtitle', 'button_label', 'link_url', 'image_path', 'mobile_image_path',
    'show_text', 'starts_at', 'ends_at', 'is_active', 'sort_order',
])]
class Banner extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'show_text' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** Banner yang tampil di beranda saat ini. */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('sort_order')
            ->latest('id');
    }

    public function imageUrl(): string
    {
        return Storage::disk('public')->url($this->image_path);
    }

    public function mobileImageUrl(): ?string
    {
        return $this->mobile_image_path ? Storage::disk('public')->url($this->mobile_image_path) : null;
    }

    public function state(): string
    {
        return match (true) {
            ! $this->is_active => 'inactive',
            $this->ends_at?->isPast() ?? false => 'ended',
            $this->starts_at?->isFuture() ?? false => 'scheduled',
            default => 'running',
        };
    }

    /** Link internal ("/produk") atau eksternal (https://...). */
    public function href(): ?string
    {
        if (! $this->link_url) {
            return null;
        }

        return str_starts_with($this->link_url, '/') ? url($this->link_url) : $this->link_url;
    }

    public function isExternal(): bool
    {
        return (bool) $this->link_url && ! str_starts_with($this->link_url, '/');
    }
}
