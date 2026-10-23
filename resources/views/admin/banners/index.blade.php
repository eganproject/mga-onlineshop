<x-layouts.admin title="Banner">
    <x-admin.page-header title="Banner promo" description="Slide promo di beranda toko. Urutan kecil tampil lebih dulu.">
        <a href="{{ route('admin.banners.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Tambah banner</a>
    </x-admin.page-header>

    @php $filtered = request()->filled('q') || $state; @endphp
    @if ($banners->isNotEmpty() || $filtered)
        <form method="GET" class="card mb-4 flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
            <div class="relative max-w-xs flex-1">
                <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-stone-400" />
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari judul banner..." class="input pl-10" aria-label="Cari banner">
            </div>
            <div class="no-scrollbar -mx-1 flex gap-1.5 overflow-x-auto px-1">
                <a href="{{ route('admin.banners.index', array_filter(['q' => request('q')])) }}" @class(['shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold', 'bg-stone-900 text-white' => ! $state, 'bg-stone-100 text-stone-600 hover:bg-stone-200' => $state])>Semua</a>
                @foreach (\App\Http\Controllers\Admin\BannerController::STATES as $key => $label)
                    <a href="{{ route('admin.banners.index', array_filter(['q' => request('q'), 'status' => $key])) }}" @class(['shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold', 'bg-stone-900 text-white' => $state === $key, 'bg-stone-100 text-stone-600 hover:bg-stone-200' => $state !== $key])>{{ $label }}</a>
                @endforeach
            </div>
            @if ($state)<input type="hidden" name="status" value="{{ $state }}">@endif
        </form>
    @endif

    @if ($banners->isEmpty())
        <div class="card">
            @if ($filtered)
                <x-empty-state icon="megaphone" title="Banner tidak ditemukan" description="Coba kata kunci lain atau pilih status lain.">
                    <a href="{{ route('admin.banners.index') }}" class="btn btn-secondary">Hapus filter</a>
                </x-empty-state>
            @else
                <x-empty-state icon="megaphone" title="Belum ada banner" description="Unggah banner untuk promo, koleksi baru, atau kode voucher.">
                    <a href="{{ route('admin.banners.create') }}" class="btn btn-primary">Tambah banner</a>
                </x-empty-state>
            @endif
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($banners as $banner)
                @php
                    [$label, $class] = match ($banner->state()) {
                        'running' => ['Tayang', 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'],
                        'scheduled' => ['Terjadwal', 'bg-sky-50 text-sky-700 ring-sky-600/20'],
                        'ended' => ['Berakhir', 'bg-stone-100 text-stone-600 ring-stone-500/20'],
                        default => ['Disembunyikan', 'bg-stone-100 text-stone-600 ring-stone-500/20'],
                    };
                @endphp
                <div class="card overflow-hidden">
                    <div class="relative aspect-[21/9] bg-stone-100">
                        <img src="{{ $banner->imageUrl() }}" alt="" loading="lazy" class="size-full object-cover">
                        <span class="absolute left-3 top-3 rounded-full bg-white/90 px-2 py-0.5 text-[11px] font-bold text-stone-700">#{{ $banner->sort_order }}</span>
                    </div>
                    <div class="space-y-3 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-stone-900">{{ $banner->title }}</p>
                                <p class="truncate text-xs text-stone-500">{{ $banner->link_url ?: 'Tanpa link' }}</p>
                            </div>
                            <form method="POST" action="{{ route('admin.banners.toggle', $banner) }}" class="shrink-0">
                                @csrf
                                @method('PATCH')
                                <button title="Klik untuk {{ $banner->is_active ? 'sembunyikan' : 'tampilkan' }}"><span class="badge {{ $class }}">{{ $label }}</span></button>
                            </form>
                        </div>
                        <p class="text-xs text-stone-500">
                            <x-icon name="calendar" class="inline size-3.5" />
                            {{ $banner->starts_at?->translatedFormat('j M Y') ?? 'Sekarang' }} – {{ $banner->ends_at?->translatedFormat('j M Y') ?? 'tanpa batas' }}
                            @if ($banner->mobile_image_path) · Gambar HP ✓ @endif
                        </p>
                        <div class="flex gap-1 border-t border-stone-100 pt-3">
                            <a href="{{ route('admin.banners.edit', $banner) }}" class="btn btn-ghost btn-sm"><x-icon name="pencil" class="size-4" /> Edit</a>
                            <x-confirm-delete :action="route('admin.banners.destroy', $banner)" title="Hapus banner?" message="Banner “{{ $banner->title }}” dan gambarnya akan dihapus permanen." class="ml-auto" />
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <x-admin.pagination :paginator="$banners" label="banner" class="card mt-4 !border-t-0" />
    @endif
</x-layouts.admin>
