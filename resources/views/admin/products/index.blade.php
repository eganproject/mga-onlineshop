<x-layouts.admin title="Produk">
    <x-admin.page-header title="Produk" description="Kelola katalog, harga, dan stok produk.">
        <a href="{{ route('admin.products.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Tambah produk</a>
    </x-admin.page-header>

    <div class="card overflow-hidden">
        <form method="GET" class="flex flex-col gap-3 border-b border-stone-100 p-4 sm:flex-row" x-data @change="$el.submit()">
            <div class="relative flex-1 sm:max-w-xs">
                <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-stone-400" />
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama atau SKU..." class="input pl-10">
            </div>
            <select name="kategori" class="input sm:w-48">
                <option value="">Semua kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('kategori') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <select name="status" class="input sm:w-44">
                <option value="">Semua status</option>
                <option value="aktif" @selected(request('status') === 'aktif')>Aktif</option>
                <option value="nonaktif" @selected(request('status') === 'nonaktif')>Nonaktif</option>
                <option value="stok-menipis" @selected(request('status') === 'stok-menipis')>Stok menipis</option>
            </select>
        </form>

        @if ($products->isEmpty())
            <x-empty-state icon="cube" title="Produk tidak ditemukan" description="Tambahkan produk baru atau ubah filter pencarian.">
                <a href="{{ route('admin.products.create') }}" class="btn btn-primary">Tambah produk</a>
            </x-empty-state>
        @else
            <div class="relative overflow-x-auto">
                <table class="table-base">
                    <thead class="bg-stone-50/70"><tr><th>Produk</th><th>Kategori</th><th>Harga</th><th>Stok</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($products as $product)
                            <tr class="hover:bg-stone-50/50">
                                <td>
                                    <div class="flex min-w-56 items-center gap-3">
                                        <x-product-image :product="$product" class="size-12 shrink-0 rounded-xl" />
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-stone-900">{{ $product->name }}</p>
                                            <p class="text-xs text-stone-500">{{ $product->sku ?? 'Tanpa SKU' }} · {{ $product->weight }} gr</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">{{ $product->category?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap">
                                    <p class="font-semibold text-stone-900">@rupiah($product->price)</p>
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
