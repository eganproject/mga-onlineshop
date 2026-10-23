@php
    use App\Enums\ArticleStatus;

    $editing = $article->exists;
    $selectedTags = old('tags', $article->tagList());
    $tagOptions = collect($tagSuggestions)->merge($selectedTags)->unique()->values();
    $selectedProducts = array_map('intval', old('product_ids', $article->products->pluck('id')->all()));
    $status = old('status', $article->status?->value ?? ArticleStatus::Draft->value);
@endphp
<x-layouts.admin :title="$editing ? 'Edit Artikel' : 'Tulis Artikel'">
    <x-admin.page-header :title="$editing ? 'Edit artikel' : 'Tulis artikel'" :back="route('admin.articles.index')">
        @if ($editing)
            <span class="badge {{ $article->stateBadgeClass() }}">{{ $article->stateLabel() }}</span>
            <a href="{{ route('articles.show', $article) }}" target="_blank" class="btn btn-secondary">
                <x-icon name="eye" class="size-4" /> {{ $article->isVisible() ? 'Lihat di toko' : 'Pratinjau' }}
            </a>
        @endif
    </x-admin.page-header>

    <form method="POST" enctype="multipart/form-data"
          action="{{ $editing ? route('admin.articles.update', $article) : route('admin.articles.store') }}"
          x-data="{
              title: @js(old('title', $article->title ?? '')),
              slug: @js(old('slug', $article->slug ?? '')),
              slugTouched: @js($editing || filled(old('slug'))),
              metaTitle: @js(old('meta_title', $article->meta_title ?? '')),
              metaDescription: @js(old('meta_description', $article->meta_description ?? '')),
              excerpt: @js(old('excerpt', $article->excerpt ?? '')),
              status: @js($status),
              publishedAt: @js(old('published_at', $article->published_at?->format('Y-m-d\TH:i'))),
              slugify(v) { return v.toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 170) },
              get scheduled() { return this.status === 'published' && this.publishedAt && new Date(this.publishedAt) > new Date() },
          }"
          x-effect="if (!slugTouched) slug = slugify(title)"
          {{-- Enter di kotak isian (judul, tag, dll.) jangan langsung mengirim form. --}}
          @keydown.enter="if ($event.target.matches('input:not([type=submit])')) $event.preventDefault()"
          class="grid gap-6 xl:grid-cols-3">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="min-w-0 space-y-6 xl:col-span-2">
            <div class="card space-y-5 p-6">
                <x-form.field label="Judul" for="title" name="title">
                    <input id="title" name="title" x-model="title" required maxlength="160" placeholder="5 Cara Merawat Baterai HP Agar Awet"
                           class="input py-3 text-lg font-semibold @error('title') input-error @enderror">
                </x-form.field>
                <x-form.field label="Slug (alamat URL)" for="slug" name="slug" hint="Otomatis dari judul. Hindari mengubah slug artikel yang sudah terbit supaya link lama tidak rusak.">
                    <div class="flex items-stretch overflow-hidden rounded-xl border border-stone-200 bg-white focus-within:border-brand-500 focus-within:ring-4 focus-within:ring-brand-500/15 @error('slug') border-rose-300 @enderror">
                        <span class="hidden items-center bg-stone-50 px-3 text-sm text-stone-400 sm:flex">{{ str_replace(['https://', 'http://'], '', url('/artikel')) }}/</span>
                        <input id="slug" name="slug" x-model="slug" @input="slugTouched = true" maxlength="170" placeholder="cara-merawat-baterai-hp"
                               class="min-w-0 flex-1 border-0 px-3.5 py-2.5 text-sm focus:ring-0">
                    </div>
                </x-form.field>
            </div>

            <div class="card"
                 x-data="articleEditor({ content: @js(old('body', $article->body ?? '')), uploadUrl: @js(route('admin.articles.images')), placeholder: 'Tulis isi artikel di sini. Tempel atau seret gambar langsung ke editor.' })">
                <div class="sticky top-16 z-20 flex flex-wrap items-center gap-0.5 rounded-t-2xl border-b border-stone-100 bg-white/95 px-2 py-1.5 backdrop-blur" role="toolbar" aria-label="Format teks" @mousedown="if ($event.target.closest('button')) $event.preventDefault()">
                    @php
                        $tool = 'grid h-9 min-w-9 place-items-center rounded-lg px-2 text-sm font-semibold text-stone-600 transition hover:bg-stone-100 hover:text-stone-900 disabled:opacity-30';
                        $on = 'bg-brand-50 text-brand-700 hover:bg-brand-100 hover:text-brand-800';
                    @endphp
                    <button type="button" class="{{ $tool }}" :class="active.h2 && '{{ $on }}'" @click="run('toggleHeading', { level: 2 })" title="Judul bagian (H2)">H2</button>
                    <button type="button" class="{{ $tool }}" :class="active.h3 && '{{ $on }}'" @click="run('toggleHeading', { level: 3 })" title="Subjudul (H3)">H3</button>
                    <span class="mx-1 h-5 w-px bg-stone-200"></span>
                    <button type="button" class="{{ $tool }} font-bold" :class="active.bold && '{{ $on }}'" @click="run('toggleBold')" title="Tebal (Ctrl+B)">B</button>
                    <button type="button" class="{{ $tool }} italic" :class="active.italic && '{{ $on }}'" @click="run('toggleItalic')" title="Miring (Ctrl+I)">I</button>
                    <button type="button" class="{{ $tool }} underline" :class="active.underline && '{{ $on }}'" @click="run('toggleUnderline')" title="Garis bawah (Ctrl+U)">U</button>
                    <button type="button" class="{{ $tool }} line-through" :class="active.strike && '{{ $on }}'" @click="run('toggleStrike')" title="Coret">S</button>
                    <span class="mx-1 h-5 w-px bg-stone-200"></span>
                    <button type="button" class="{{ $tool }}" :class="active.bulletList && '{{ $on }}'" @click="run('toggleBulletList')" title="Daftar berpoin"><x-icon name="list-bullet" class="size-5" /></button>
                    <button type="button" class="{{ $tool }}" :class="active.orderedList && '{{ $on }}'" @click="run('toggleOrderedList')" title="Daftar bernomor"><x-icon name="list-number" class="size-5" /></button>
                    <button type="button" class="{{ $tool }}" :class="active.blockquote && '{{ $on }}'" @click="run('toggleBlockquote')" title="Kutipan"><x-icon name="quote" class="size-5" /></button>
                    <button type="button" class="{{ $tool }}" @click="run('setHorizontalRule')" title="Garis pemisah"><x-icon name="minus" class="size-5" /></button>
                    <span class="mx-1 h-5 w-px bg-stone-200"></span>
                    <button type="button" class="{{ $tool }}" :class="active.link && '{{ $on }}'" @click="setLink()" title="Sisipkan link"><x-icon name="link" class="size-5" /></button>
                    <button type="button" class="{{ $tool }}" @click="pickImage()" title="Sisipkan gambar"><x-icon name="photo" class="size-5" /></button>
                    <input type="file" x-ref="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple class="hidden" @change="handleFiles($event.target.files)">
                    <span class="ml-auto flex items-center gap-0.5">
                        <button type="button" class="{{ $tool }}" :disabled="!active.canUndo" @click="run('undo')" title="Urungkan (Ctrl+Z)"><x-icon name="undo" class="size-4" /></button>
                        <button type="button" class="{{ $tool }}" :disabled="!active.canRedo" @click="run('redo')" title="Ulangi (Ctrl+Shift+Z)"><x-icon name="redo" class="size-4" /></button>
                    </span>
                </div>

                <div x-show="!ready" class="min-h-[420px] animate-pulse px-8 py-6 text-sm text-stone-400">Memuat editor...</div>
                <div x-ref="editor" x-show="ready" x-cloak></div>
                <input type="hidden" name="body" :value="html">

                <div class="flex flex-wrap items-center gap-3 rounded-b-2xl border-t border-stone-100 bg-stone-50/60 px-5 py-2.5 text-xs text-stone-500">
                    <span x-text="`${words} kata · ±${Math.max(1, Math.ceil(words / 200))} menit baca`"></span>
                    <span x-show="uploading" x-cloak class="flex items-center gap-1.5 font-medium text-brand-700"><x-icon name="refresh" class="size-3.5 animate-spin" /> Mengunggah gambar...</span>
                    <span x-show="error" x-cloak x-text="error" class="font-medium text-rose-600"></span>
                </div>
                @error('body')
                    <p class="field-error border-t border-stone-100 px-5 py-2.5"><x-icon name="warning" class="size-4 shrink-0" /> {{ $message }}</p>
                @enderror
            </div>

            <div class="card p-6">
                <x-form.textarea name="excerpt" label="Ringkasan" optional maxlength="300" rows="3" x-model="excerpt"
                    hint="Tampil di daftar artikel & saat dibagikan. Kosongkan untuk memakai awal isi artikel."
                    :value="$article->excerpt" />
            </div>

            <div class="card space-y-5 p-6">
                <div>
                    <h2 class="font-semibold text-stone-900">SEO</h2>
                    <p class="text-xs text-stone-500">Opsional. Kosongkan untuk memakai judul & ringkasan.</p>
                </div>
                <div class="rounded-xl border border-stone-200 bg-white p-4">
                    <p class="truncate text-xs text-stone-500">{{ str_replace(['https://', 'http://'], '', url('/artikel')) }}/<span x-text="slug || '...'"></span></p>
                    <p class="mt-1 truncate text-lg leading-snug text-[#1a0dab]" x-text="(metaTitle || title || 'Judul artikel') + ' · {{ config('shop.name') }}'"></p>
                    <p class="mt-1 line-clamp-2 text-sm text-stone-600" x-text="metaDescription || excerpt || 'Ringkasan artikel akan tampil di sini.'"></p>
                </div>
                <x-form.input name="meta_title" label="Judul SEO" optional maxlength="70" x-model="metaTitle" :value="$article->meta_title" hint="Idealnya 50–60 karakter." />
                <x-form.textarea name="meta_description" label="Deskripsi SEO" optional maxlength="160" rows="2" x-model="metaDescription" :value="$article->meta_description" hint="Idealnya 120–160 karakter." />
            </div>
        </div>

        <div class="space-y-6">
            <div class="card space-y-5 p-6">
                <x-form.field label="Status" for="status" name="status">
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ([ArticleStatus::Draft->value => 'Simpan draf', ArticleStatus::Published->value => 'Terbitkan'] as $value => $label)
                            <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border px-3 py-2.5 text-sm font-semibold transition"
                                   :class="status === '{{ $value }}' ? 'border-brand-500 bg-brand-50 text-brand-800' : 'border-stone-200 text-stone-600 hover:bg-stone-50'">
                                <input type="radio" name="status" value="{{ $value }}" x-model="status" class="sr-only">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </x-form.field>
                <x-form.date name="published_at" label="Tanggal terbit" optional time :value="$article->published_at" x-model="publishedAt"
                    hint="Kosongkan = terbit sekarang. Isi tanggal mendatang untuk menjadwalkan." />
                <p x-show="scheduled" x-cloak class="flex items-start gap-2 rounded-xl bg-sky-50 p-3 text-xs text-sky-800">
                    <x-icon name="clock" class="size-4 shrink-0" /> Artikel akan tampil otomatis di toko pada tanggal tersebut.
                </p>
                <button class="btn btn-primary w-full py-3"
                        x-text="status === 'draft' ? 'Simpan draf' : (scheduled ? 'Jadwalkan' : ({{ $editing && $article->isVisible() ? 'true' : 'false' }} ? 'Simpan perubahan' : 'Terbitkan sekarang'))">
                    {{ $editing ? 'Simpan perubahan' : 'Simpan' }}
                </button>
                @if ($editing)
                    <p class="text-center text-xs text-stone-400">Terakhir diubah {{ $article->updated_at->diffForHumans() }}</p>
                @endif
            </div>

            <div class="card p-6">
                <x-form.image-upload name="cover_image" label="Gambar sampul" optional :current="$article->coverUrl()"
                    remove-name="remove_cover_image" aspect="aspect-[16/9]" :max-kb="4096"
                    hint="Rasio 16:9, mis. 1600×900 px. Juga dipakai saat dibagikan ke WhatsApp/Facebook." />
            </div>

            <div class="card space-y-5 p-6">
                <x-form.select name="article_category_id" label="Kategori" optional :options="$categories" :selected="$article->article_category_id" placeholder="Tanpa kategori"
                    :hint="$categories->isEmpty() ? 'Belum ada kategori. Tambah di menu Kategori Artikel.' : null" />

                <x-form.field label="Tag" for="tags" name="tags" optional hint="Ketik lalu tekan Enter. Maks. 10 tag.">
                    <select id="tags" name="tags[]" multiple data-placeholder="mis. baterai, tips"
                            x-data x-searchable="{ settings: { create: true, persist: false, createOnBlur: true, maxItems: 10 } }" class="input">
                        @foreach ($tagOptions as $tag)
                            <option value="{{ $tag }}" @selected(in_array($tag, $selectedTags, true))>{{ $tag }}</option>
                        @endforeach
                    </select>
                </x-form.field>

                <x-form.field label="Produk terkait" for="product_ids" name="product_ids" optional hint="Tampil di akhir artikel supaya pembaca bisa langsung belanja. Maks. 8.">
                    <select id="product_ids" name="product_ids[]" multiple data-placeholder="Cari produk..."
                            x-data x-searchable="{ settings: { maxItems: 8 } }" class="input">
                        @foreach ($products as $id => $name)
                            <option value="{{ $id }}" @selected(in_array($id, $selectedProducts, true))>{{ $name }}</option>
                        @endforeach
                    </select>
                </x-form.field>
            </div>
        </div>
    </form>
</x-layouts.admin>
