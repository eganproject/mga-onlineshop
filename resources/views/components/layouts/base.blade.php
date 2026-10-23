@props([
    'title' => null,
    'bodyClass' => 'bg-cream text-stone-800',
    'description' => null,
    'image' => null,
    'canonical' => null,
    'type' => 'website',
    'noindex' => false,
])
@php
    $pageTitle = ($title ? $title.' · ' : '').config('shop.name').' - '.config('shop.full_name');
    $metaDescription = \Illuminate\Support\Str::limit($description ?: config('shop.seo.description'), 160);
    $canonicalUrl = $canonical ?: \App\Support\Seo::canonical(request());
    $index = ! $noindex && \App\Support\Seo::shouldIndex(request());
    $ogImage = $image ?: (config('shop.seo.default_image') ? asset(config('shop.seo.default_image')) : null);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#ea580c">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <meta name="robots" content="{{ $index ? 'index, follow, max-image-preview:large' : 'noindex, follow' }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">

    {{-- Open Graph & Twitter --}}
    <meta property="og:site_name" content="{{ config('shop.full_name') }}">
    <meta property="og:locale" content="id_ID">
    <meta property="og:type" content="{{ $type }}">
    <meta property="og:title" content="{{ $title ?: config('shop.full_name') }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    @if ($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
        <meta property="og:image:alt" content="{{ $title ?: config('shop.full_name') }}">
    @endif
    <meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $title ?: config('shop.full_name') }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    @if ($ogImage)
        <meta name="twitter:image" content="{{ $ogImage }}">
    @endif

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body {{ $attributes->merge(['class' => 'min-h-full antialiased '.$bodyClass]) }}>
    {{ $slot }}
    <x-flash />
    {{ $scripts ?? '' }}
</body>
</html>
