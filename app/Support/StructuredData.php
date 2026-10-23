<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Collection;

/** Data terstruktur schema.org (JSON-LD) untuk rich result di mesin pencari. */
class StructuredData
{
    public static function product(Product $product, Collection $reviews): array
    {
        $variants = $product->relationLoaded('activeVariants') ? $product->activeVariants : collect();
        $url = route('products.show', $product);
        $availability = $product->inStock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';

        $offers = $variants->count() > 1
            ? [
                '@type' => 'AggregateOffer',
                'priceCurrency' => 'IDR',
                'lowPrice' => $variants->min('price'),
                'highPrice' => $variants->max('price'),
                'offerCount' => $variants->count(),
                'availability' => $availability,
                'url' => $url,
            ]
            : [
                '@type' => 'Offer',
                'priceCurrency' => 'IDR',
                'price' => $product->price,
                'availability' => $availability,
                'itemCondition' => 'https://schema.org/NewCondition',
                'url' => $url,
                'seller' => ['@type' => 'Organization', 'name' => config('shop.full_name')],
            ];

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'description' => $product->metaDescription(),
            'url' => $url,
            'image' => $product->images->map->url()->values()->all() ?: null,
            'sku' => $product->sku,
            'category' => $product->category?->name,
            'offers' => $offers,
        ];

        if ($product->review_count > 0) {
            $data['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $product->rating_avg,
                'reviewCount' => $product->review_count,
                'bestRating' => 5,
                'worstRating' => 1,
            ];
            $data['review'] = $reviews->take(5)->map(fn ($review) => [
                '@type' => 'Review',
                'author' => ['@type' => 'Person', 'name' => $review->authorName()],
                'datePublished' => ($review->approved_at ?? $review->created_at)->toDateString(),
                'reviewRating' => ['@type' => 'Rating', 'ratingValue' => $review->rating, 'bestRating' => 5],
                'name' => $review->title,
                'reviewBody' => $review->body,
            ])->values()->all();
        }

        return self::clean($data);
    }

    /** @param  array<int, array{0: string, 1: string}>  $items  [nama, url] */
    public static function breadcrumbs(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(fn ($item, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item[0],
                'item' => $item[1],
            ])->all(),
        ];
    }

    public static function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                self::clean([
                    '@type' => 'Organization',
                    '@id' => route('home').'#organization',
                    'name' => config('shop.full_name'),
                    'alternateName' => config('shop.name'),
                    'url' => route('home'),
                    'logo' => asset('images/brand/icon-512.png'),
                    'sameAs' => array_values(array_filter([
                        config('shop.instagram') ? 'https://instagram.com/'.ltrim(config('shop.instagram'), '@') : null,
                        config('shop.tiktok') ? 'https://www.tiktok.com/@'.ltrim(config('shop.tiktok'), '@') : null,
                        config('shop.facebook') ?: null,
                    ])) ?: null,
                ]),
                [
                    '@type' => 'WebSite',
                    'name' => config('shop.full_name'),
                    'url' => route('home'),
                    'publisher' => ['@id' => route('home').'#organization'],
                    'inLanguage' => 'id-ID',
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => ['@type' => 'EntryPoint', 'urlTemplate' => route('products.index').'?q={search_term_string}'],
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
            ],
        ];
    }

    /** Render sebagai tag <script> yang aman disisipkan di HTML. */
    public static function script(array ...$blocks): string
    {
        return collect($blocks)
            ->map(fn ($data) => '<script type="application/ld+json">'
                .json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP)
                .'</script>')
            ->implode("\n");
    }

    private static function clean(array $data): array
    {
        return array_filter($data, fn ($value) => $value !== null && $value !== '' && $value !== []);
    }
}
