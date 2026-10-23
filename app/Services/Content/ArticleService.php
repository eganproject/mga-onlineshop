<?php

namespace App\Services\Content;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Simpan & hapus artikel: slug unik, HTML disaring, sampul, dan produk terkait. */
class ArticleService
{
    public function __construct(private readonly ArticleSanitizer $sanitizer) {}

    /**
     * @param  array{title: string, slug?: ?string, body: string, status: string, published_at?: mixed, product_ids?: array, tags?: array}  $data
     */
    public function save(Article $article, array $data, ?User $author = null, ?UploadedFile $cover = null, bool $removeCover = false): Article
    {
        $body = $this->sanitizer->clean($data['body']);
        $status = ArticleStatus::from($data['status']);
        $publishedAt = $data['published_at'] ?? null;

        // Terbit tanpa tanggal = sekarang. Draf tetap menyimpan tanggal yang diisi (untuk nanti).
        if ($status === ArticleStatus::Published && ! $publishedAt) {
            $publishedAt = $article->published_at && $article->status === ArticleStatus::Published ? $article->published_at : now();
        }

        $article->fill([
            'article_category_id' => $data['article_category_id'] ?? null,
            'title' => $data['title'],
            'slug' => $this->uniqueSlug(($data['slug'] ?? null) ?: $data['title'], $article),
            'excerpt' => $data['excerpt'] ?? null,
            'body' => $body,
            'tags' => $this->normalizeTags($data['tags'] ?? []),
            'status' => $status,
            'published_at' => $publishedAt,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'reading_minutes' => Article::readingMinutes($body),
        ]);

        if (! $article->exists && $author) {
            $article->author()->associate($author);
        }

        $oldCover = null;
        if ($cover || $removeCover) {
            $oldCover = $article->cover_image_path;
            $article->cover_image_path = $cover?->store('articles', 'public');
        }

        DB::transaction(function () use ($article, $data) {
            $article->save();
            $article->products()->sync(
                collect($data['product_ids'] ?? [])->values()->mapWithKeys(fn ($id, $i) => [(int) $id => ['sort_order' => $i]])->all()
            );
        });

        if ($oldCover) {
            Storage::disk('public')->delete($oldCover);
        }

        return $article;
    }

    public function delete(Article $article): void
    {
        $files = array_filter([$article->cover_image_path, ...$article->contentImagePaths()]);
        $article->delete();
        Storage::disk('public')->delete($files);
    }

    /** Gambar dari editor (disimpan di folder isi artikel). Mengembalikan URL publiknya. */
    public function storeContentImage(UploadedFile $file): string
    {
        return Storage::disk('public')->url($file->store(Article::CONTENT_IMAGE_DIR, 'public'));
    }

    public function uniqueSlug(string $source, ?Article $ignore = null): string
    {
        $base = Str::limit(Str::slug($source), 170, '') ?: 'artikel';
        $slug = $base;

        for ($i = 2; Article::where('slug', $slug)->when($ignore?->exists, fn ($q) => $q->whereKeyNot($ignore->id))->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    /** @return list<string> */
    private function normalizeTags(array $tags): array
    {
        return collect($tags)
            ->map(fn ($tag) => Str::of((string) $tag)->squish()->lower()->limit(30, '')->value())
            ->filter()
            ->unique()
            ->take(10)
            ->values()
            ->all();
    }
}
