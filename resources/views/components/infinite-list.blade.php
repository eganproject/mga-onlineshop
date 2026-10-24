{{--
    Daftar dengan infinite scroll. Bungkus pemakaian dengan @fragment('infinite-list')
    dan balas lewat App\Support\InfiniteScroll::respond() di controller.

    <x-infinite-list :paginator="$products" label="produk" skeleton="product" items-class="grid ...">
        @foreach ($products as $product) <x-product-card :product="$product" /> @endforeach
    </x-infinite-list>

    - Saat di-scroll mendekati bawah, halaman berikutnya dimuat otomatis (setiap 4 kali
      berhenti dan menampilkan tombol "Muat lebih banyak" supaya footer tetap terjangkau).
    - :auto="false" = hanya tombol (mis. ulasan).
    - Tanpa JS: link halaman biasa (vendor/pagination/tailwind) tetap tampil.
--}}
@props(['paginator', 'label' => 'data', 'skeleton' => 'product', 'skeletonCount' => 4, 'auto' => true, 'itemsClass' => '', 'more' => 'Muat lebih banyak'])
<div {{ $attributes }} data-infinite
     data-infinite-next="{{ $paginator->nextPageUrl() }}"
     data-infinite-auto="{{ $auto ? 'true' : 'false' }}"
     data-infinite-label="{{ $label }}">
    @unless ($paginator->onFirstPage())
        <p class="mb-6 text-center text-sm">
            <a href="{{ $paginator->url(1) }}" class="link-underline font-semibold text-ink">Tampilkan dari awal</a>
        </p>
    @endunless
    <div class="{{ $itemsClass }}" data-infinite-items>{{ $slot }}</div>

    @if ($paginator->hasMorePages())
        <template data-infinite-skeleton>
            <div class="{{ $itemsClass }}" aria-hidden="true" data-infinite-skeleton-list>
                @for ($i = 0; $i < $skeletonCount; $i++)
                    @switch($skeleton)
                        @case('article')
                            <div class="skeleton-item"><div class="skeleton aspect-[16/10] rounded-lg"></div><div class="skeleton mt-4 h-3 w-1/3"></div><div class="skeleton mt-3 h-5 w-5/6"></div><div class="skeleton mt-2 h-5 w-2/3"></div></div>
                            @break
                        @case('order')
                            <div class="skeleton-item card p-5"><div class="flex items-center gap-4"><div class="skeleton size-16 shrink-0 rounded-xl"></div><div class="flex-1"><div class="skeleton h-4 w-1/2"></div><div class="skeleton mt-2 h-3 w-1/3"></div></div><div class="skeleton h-10 w-20 rounded-xl"></div></div></div>
                            @break
                        @case('review')
                            <div class="skeleton-item py-6"><div class="skeleton h-3 w-24"></div><div class="skeleton mt-3 h-4 w-2/3"></div><div class="skeleton mt-2 h-4 w-full"></div><div class="skeleton mt-2 h-4 w-5/6"></div></div>
                            @break
                        @default
                            <div class="skeleton-item"><div class="skeleton aspect-[4/5]"></div><div class="skeleton mt-3 h-4 w-5/6"></div><div class="skeleton mt-2 h-4 w-1/2"></div><div class="skeleton mt-3 h-4 w-1/3"></div></div>
                    @endswitch
                @endfor
            </div>
        </template>
    @endif

    @if ($paginator->hasPages())
        {{-- Diaktifkan JS; tanpa JS yang tampil link halaman di bawahnya. --}}
        <div class="mt-12 hidden flex-col items-center gap-4 text-center" data-infinite-status>
            <div class="w-full max-w-56">
                <p class="text-sm text-stone-500" aria-live="polite" data-infinite-count>
                    Menampilkan <span class="font-semibold text-ink" data-infinite-shown>{{ number_format($paginator->lastItem() ?? 0, 0, ',', '.') }}</span>
                    dari {{ number_format($paginator->total(), 0, ',', '.') }} {{ $label }}
                </p>
                <div class="mt-3 h-1 overflow-hidden rounded-full bg-sand-100">
                    <div class="h-full rounded-full bg-ink transition-[width] duration-500" data-infinite-progress
                         data-total="{{ $paginator->total() }}"
                         style="width: {{ $paginator->total() ? round(($paginator->lastItem() ?? 0) / $paginator->total() * 100) : 100 }}%"></div>
                </div>
            </div>
            <button type="button" class="btn btn-outline btn-pill hidden min-w-52 aria-busy:opacity-60" data-infinite-more>{{ $more }}</button>
            <p class="hidden items-center gap-2 text-sm text-stone-500" data-infinite-loading>
                <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".2" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                Memuat {{ $label }} lainnya…
            </p>
            <div class="h-px w-full" data-infinite-sentinel></div>
        </div>
        <div class="mt-12" data-infinite-pager>{{ $paginator->links() }}</div>
    @endif
</div>
