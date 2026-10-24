<x-layouts.admin title="Kategori">
    <x-admin.page-header title="Kategori" description="Kelompokkan produk (maks. 2 tingkat) agar mudah dicari pelanggan.">
        <a href="{{ route('admin.categories.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Tambah kategori</a>
    </x-admin.page-header>

    <div class="card overflow-hidden">
        <form method="GET" class="border-b border-stone-100 p-4">
            <div class="relative max-w-xs">
                <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-stone-400" />
                <input type="search" name="q" value="{{ $search }}" placeholder="Cari kategori..." class="input pl-10">
            </div>
        </form>

        @if ($categories->isEmpty())
            <x-empty-state icon="tag" :title="$search ? 'Kategori tidak ditemukan' : 'Belum ada kategori'" description="Buat kategori pertama, misalnya Tripod Banner atau Frame Akrilik.">
                <a href="{{ route('admin.categories.create') }}" class="btn btn-primary">Tambah kategori</a>
            </x-empty-state>
        @else
            <div class="relative overflow-x-auto">
                <table class="table-base">
                    <thead class="bg-stone-50/70"><tr><th>Kategori</th><th>Produk</th><th>Urutan</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($categories as $category)
                            <tr @class(['hover:bg-stone-50/50', 'bg-stone-50/30' => $category->parent_id])>
                                <td>
                                    <div @class(['flex min-w-52 items-center gap-3', 'pl-8' => $category->parent_id && ! $search])>
                                        <div class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-xl bg-brand-50 text-brand-500">
                                            @if ($category->imageUrl())
                                                <img src="{{ $category->imageUrl() }}" alt="" class="size-full object-cover">
                                            @else
                                                <x-icon name="tag" class="size-5" />
                                            @endif
                                        </div>
                                        <div>
                                            <p class="font-semibold text-stone-900">{{ $category->name }}</p>
                                            <p class="text-xs text-stone-500">
                                                @if ($category->parent){{ $category->parent->name }} › @endif/{{ $category->slug }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <a href="{{ route('admin.products.index', ['kategori' => $category->id]) }}" class="font-semibold text-stone-700 hover:text-brand-700">{{ $category->products_count }}</a>
                                    @if (! $search && ! $category->parent_id && $category->children->sum('products_count'))
                                        <span class="text-xs text-stone-400">+{{ $category->children->sum('products_count') }} di subkategori</span>
                                    @endif
                                </td>
                                <td>{{ $category->sort_order }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.categories.toggle', $category) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button title="Klik untuk {{ $category->is_active ? 'sembunyikan' : 'tampilkan' }}">
                                            @if ($category->is_active)
                                                <span class="badge bg-emerald-50 text-emerald-700 ring-emerald-600/20">Aktif</span>
                                            @else
                                                <span class="badge bg-stone-100 text-stone-600 ring-stone-500/20">Nonaktif</span>
                                            @endif
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <div class="flex justify-end gap-1">
                                        <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-ghost btn-sm"><x-icon name="pencil" class="size-4" /> <span class="sr-only sm:not-sr-only">Edit</span></a>
                                        <x-confirm-delete :action="route('admin.categories.destroy', $category)" title="Hapus kategori {{ $category->name }}?" message="Produk & subkategori di dalamnya tidak ikut terhapus, hanya jadi tanpa kategori induk." />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-admin.pagination :paginator="$paginator" :label="$search ? 'kategori' : 'kategori induk'" />
        @endif
    </div>
</x-layouts.admin>
