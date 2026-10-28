{{--
    Isian SEO (judul & deskripsi di hasil Google) + pratinjau hasil pencarian.
    <x-admin.seo-fields :model="$product" url="/produk/..." title-fallback="Nama produk" description-fallback="Awal deskripsi" />
    Kolom model: meta_title (maks 70), meta_description (maks 160).
--}}
@props([
    'model',
    'url',
    'titleFallback' => null,
    'descriptionFallback' => null,
    'titleTips' => [],
    'descriptionTips' => [],
])
@php
    $suffix = ' · '.config('shop.name').' - '.config('shop.full_name');
    $state = [
        'metaTitle' => (string) old('meta_title', $model->meta_title),
        'metaDescription' => (string) old('meta_description', $model->meta_description),
    ];
@endphp
<div {{ $attributes->merge(['class' => 'card space-y-5 p-6']) }} x-data="@js($state)">
    <div>
        <h2 class="font-bold text-stone-900">SEO</h2>
        <p class="text-xs text-stone-500">Opsional. Kosongkan untuk memakai nama & deskripsi di atas.</p>
    </div>
    <div class="rounded-xl border border-stone-200 bg-white p-4" aria-hidden="true">
        <p class="truncate text-xs text-stone-500">{{ str_replace(['https://', 'http://'], '', $url) }}</p>
        <p class="mt-1 truncate text-lg leading-snug text-[#1a0dab]" x-text="(metaTitle || @js($titleFallback ?: 'Judul halaman')) + @js($suffix)"></p>
        <p class="mt-1 line-clamp-2 text-sm text-stone-600" x-text="metaDescription || @js($descriptionFallback ?: 'Deskripsi halaman akan tampil di sini.')"></p>
    </div>
    <x-form.input name="meta_title" label="Judul SEO" optional maxlength="70" x-model="metaTitle"
        hint="Idealnya 40–60 karakter. Nama toko ditambahkan otomatis."
        tooltip="Judul biru yang tampil di hasil pencarian Google (lihat pratinjau di atas)."
        :tooltip-tips="$titleTips" />
    <x-form.textarea name="meta_description" label="Deskripsi SEO" optional maxlength="160" rows="2" x-model="metaDescription"
        hint="Idealnya 120–160 karakter."
        tooltip="Teks abu-abu di bawah judul pada hasil Google, juga dipakai saat link dibagikan di WhatsApp/Facebook."
        :tooltip-tips="$descriptionTips" />
</div>
