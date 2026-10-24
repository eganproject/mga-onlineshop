{{-- Pagination toko (pengganti tampilan bawaan Laravel). Admin memakai <x-admin.pagination>. --}}
@if ($paginator->hasPages())
    @php($base = 'inline-grid size-10 place-items-center rounded-full text-sm font-semibold transition')
    <nav role="navigation" aria-label="Navigasi halaman" class="flex flex-col items-center gap-4">
        <div class="flex items-center gap-1 sm:gap-1.5">
            @if ($paginator->onFirstPage())
                <span class="{{ $base }} text-stone-300" aria-disabled="true"><x-icon name="chevron-left" class="size-4" /><span class="sr-only">Sebelumnya</span></span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $base }} text-ink ring-1 ring-stone-300 ring-inset hover:ring-ink"><x-icon name="chevron-left" class="size-4" /><span class="sr-only">Sebelumnya</span></a>
            @endif

            {{-- HP: cukup "2 / 5" --}}
            <span class="px-3 text-sm font-semibold text-stone-600 sm:hidden">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

            <div class="hidden items-center gap-1 sm:flex">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="{{ $base }} text-stone-400" aria-hidden="true">…</span>
                    @endif
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="{{ $base }} bg-ink text-white" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="{{ $base }} text-stone-600 hover:bg-sand-100 hover:text-ink" aria-label="Halaman {{ $page }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $base }} text-ink ring-1 ring-stone-300 ring-inset hover:ring-ink"><x-icon name="chevron-right" class="size-4" /><span class="sr-only">Berikutnya</span></a>
            @else
                <span class="{{ $base }} text-stone-300" aria-disabled="true"><x-icon name="chevron-right" class="size-4" /><span class="sr-only">Berikutnya</span></span>
            @endif
        </div>
        @if ($paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
            <p class="text-xs text-stone-500">
                Menampilkan {{ number_format($paginator->firstItem(), 0, ',', '.') }}–{{ number_format($paginator->lastItem(), 0, ',', '.') }} dari {{ number_format($paginator->total(), 0, ',', '.') }}
            </p>
        @endif
    </nav>
@endif
