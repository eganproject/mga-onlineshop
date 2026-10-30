@php
    $suggestion = \App\Support\ErrorPage::searchSuggestion();
    $categories = \App\Support\ErrorPage::categories();
@endphp
<x-layouts.error code="404" title="Halaman tidak ditemukan" icon="search"
                 message="Link ini mungkin sudah lama, dipindah, atau salah ketik. Tenang, produknya kemungkinan masih ada. Coba cari di bawah ini.">
    <form action="{{ url('/produk') }}" method="GET" role="search" class="mx-auto mt-8 max-w-lg">
        <label for="error-search" class="sr-only">Cari produk</label>
        <div class="flex items-center gap-2 rounded-full bg-white p-1.5 shadow-lg shadow-ink/5 ring-1 ring-ink/10 focus-within:ring-2 focus-within:ring-brand-500">
            <x-icon name="search" class="ml-3 size-5 shrink-0 text-stone-400" />
            <input id="error-search" type="search" name="q" value="{{ $suggestion }}" placeholder="Tripod banner, frame akrilik..."
                   class="min-w-0 flex-1 border-0 bg-transparent px-1 py-2 text-base text-ink placeholder:text-stone-400 focus:ring-0 sm:text-sm">
            <button type="submit" class="btn btn-primary btn-pill shrink-0 px-5 py-2.5">Cari</button>
        </div>
    </form>

    <div class="mt-6 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center">
        <a href="{{ url('/produk') }}" class="btn btn-ink btn-pill">
            <x-icon name="bag" class="size-4" /> Lihat semua produk
        </a>
        <a href="{{ url('/') }}" class="btn btn-outline btn-pill">
            <x-icon name="home" class="size-4" /> Kembali ke beranda
        </a>
    </div>

    @if ($categories)
        <div class="mt-10">
            <p class="eyebrow text-stone-500">Atau jelajahi kategori</p>
            <div class="mt-3 flex flex-wrap justify-center gap-2">
                @foreach ($categories as $category)
                    <a href="{{ $category['url'] }}"
                       class="rounded-full bg-white px-4 py-2 text-sm font-medium text-ink ring-1 ring-ink/10 transition hover:bg-brand-50 hover:text-brand-700 hover:ring-brand-200">
                        {{ $category['name'] }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</x-layouts.error>
