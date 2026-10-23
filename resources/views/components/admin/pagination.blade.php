{{--
    Navigasi halaman untuk semua daftar admin: info "Menampilkan 1–15 dari 120",
    pilihan baris per halaman, dan nomor halaman. Filter & pencarian di URL
    ikut terbawa (paginator wajib ->withQueryString()).
--}}
@props(['paginator', 'perPage' => true, 'label' => 'data'])
@php
    use App\Support\AdminList;
    use Illuminate\Pagination\UrlWindow;

    $total = $paginator->total();
    $pageName = $paginator->getPageName();
    $window = $paginator->hasPages() ? UrlWindow::make($paginator) : ['first' => null, 'slider' => null, 'last' => null];
    $elements = array_filter([$window['first'], is_array($window['slider']) ? '...' : null, $window['slider'], is_array($window['last']) ? '...' : null, $window['last']]);

    // Query string lain (filter, pencarian, urutan) sebagai input tersembunyi form "per halaman".
    $hidden = [];
    $flatten = function (array $values, string $prefix = '') use (&$flatten, &$hidden) {
        foreach ($values as $key => $value) {
            $name = $prefix === '' ? $key : "{$prefix}[{$key}]";
            is_array($value) ? $flatten($value, $name) : $hidden[$name] = $value;
        }
    };
    $flatten(request()->except([$pageName, 'per_page']));
@endphp
@if ($total > 0)
    <div {{ $attributes->merge(['class' => 'flex flex-col gap-3 border-t border-stone-100 p-4 sm:flex-row sm:items-center sm:justify-between']) }}>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-stone-500">
            <p>
                Menampilkan <span class="font-semibold text-stone-800">{{ number_format($paginator->firstItem(), 0, ',', '.') }}–{{ number_format($paginator->lastItem(), 0, ',', '.') }}</span>
                dari <span class="font-semibold text-stone-800">{{ number_format($total, 0, ',', '.') }}</span> {{ $label }}
            </p>
            @if ($perPage && $total > min(AdminList::PER_PAGE_OPTIONS))
                <form method="GET" class="flex items-center gap-2" x-data>
                    @foreach ($hidden as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
                    <label for="per-page-{{ $pageName }}" class="whitespace-nowrap">Baris per halaman</label>
                    <select id="per-page-{{ $pageName }}" name="per_page" @change="$el.form.submit()"
                            class="rounded-lg border-stone-200 bg-white py-1 pl-2.5 pr-8 text-sm font-semibold text-stone-700 focus:border-brand-500 focus:ring-brand-500/20">
                        @foreach (AdminList::PER_PAGE_OPTIONS as $option)
                            <option value="{{ $option }}" @selected($paginator->perPage() === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                    <noscript><button class="btn btn-secondary btn-sm">Terapkan</button></noscript>
                </form>
            @endif
        </div>

        @if ($paginator->hasPages())
            <nav role="navigation" aria-label="Navigasi halaman" class="flex items-center gap-1">
                @php($base = 'inline-flex h-9 min-w-9 items-center justify-center rounded-lg px-2.5 text-sm font-semibold transition')
                @if ($paginator->onFirstPage())
                    <span class="{{ $base }} cursor-not-allowed text-stone-300" aria-disabled="true"><x-icon name="chevron-left" class="size-4" /><span class="sr-only">Sebelumnya</span></span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $base }} text-stone-600 hover:bg-stone-100"><x-icon name="chevron-left" class="size-4" /><span class="sr-only">Sebelumnya</span></a>
                @endif

                {{-- HP: cukup "2 / 15" --}}
                <span class="px-2 text-sm font-semibold text-stone-600 sm:hidden">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

                <div class="hidden items-center gap-1 sm:flex">
                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <span class="{{ $base }} text-stone-400">…</span>
                        @else
                            @foreach ($element as $page => $url)
                                @if ($page === $paginator->currentPage())
                                    <span class="{{ $base }} bg-stone-900 text-white" aria-current="page">{{ $page }}</span>
                                @else
                                    <a href="{{ $url }}" class="{{ $base }} text-stone-600 hover:bg-stone-100" aria-label="Halaman {{ $page }}">{{ $page }}</a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach
                </div>

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $base }} text-stone-600 hover:bg-stone-100"><x-icon name="chevron-right" class="size-4" /><span class="sr-only">Berikutnya</span></a>
                @else
                    <span class="{{ $base }} cursor-not-allowed text-stone-300" aria-disabled="true"><x-icon name="chevron-right" class="size-4" /><span class="sr-only">Berikutnya</span></span>
                @endif
            </nav>
        @endif
    </div>
@endif
