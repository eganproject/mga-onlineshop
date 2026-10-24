<x-layouts.admin title="Artikel">
    <x-admin.page-header title="Artikel" description="Tulisan blog: tips, panduan memilih produk, dan kabar toko. Bagus untuk SEO.">
        <a href="{{ route('admin.article-categories.index') }}" class="btn btn-secondary"><x-icon name="tag" class="size-4" /> Kategori</a>
        <a href="{{ route('admin.articles.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Tulis artikel</a>
    </x-admin.page-header>

    @php($filters = array_filter(request()->only(['q', 'kategori', 'sort'])))
    <div class="card overflow-hidden">
        <form method="GET" class="flex flex-col gap-3 border-b border-stone-100 p-4 lg:flex-row lg:items-center">
            @if ($state) <input type="hidden" name="status" value="{{ $state }}"> @endif
            <div class="relative flex-1 lg:max-w-xs">
                <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-stone-400" />
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari judul artikel..." class="input pl-10">
            </div>
            @if ($categories->isNotEmpty())
                <x-form.select name="kategori" :options="$categories" :selected="request('kategori')" placeholder="Semua kategori" wrapper-class="lg:w-56" x-on:change="$el.form.submit()" />
            @endif
            <x-admin.sort-select class="lg:w-48" :options="['-diubah' => 'Terakhir diubah', '-terbit' => 'Tanggal terbit', 'judul' => 'Judul A–Z']" />
            <div class="no-scrollbar -mx-1 flex gap-1.5 overflow-x-auto px-1 lg:ml-auto">
                <a href="{{ route('admin.articles.index', $filters) }}" @class(['shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold', 'bg-stone-900 text-white' => ! $state, 'bg-stone-100 text-stone-600 hover:bg-stone-200' => $state])>Semua</a>
                @foreach ($states as $key => $label)
                    <a href="{{ route('admin.articles.index', $filters + ['status' => $key]) }}" @class(['shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold', 'bg-stone-900 text-white' => $state === $key, 'bg-stone-100 text-stone-600 hover:bg-stone-200' => $state !== $key])>{{ $label }}</a>
                @endforeach
            </div>
        </form>

        @if ($articles->isEmpty())
            <x-empty-state icon="newspaper" :title="request()->hasAny(['q', 'kategori']) || $state ? 'Artikel tidak ditemukan' : 'Belum ada artikel'" description="Tulis artikel pertama, misalnya tips merawat akrilik atau panduan memilih stand banner.">
                <a href="{{ route('admin.articles.create') }}" class="btn btn-primary">Tulis artikel</a>
            </x-empty-state>
        @else
            <ul class="divide-y divide-stone-100">
                @foreach ($articles as $article)
                    <li class="flex items-center gap-4 p-4 hover:bg-stone-50/50">
                        <div class="hidden h-16 w-24 shrink-0 overflow-hidden rounded-lg bg-stone-100 sm:block">
                            @if ($article->coverUrl())
                                <img src="{{ $article->coverUrl() }}" alt="" loading="lazy" class="size-full object-cover">
                            @else
                                <div class="grid size-full place-items-center text-stone-300"><x-icon name="newspaper" class="size-6" /></div>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="badge {{ $article->stateBadgeClass() }}">{{ $article->stateLabel() }}</span>
                                @if ($article->category)
                                    <span class="text-xs font-medium text-stone-500">{{ $article->category->name }}</span>
                                @endif
                            </div>
                            <a href="{{ route('admin.articles.edit', $article) }}" class="mt-1 block truncate font-semibold text-stone-900 hover:text-brand-700">{{ $article->title }}</a>
                            <p class="mt-0.5 truncate text-xs text-stone-500">
                                @if ($article->state() === 'draft')
                                    Diubah {{ $article->updated_at->diffForHumans() }}
                                @else
                                    {{ $article->state() === 'scheduled' ? 'Dijadwalkan' : 'Terbit' }} {{ $article->published_at->translatedFormat('j M Y, H:i') }}
                                @endif
                                · {{ $article->reading_minutes }} menit baca
                                @if ($article->author) · {{ $article->author->name }} @endif
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <a href="{{ route('articles.show', $article) }}" target="_blank" class="btn btn-ghost btn-sm" title="{{ $article->isVisible() ? 'Lihat di toko' : 'Pratinjau' }}"><x-icon name="eye" class="size-4" /></a>
                            <a href="{{ route('admin.articles.edit', $article) }}" class="btn btn-ghost btn-sm"><x-icon name="pencil" class="size-4" /> <span class="sr-only sm:not-sr-only">Edit</span></a>
                            <x-confirm-delete :action="route('admin.articles.destroy', $article)" title="Hapus artikel?" message="Artikel “{{ $article->title }}” beserta gambar di dalamnya akan dihapus permanen." />
                        </div>
                    </li>
                @endforeach
            </ul>
            <x-admin.pagination :paginator="$articles" label="artikel" />
        @endif
    </div>
</x-layouts.admin>
