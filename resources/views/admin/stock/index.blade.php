<x-layouts.admin title="Stok">
    <x-admin.page-header title="Manajemen stok" description="Pantau stok per produk & varian, catat barang masuk, dan lakukan stock opname.">
        <a href="{{ route('admin.stock.history') }}" class="btn btn-secondary"><x-icon name="clock" class="size-4" /> Riwayat stok</a>
        <a href="{{ route('admin.products.transfer') }}#stok" class="btn btn-secondary"><x-icon name="download" class="size-4 rotate-180" /> Update via file</a>
    </x-admin.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-admin.stat-card label="Total unit di gudang" :value="number_format($summary['units'], 0, ',', '.')" icon="archive" />
        <x-admin.stat-card label="Produk stok menipis" :value="$summary['low']" icon="warning" tone="violet" :hint="'Stok ≤ '.$threshold.' unit'" />
        <x-admin.stat-card label="Produk stok habis" :value="$summary['out']" icon="x-circle" tone="sky" hint="Tidak bisa dibeli pelanggan" />
    </div>

    <div class="card overflow-hidden"
         x-data="{ open: false, item: {}, mode: 'add' }"
         @adjust-stock.window="item = $event.detail; mode = 'add'; open = true; $nextTick(() => $refs.qty.focus())">
        <form method="GET" class="flex flex-col gap-3 border-b border-stone-100 p-4 sm:flex-row" x-data @change="$el.submit()">
            <div class="relative flex-1 sm:max-w-xs">
                <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-stone-400" />
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama atau SKU..." class="input pl-10">
            </div>
            <select name="status" class="input sm:w-48">
                <option value="">Semua stok</option>
                <option value="menipis" @selected(request('status') === 'menipis')>Menipis (≤ {{ $threshold }})</option>
                <option value="habis" @selected(request('status') === 'habis')>Habis</option>
            </select>
        </form>

        @if ($products->isEmpty())
            <x-empty-state icon="archive" title="Tidak ada produk" description="Ubah filter atau tambahkan produk terlebih dahulu." />
        @else
            <div class="relative overflow-x-auto">
                <table class="table-base">
                    <thead class="bg-stone-50/70"><tr><th>Produk / varian</th><th>SKU</th><th>Stok</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($products as $product)
                            @php
                                $rows = $product->has_variants
                                    ? $product->variants->map(fn ($v) => ['variant' => $v, 'name' => $v->name, 'sku' => $v->sku, 'stock' => $v->stock, 'low' => $v->isLowStock(), 'active' => $v->is_active])
                                    : collect([['variant' => null, 'name' => null, 'sku' => $product->sku, 'stock' => $product->stock, 'low' => $product->isLowStock(), 'active' => true]]);
                            @endphp
                            <tr class="bg-white">
                                <td @if ($product->has_variants) colspan="2" @endif>
                                    <div class="flex min-w-56 items-center gap-3">
                                        <x-product-image :product="$product" class="size-10 shrink-0 rounded-lg" />
                                        <div class="min-w-0">
                                            <a href="{{ route('admin.products.edit', $product) }}" class="block truncate font-semibold text-stone-900 hover:text-brand-700">{{ $product->name }}</a>
                                            <p class="text-xs text-stone-500">
                                                {{ $product->category?->name ?? 'Tanpa kategori' }}
                                                @unless ($product->is_active) · <span class="text-stone-400">Nonaktif</span>@endunless
                                                @if ($product->has_variants) · {{ $product->variants->count() }} varian · total {{ $product->stock }}@endif
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                @unless ($product->has_variants)
                                    @include('admin.stock.partials.cells', ['product' => $product, 'row' => $rows->first()])
                                @else
                                    <td></td><td></td>
                                @endunless
                            </tr>
                            @if ($product->has_variants)
                                @foreach ($rows as $row)
                                    <tr class="bg-stone-50/40">
                                        <td class="pl-16">
                                            <span class="inline-flex items-center gap-2 text-stone-700">
                                                <x-icon name="swatch" class="size-4 text-stone-400" /> {{ $row['name'] }}
                                                @unless ($row['active'])<span class="badge bg-stone-100 text-stone-500 ring-stone-500/20">Nonaktif</span>@endunless
                                            </span>
                                        </td>
                                        @include('admin.stock.partials.cells', ['product' => $product, 'row' => $row])
                                    </tr>
                                @endforeach
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-stone-100 p-4">{{ $products->links() }}</div>
        @endif

        {{-- Modal penyesuaian stok --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-stone-900/40 p-4 backdrop-blur-sm sm:items-center" @keydown.escape.window="open = false">
            <form method="POST" action="{{ route('admin.stock.adjust') }}" @click.outside="open = false" x-transition
                  class="w-full max-w-md space-y-4 rounded-3xl bg-white p-6 shadow-2xl">
                @csrf
                <input type="hidden" name="product_id" :value="item.product_id">
                <input type="hidden" name="variant_id" :value="item.variant_id ?? ''">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-stone-900">Ubah stok</h2>
                        <p class="text-sm text-stone-500" x-text="item.label"></p>
                    </div>
                    <button type="button" @click="open = false" class="btn btn-ghost p-2" aria-label="Tutup"><x-icon name="x" class="size-5" /></button>
                </div>
                <p class="rounded-2xl bg-stone-50 px-4 py-3 text-sm">Stok saat ini: <span class="font-bold text-stone-900" x-text="item.stock"></span></p>
                <div class="grid grid-cols-3 gap-2 text-sm font-semibold">
                    @foreach (['add' => 'Tambah', 'subtract' => 'Kurangi', 'set' => 'Set jadi'] as $value => $label)
                        <label class="cursor-pointer rounded-xl border px-3 py-2 text-center transition"
                               :class="mode === '{{ $value }}' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-stone-200 text-stone-600 hover:bg-stone-50'">
                            <input type="radio" name="mode" value="{{ $value }}" x-model="mode" class="sr-only"> {{ $label }}
                        </label>
                    @endforeach
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="adjust-qty" class="label">Jumlah</label>
                        <input id="adjust-qty" x-ref="qty" type="number" name="quantity" min="0" required class="input">
                    </div>
                    <div>
                        <label for="adjust-type" class="label">Jenis</label>
                        <select id="adjust-type" name="type" class="input" x-effect="$el.value = mode === 'add' ? 'restock' : 'adjustment'">
                            @foreach ($types as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label for="adjust-note" class="label">Catatan <span class="font-normal text-stone-400">(opsional)</span></label>
                    <input id="adjust-note" name="note" maxlength="255" placeholder="Mis. kiriman supplier, barang rusak, stock opname" class="input">
                </div>
                <button class="btn btn-primary w-full py-3">Simpan perubahan stok</button>
            </form>
        </div>
    </div>
</x-layouts.admin>
