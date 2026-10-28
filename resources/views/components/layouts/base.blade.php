@props([
    'title' => null,
    'seoTitle' => null,
    'bodyClass' => 'bg-cream text-stone-800',
    'description' => null,
    'image' => null,
    'canonical' => null,
    'type' => 'website',
    'noindex' => false,
    'viewportFit' => false,
])
@php
    // seoTitle = isi <title> utuh (mis. beranda); selain itu "Judul · MGA - Nama toko".
    $pageTitle = $seoTitle ?: ($title ? $title.' · ' : '').config('shop.name').' - '.config('shop.full_name');
    $shareTitle = $seoTitle ?: ($title ?: config('shop.full_name'));
    $metaDescription = \Illuminate\Support\Str::limit($description ?: config('shop.seo.description'), 160);
    $canonicalUrl = $canonical ?: \App\Support\Seo::canonical(request());
    $index = ! $noindex && \App\Support\Seo::shouldIndex(request());
    $ogImage = $image ?: \App\Support\Storefront::url(config('shop.seo.default_image'));
    $isAdminPage = request()->routeIs('admin.*');
    $analyticsId = $isAdminPage ? null : \App\Support\Seo::analyticsId();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1{{ $viewportFit ? ', viewport-fit=cover' : '' }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#21939f">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <meta name="robots" content="{{ $index ? 'index, follow, max-image-preview:large' : 'noindex, follow' }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">

    {{-- Open Graph & Twitter --}}
    <meta property="og:site_name" content="{{ config('shop.full_name') }}">
    <meta property="og:locale" content="id_ID">
    <meta property="og:type" content="{{ $type }}">
    <meta property="og:title" content="{{ $shareTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    @if ($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
        <meta property="og:image:alt" content="{{ $shareTitle }}">
    @endif
    <meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $shareTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    @if ($ogImage)
        <meta name="twitter:image" content="{{ $ogImage }}">
    @endif

    @unless ($isAdminPage)
        @if ($code = config('shop.seo.google_verification'))<meta name="google-site-verification" content="{{ $code }}">@endif
        @if ($code = config('shop.seo.bing_verification'))<meta name="msvalidate.01" content="{{ $code }}">@endif
    @endunless

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
    @if ($analyticsId)
        {{-- Google Analytics 4 (Admin › SEO). Tidak dimuat di panel admin. --}}
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $analyticsId }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', @js($analyticsId));
        </script>
    @endif
</head>
<body {{ $attributes->merge(['class' => 'min-h-full antialiased '.$bodyClass]) }}>
    {{ $slot }}
    <x-flash />
    {{ $scripts ?? '' }}
</body>
</html>
