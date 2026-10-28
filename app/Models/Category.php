<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['parent_id', 'name', 'slug', 'description', 'meta_title', 'meta_description', 'image_path', 'is_active', 'sort_order'])]
class Category extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function root(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /** ID kategori ini beserta subkategorinya, untuk filter produk. */
    public function familyIds(): array
    {
        return [$this->id, ...$this->children()->pluck('id')->all()];
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    /** Judul di hasil pencarian Google (diisi admin, atau nama kategori). */
    public function seoTitle(): string
    {
        return $this->meta_title ?: $this->name;
    }

    /** Deskripsi di hasil pencarian: isian SEO, deskripsi kategori, atau kalimat bawaan. */
    public function metaDescription(): string
    {
        $text = $this->meta_description
            ?: trim(preg_replace('/\s+/', ' ', strip_tags((string) $this->description)));

        return Str::limit($text ?: 'Belanja '.$this->name.' di '.config('shop.full_name').'. Harga gudang, kirim ke seluruh Indonesia.', 160);
    }
}
