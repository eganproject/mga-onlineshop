@php
    $time = fn ($date) => $date?->timezone(config('app.timezone'))->translatedFormat('d M Y, H.i');
@endphp
<x-layouts.admin title="Sinkron stok gudang">
    <x-admin.page-header title="Sinkron stok gudang" description="Stok toko ditarik dari warehouse29 dan dicocokkan per SKU. Stok toko = stok gudang dikurangi pesanan website yang belum dikirim.">
        <a href="{{ route('admin.stock.index') }}" class="btn btn-secondary"><x-icon name="arrow-left" class="size-4" /> Kembali ke stok</a>
        @if ($configured)
            <form method="POST" action="{{ route('admin.stock.warehouse.sync') }}" data-confirm="Tarik ulang semua SKU dari gudang? Stok semua produk yang terhubung disamakan dengan gudang." data-confirm-title="Sinkron penuh" data-confirm-ok="Tarik semua">
                @csrf
                <input type="hidden" name="full" value="1">
                <button class="btn btn-secondary"><x-icon name="download" class="size-4" /> Sinkron penuh</button>
            </form>
            <form method="POST" action="{{ route('admin.stock.warehouse.sync') }}">
                @csrf
                <button class="btn btn-primary"><x-icon name="refresh" class="size-4" /> Sinkron sekarang</button>
            </form>
        @endif
    </x-admin.page-header>

    @unless ($configured)
        <div class="mb-6 flex gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            <x-icon name="warning" class="size-5 shrink-0" />
            <p>
                URL dan token API warehouse29 belum diisi.
                @can('integrations.manage')
                    Atur di <a href="{{ route('admin.integrations.index') }}#warehouse" class="font-semibold underline">Integrasi › Stok gudang</a>.
                @else
                    Minta Superadmin mengisinya di Admin › Integrasi.
                @endcan
            </p>
        </div>
    @endunless

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="card min-w-0 p-4 sm:p-5">
            <p class="text-xs font-medium text-stone-500 sm:text-sm">Sinkron terakhir</p>
            @if ($lastRun)
                <p class="mt-2 flex flex-wrap items-center gap-2">
                    <span class="badge {{ $lastRun->statusClass() }}">{{ $lastRun->statusLabel() }}</span>
                    <span class="text-sm font-semibold text-stone-900">{{ $lastRun->started_at->diffForHumans() }}</span>
                </p>
                @if ($lastRun->status === 'failed')
                    <p class="mt-1 text-xs text-rose-700">{{ $lastRun->message }}</p>
                @elseif ($lastSuccess)
                    <p class="mt-1 text-xs text-stone-500">Berhasil terakhir {{ $time($lastSuccess->finished_at) }}</p>
                @endif
            @else
                <p class="mt-2 text-sm font-semibold text-stone-900">Belum pernah</p>
            @endif
            <p class="mt-1 text-xs text-stone-500">
                @if ($enabled)
                    Otomatis {{ $interval === 60 ? 'tiap 1 jam' : 'tiap '.$interval.' menit' }}{{ $host ? ' dari '.$host : '' }}, penuh tiap malam 02.40.
                @else
                    Sinkron otomatis nonaktif.
                @endif
            </p>
        </div>
        <a href="{{ route('admin.stock.warehouse', ['tab' => 'terhubung']) }}" class="block rounded-3xl transition hover:-translate-y-0.5">
            <x-admin.stat-card label="SKU gudang terhubung" :value="number_format($counts['linked'], 0, ',', '.')" icon="link" tone="emerald" :hint="'Dari '.number_format($counts['items'], 0, ',', '.').' SKU aktif di gudang'" />
        </a>
        <a href="{{ route('admin.stock.warehouse', ['tab' => 'belum']) }}" class="block rounded-3xl transition hover:-translate-y-0.5">
            <x-admin.stat-card label="SKU gudang belum terhubung" :value="number_format($counts['unlinked'], 0, ',', '.')" icon="warning" tone="amber" hint="Stoknya belum dipakai toko" />
        </a>
    </div>

    <div class="card overflow-hidden" x-data="{ open: false, sku: '', name: '' }"
         @link-sku.window="sku = $event.detail.sku; name = $event.detail.name; open = true">
        <div class="flex flex-col gap-3 border-b border-stone-100 p-4 lg:flex-row lg:items-center lg:justify-between">
            <nav class="flex gap-1 rounded-xl bg-stone-100 p-1 text-sm font-semibold" aria-label="Tab SKU gudang">
                @foreach (['belum' => 'Belum terhubung', 'terhubung' => 'Terhubung'] as $value => $text)
                    <a href="{{ route('admin.stock.warehouse', ['tab' => $value]) }}" @class([
                        'flex-1 rounded-lg px-4 py-2 text-center transition lg:flex-none',
                        'bg-white text-stone-900 shadow-sm' => $tab === $value,
                        'text-stone-500 hover:text-stone-800' => $tab !== $value,
                    ]) @if ($tab === $value) aria-current="page" @endif>
                        {{ $text }} <span class="text-stone-400">({{ $counts[$value === 'belum' ? 'unlinked' : 'linked'] }})</span>
                    </a>
                @endforeach
            </nav>
            <form method="GET" class="relative lg:w-72">
                <x-admin.keep-query />
                <input type="hidden" name="tab" value="{{ $tab }}">
                <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-stone-400" />
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari SKU atau nama barang gudang..." class="input pl-10">
            </form>
        </div>

        @if ($items->isEmpty())
            @if ($counts['items'] === 0 && ! $lastSuccess)
                <x-empty-state icon="archive" title="Belum ada data gudang" description="Tekan “Sinkron sekarang” untuk menarik daftar SKU dari warehouse29." />
            @elseif ($tab === 'belum')
                <x-empty-state icon="check-circle" title="Semua SKU gudang sudah terhubung" description="Stok produk yang terhubung ikut berubah setiap kali stok di gudang berubah." />
            @else
                <x-empty-state icon="link" title="Belum ada SKU yang terhubung" description="Buka tab “Belum terhubung” lalu pasangkan SKU gudang dengan produk toko." />
            @endif
        @else
            <div class="relative overflow-x-auto">
                <table class="table-base">
                    <thead class="bg-stone-50/70">
                        <tr>
                            <th>Barang gudang</th>
                            <th class="text-right">Stok gudang</th>
                            @if ($tab === 'terhubung')
                                <th class="hidden md:table-cell">Produk toko</th>
                                <th class="hidden text-right sm:table-cell">Belum dikirim</th>
                                <th class="text-right">Stok toko</th>
                                <th class="text-right">Aksi</th>
                            @else
                                <th class="text-right">Hubungkan</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($items as $item)
                            @php($links = $targets[mb_strtolower($item->sku)] ?? [])
                            <tr class="bg-white align-top">
                                <td>
                                    <p class="font-mono text-xs font-semibold text-stone-900">{{ $item->sku }}</p>
                                    <p class="max-w-72 text-sm text-stone-700">{{ $item->name ?? '—' }}</p>
                                    <p class="text-xs text-stone-500">
                                        {{ $item->category ?? 'Tanpa kategori' }}
                                        @if ($item->status !== 'active') · <span class="text-rose-600">{{ $item->statusLabel() }}</span>@endif
                                    </p>
                                    @if ($tab === 'terhubung')
                                        <div class="mt-1 space-y-0.5 text-xs text-stone-600 md:hidden">
                                            @foreach ($links as [$product, $variant])
                                                <p>→ {{ $product->name }}{{ $variant ? ' ('.$variant->name.')' : '' }}</p>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="text-right tabular-nums">
                                    {{ rtrim(rtrim(number_format($item->qty, 2, ',', '.'), '0'), ',') }}
                                    <span class="text-xs text-stone-400">{{ $item->uom }}</span>
                                </td>
                                @if ($tab === 'terhubung')
                                    <td class="hidden md:table-cell">
                                        @foreach ($links as [$product, $variant])
                                            <p class="text-sm">
                                                @can('catalog.manage')
                                                    <a href="{{ route('admin.products.edit', $product) }}" class="font-semibold text-stone-900 hover:text-brand-700">{{ $product->name }}</a>
                                                @else
                                                    <span class="font-semibold text-stone-900">{{ $product->name }}</span>
                                                @endcan
                                                @if ($variant)<span class="text-stone-500">({{ $variant->name }})</span>@endif
                                            </p>
                                            @unless (($variant ?? $product)->warehouse_sku)
                                                <p class="text-xs text-stone-500">Cocok karena SKU toko sama</p>
                                            @endunless
                                        @endforeach
                                    </td>
                                    <td class="hidden text-right tabular-nums sm:table-cell">
                                        @foreach ($links as [$product, $variant])
                                            <p>{{ $reserved[$variant ? 'v'.$variant->id : 'p'.$product->id] ?? 0 }}</p>
                                        @endforeach
                                    </td>
                                    <td class="text-right font-semibold tabular-nums text-stone-900">
                                        @foreach ($links as [$product, $variant])
                                            <p>{{ ($variant ?? $product)->stock }}</p>
                                        @endforeach
                                    </td>
                                    <td class="text-right">
                                        @if (collect($links)->contains(fn ($link) => filled(($link[1] ?? $link[0])->warehouse_sku)))
                                            <form method="POST" action="{{ route('admin.stock.warehouse.unlink') }}"
                                                  data-confirm="Stok produk ini tidak akan ikut berubah lagi saat stok gudang {{ $item->sku }} berubah." data-confirm-title="Putuskan SKU gudang?" data-confirm-ok="Putuskan" data-confirm-tone="danger">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="sku" value="{{ $item->sku }}">
                                                <button class="btn btn-ghost btn-sm text-rose-600">Putuskan</button>
                                            </form>
                                        @endif
                                    </td>
                                @else
                                    <td class="text-right">
                                        @if ($suggestion = $suggestions[$item->sku] ?? null)
                                            <form method="POST" action="{{ route('admin.stock.warehouse.link') }}" class="mb-2 flex flex-col items-end gap-1">
                                                @csrf
                                                <input type="hidden" name="sku" value="{{ $item->sku }}">
                                                <input type="hidden" name="target" value="{{ $suggestion }}">
                                                <span class="max-w-60 text-xs text-stone-500">Saran: <span class="font-semibold text-stone-700">{{ $options[$suggestion] }}</span></span>
                                                <button class="btn btn-primary btn-sm"><x-icon name="link" class="size-4" /> Hubungkan saran</button>
                                            </form>
                                        @endif
                                        <button type="button" class="btn btn-secondary btn-sm"
                                                @click="$dispatch('link-sku', { sku: @js($item->sku), name: @js($item->name ?? $item->sku) })">
                                            Pilih produk…
                                        </button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-admin.pagination :paginator="$items" label="SKU" />
        @endif

        {{-- Modal pilih produk/varian untuk SKU gudang --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-stone-900/40 p-4 backdrop-blur-sm sm:items-center" @keydown.escape.window="open = false">
            <form method="POST" action="{{ route('admin.stock.warehouse.link') }}" @click.outside="open = false" x-transition
                  class="w-full max-w-lg space-y-4 rounded-3xl bg-white p-6 shadow-2xl">
                @csrf
                <input type="hidden" name="sku" x-bind:value="sku">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="text-lg font-bold text-stone-900">Hubungkan SKU gudang</h2>
                        <p class="text-sm text-stone-500"><span class="font-mono" x-text="sku"></span> · <span x-text="name"></span></p>
                    </div>
                    <button type="button" @click="open = false" class="btn btn-ghost p-2" aria-label="Tutup"><x-icon name="x" class="size-5" /></button>
                </div>
                <x-form.select name="target" id="warehouse-target" label="Produk / varian toko" :options="$options" placeholder="Cari nama produk atau SKU..." :search="true"
                               tooltip="Stok produk yang dipilih akan mengikuti stok gudang SKU ini, dikurangi pesanan website yang belum dikirim."
                               :tooltip-tips="['Produk bervarian dihubungkan per varian.', 'Satu SKU gudang hanya untuk satu produk/varian.']" />
                <p class="hint">Stok toko langsung disamakan setelah disimpan dan tercatat di riwayat stok sebagai “Sinkron gudang”.</p>
                <button class="btn btn-primary w-full py-3">Hubungkan</button>
            </form>
        </div>
    </div>

    @if ($runs->isNotEmpty())
        <section class="card mt-6 overflow-hidden">
            <h2 class="border-b border-stone-100 px-5 py-4 font-bold text-stone-900">Riwayat sinkron</h2>
            <div class="relative overflow-x-auto">
                <table class="table-base">
                    <thead class="bg-stone-50/70"><tr><th>Waktu</th><th class="hidden sm:table-cell">Jenis</th><th>Hasil</th><th class="hidden text-right sm:table-cell">SKU diterima</th><th class="text-right">Stok diubah</th></tr></thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($runs as $run)
                            <tr class="bg-white">
                                <td class="whitespace-nowrap">
                                    {{ $time($run->started_at) }}
                                    <p class="text-xs text-stone-500">{{ $run->user?->name ?? 'Otomatis' }}<span class="sm:hidden"> · {{ $run->modeLabel() }}</span></p>
                                </td>
                                <td class="hidden sm:table-cell">{{ $run->modeLabel() }}</td>
                                <td>
                                    <span class="badge {{ $run->statusClass() }}">{{ $run->statusLabel() }}</span>
                                    @if ($run->message)<p class="mt-1 max-w-80 text-xs text-rose-700">{{ $run->message }}</p>@endif
                                </td>
                                <td class="hidden text-right tabular-nums sm:table-cell">{{ $run->fetched }}</td>
                                <td class="text-right tabular-nums">{{ $run->updated }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</x-layouts.admin>
