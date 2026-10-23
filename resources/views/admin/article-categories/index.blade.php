@php($editing = $category->exists)
<x-layouts.admin title="Kategori Artikel">
    <x-admin.page-header title="Kategori artikel" description="Kelompokkan artikel, mis. Tips & Trik, Review Produk, Kabar Toko." :back="route('admin.articles.index')" />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card overflow-hidden lg:col-span-2">
            @if ($categories->isEmpty())
                <x-empty-state icon="tag" title="Belum ada kategori" description="Tambah kategori pertama lewat form di samping." />
            @else
                <ul class="divide-y divide-stone-100">
                    @foreach ($categories as $item)
                        <li @class(['flex items-center gap-4 p-4', 'bg-brand-50/50' => $editing && $item->is($category)])>
                            <span class="w-8 shrink-0 text-center text-xs font-bold text-stone-400">#{{ $item->sort_order }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-stone-900">{{ $item->name }}</p>
                                <p class="truncate text-xs text-stone-500">/artikel?kategori={{ $item->slug }} · {{ $item->articles_count }} artikel</p>
                            </div>
                            <div class="flex shrink-0 gap-1">
                                <a href="{{ route('admin.article-categories.edit', $item) }}" class="btn btn-ghost btn-sm"><x-icon name="pencil" class="size-4" /> <span class="sr-only sm:not-sr-only">Edit</span></a>
                                <x-confirm-delete :action="route('admin.article-categories.destroy', $item)" message="Hapus kategori {{ $item->name }}? Artikelnya tetap ada, hanya jadi tanpa kategori." />
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <form method="POST" action="{{ $editing ? route('admin.article-categories.update', $category) : route('admin.article-categories.store') }}" class="card h-fit space-y-5 p-6">
            @csrf
            @if ($editing) @method('PUT') @endif
            <h2 class="font-semibold text-stone-900">{{ $editing ? 'Edit kategori' : 'Tambah kategori' }}</h2>
            <x-form.input name="name" label="Nama" required maxlength="80" :value="$category->name" placeholder="Tips & Trik" />
            <x-form.input name="slug" label="Slug" optional maxlength="100" :value="$category->slug" placeholder="tips-trik" hint="Kosongkan untuk dibuat otomatis dari nama." />
            <x-form.textarea name="description" label="Deskripsi" optional maxlength="255" rows="2" :value="$category->description" hint="Tampil di halaman daftar artikel kategori ini." />
            <x-form.number name="sort_order" label="Urutan" :value="$category->sort_order ?? 0" min="0" />
            <div class="flex gap-2">
                <button class="btn btn-primary flex-1">{{ $editing ? 'Simpan perubahan' : 'Tambah kategori' }}</button>
                @if ($editing)
                    <a href="{{ route('admin.article-categories.index') }}" class="btn btn-secondary">Batal</a>
                @endif
            </div>
        </form>
    </div>
</x-layouts.admin>
