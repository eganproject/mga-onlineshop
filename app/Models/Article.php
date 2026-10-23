<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable([
    'article_category_id', 'title', 'slug', 'excerpt', 'body', 'cover_image_path', 'tags',
    'status', 'published_at', 'meta_title', 'meta_description', 'reading_minutes',
])]
class Article extends Model
{
    use HasFactory;

    /** Folder gambar di dalam isi artikel (disk public). */
    public const CONTENT_IMAGE_DIR = 'articles/content';

    protected function casts(): array
    {
        return [
            'status' => ArticleStatus::class,
            'published_at' => 'datetime',
            'tags' => 'array',
            'reading_minutes' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ArticleCategory::class, 'article_category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot('sort_order')->orderByPivot('sort_order');
    }

    /** Artikel yang sudah tayang untuk publik (terbit & jadwalnya sudah lewat). */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', ArticleStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        if ($term === null || $term === '') {
            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';
        $query->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('excerpt', 'like', $like));
    }

    #[Scope]
    protected function withTag(Builder $query, ?string $tag): void
    {
        if ($tag) {
            $query->whereJsonContains('tags', $tag);
        }
    }

    public function isVisible(): bool
    {
        return $this->state() === 'published';
    }

    /** draft | scheduled | published */
    public function state(): string
    {
        return match (true) {
            $this->status !== ArticleStatus::Published || ! $this->published_at => 'draft',
            $this->published_at->isFuture() => 'scheduled',
            default => 'published',
        };
    }

    public function stateLabel(): string
    {
        return ['draft' => 'Draf', 'scheduled' => 'Terjadwal', 'published' => 'Terbit'][$this->state()];
    }

    public function stateBadgeClass(): string
    {
        return match ($this->state()) {
            'published' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            'scheduled' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
            default => 'bg-stone-100 text-stone-600 ring-stone-500/20',
        };
    }

    public function coverUrl(): ?string
    {
        return $this->cover_image_path ? Storage::disk('public')->url($this->cover_image_path) : null;
    }

    public function seoTitle(): string
    {
        return $this->meta_title ?: $this->title;
    }

    public function metaDescription(): string
    {
        return $this->meta_description ?: ($this->excerpt ?: $this->summary(160));
    }

    /** Ringkasan teks polos dari isi artikel. */
    public function summary(int $limit = 180): string
    {
        $text = $this->excerpt ?: html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '</li>', '</h2>', '</h3>'], ' ', $this->body ?? '')), ENT_QUOTES | ENT_HTML5);

        return Str::limit(trim(preg_replace('/\s+/u', ' ', $text)), $limit);
    }

    /** @return list<string> */
    public function tagList(): array
    {
        return array_values(array_filter((array) $this->tags));
    }

    /**
     * Path gambar isi artikel (disk public) yang dirujuk di body, supaya ikut
     * dihapus bila artikel dihapus.
     *
     * @return list<string>
     */
    public function contentImagePaths(): array
    {
        $base = preg_quote(rtrim(Storage::disk('public')->url(self::CONTENT_IMAGE_DIR), '/'), '#');
        $path = preg_quote(parse_url(Storage::disk('public')->url(self::CONTENT_IMAGE_DIR), PHP_URL_PATH) ?? '', '#');

        preg_match_all('#(?:'.$base.'|'.$path.')/([A-Za-z0-9._-]+)#', $this->body ?? '', $matches);

        return array_values(array_unique(array_map(fn ($file) => self::CONTENT_IMAGE_DIR.'/'.$file, $matches[1])));
    }

    public static function readingMinutes(string $html): int
    {
        $words = str_word_count(strip_tags($html));

        return max(1, (int) ceil($words / 200));
    }
}
