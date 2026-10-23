@props(['title' => null, 'bodyClass' => 'bg-cream text-stone-800'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#ea580c">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('shop.name') }} - {{ config('shop.full_name') }}</title>
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
