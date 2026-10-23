{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
    <url>
        <loc>{{ route('home') }}</loc>
        @if ($lastModified)<lastmod>{{ $lastModified->toAtomString() }}</lastmod>@endif
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc>{{ route('products.index') }}</loc>
        @if ($lastModified)<lastmod>{{ $lastModified->toAtomString() }}</lastmod>@endif
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
@foreach ($categories as $category)
    <url>
        <loc>{{ route('products.index', ['kategori' => $category->slug]) }}</loc>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>
@endforeach
@foreach ($products as $product)
    <url>
        <loc>{{ route('products.show', $product) }}</loc>
        <lastmod>{{ $product->updated_at->toAtomString() }}</lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
@foreach ($product->images->take(5) as $image)
        <image:image><image:loc>{{ $image->url() }}</image:loc><image:title>{{ $product->name }}</image:title></image:image>
@endforeach
    </url>
@endforeach
</urlset>
