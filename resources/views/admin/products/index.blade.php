<x-layouts.admin title="Produk">
    <x-admin.page-header title="Produk" description="Kelola katalog, foto, varian, harga, dan stok produk.">
        <a href="{{ route('admin.stock.index') }}" class="btn btn-secondary"><x-icon name="archive" class="size-4" /> Kelola stok</a>
        <a href="{{ route('admin.products.transfer') }}" class="btn btn-secondary"><x-icon name="download" class="size-4" /> Impor / ekspor</a>
        <a href="{{ route('admin.products.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Tambah produk</a>
    </x-admin.page-header>

    <div class="card overflow-hidden" x-data="{ selected: [], all: @js($products->pluck('id')) }">
        <form method="GET" class="flex flex-col gap-3 border-b border-stone-100 p-4 sm:flex-row" x-data @change="$el.submit()">
            <div class="relative flex-1 sm:max-w-xs">
                <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-stone-400" />
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama atau SKU..." class="input pl-10">
            </div>
            <select name="kategori" class="input sm:w-48">
                <option value="">Semua kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('kategori') == $category->id)>{{ $category->parent_id ? '— '.$category->name : $category->name }}</option>
                @endforeach
            </select>
            <select name="status" class="input sm:w-44">
                <option value="">Semua status</option>
                @foreach (['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif', 'unggulan' => 'Unggulan', 'stok-menipis' => 'Stok menipis', 'stok-habis' => 'Stok habis'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </form>

        {{-- Aksi massal --}}
        <form id="bulk-form" method="POST" action="{{ route('admin.products.bulk') }}" x-show="selected.length" x-cloak
              class="flex flex-wrap items-center gap-2 border-b border-brand-100 bg-brand-50/60 px-4 py-3"
              @submit="if ($event.submitter?.value === 'delete' && !confirm('Hapus ' + selected.length + ' produk terpilih?')) $event.preventDefault()">
            @csrf
            <p class="mr-2 text-sm font-semibold text-brand-800"><span x-text="selected.length"></span> dipilih</p>
            <button name="action" value="activate" class="btn btn-secondary btn-sm">Aktifkan</button>
            <button name="action" value="deactivate" class="btn btn-secondary btn-sm">Nonaktifkan</button>
            <button name="action" value="feature" class="btn btn-secondary btn-sm"><x-icon name="star" class="size-3.5" /> Unggulan</button>
            <button name="action" value="unfeature" class="btn btn-secondary btn-sm">Bukan unggulan</button>
            <button name="action" value="delete" class="btn btn-ghost btn-sm text-rose-600 hover:bg-rose-50"><x-icon name="trash" class="size-3.5" /> Hapus</button>
            <button type="button" @click="selected = []" class="ml-auto text-sm font-medium text-stone-500 hover:text-stone-800">Batal</button>
        </form>

        @if ($products->isEmpty())
            <x-empty-state icon="cube" title="Produk tidak ditemukan" description="Tambahkan produk baru atau ubah filter pencarian.">
                <a href="{{ route('admin.products.create') }}" class="btn btn-primary">Tambah produk</a>
            </x-empty-state>
        @else
            <div class="relative overflow-x-auto">
                <table class="table-base">
                    <thead class="bg-stone-50/70">
                        <tr>
                            <th class="w-10">
                                <input type="checkbox" class="checkbox" aria-label="Pilih semua"
                                       :checked="selected.length === all.length"
                                       @change="selected = $event.target.checked ? [...all] : []">
                            </th>
                            <th>Produk</th><th>Kategori</th><th>Harga</th><th>Stok</th><th>Status</th><th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($products as $product)
                            <tr class="hover:bg-stone-50/50" :class="selected.includes({{ $product->id }}) && 'bg-brand-50/40'">
                                <td>
                                    <input type="checkbox" form="bulk-form" name="ids[]" value="{{ $product->id }}" x-model.number="selected" class="checkbox" aria-label="Pilih {{ $product->name }}">
                                </td>
                                <td>
                                    <a href="{{ route('admin.products.edit', $product) }}" class="flex min-w-56 items-center gap-3">
                                        <x-product-image :product="$product" class="size-12 shrink-0 rounded-xl" />
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-stone-900 hover:text-brand-700">{{ $product->name }}</p>
                                            <p class="text-xs text-stone-500">
                                                {{ $product->sku ?? 'Tanpa SKU' }} · {{ $product->weight }} gr
                                                @if ($product->variants_count) · <span class="font-semibold text-brand-700">{{ $product->variants_count }} varian</span>@endif
                                            </p>
                                        </div>
                                    </a>
                                </td>
                                <td class="whitespace-nowrap">{{ $product->category?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap">
                                    <p class="font-semibold text-stone-900">@if ($product->has_variants)<span class="text-xs font-normal text-stone-400">mulai</span> @endif @rupiah($product->price)</p>
                                    @if ($product->isOnSale())<p class="text-xs text-stone-400 line-through">@rupiah($product->compare_at_price)</p>@endif
                                </td>
                                <td>
                                    <span @class(['font-semibold', 'text-rose-600' => $product->stock === 0, 'text-amber-600' => $product->stock > 0 && $product->isLowStock(), 'text-stone-700' => ! $product->isLowStock()])>{{ $product->stock }}</span>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        @if ($product->is_active)
                                            <span class="badge bg-emerald-50 text-emerald-700 ring-emerald-600/20">Aktif</span>
                                        @else
                                            <span class="badge bg-stone-100 text-stone-600 ring-stone-500/20">Nonaktif</span>
                                        @endif
                                        @if ($product->is_featured)
                                            <span class="badge bg-amber-50 text-amber-700 ring-amber-600/20"><x-icon name="star" class="size-3" /> Unggulan</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="flex justify-end gap-1">
                                        <a href="{{ route('products.show', $product) }}" target="_blank" class="btn btn-ghost btn-sm" title="Lihat di toko"><x-icon name="eye" class="size-4" /></a>
                                        <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-ghost btn-sm"><x-icon name="pencil" class="size-4" /> <span class="sr-only sm:not-sr-only">Edit</span></a>
                                        <x-confirm-delete :action="route('admin.products.destroy', $product)" message="Hapus produk {{ $product->name }}?" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-stone-100 p-4">{{ $products->links() }}</div>
        @endif
    </div>
</x-layouts.admin>
