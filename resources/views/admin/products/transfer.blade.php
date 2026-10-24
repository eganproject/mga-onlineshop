<x-layouts.admin title="Impor & ekspor produk">
    <x-admin.page-header title="Impor & ekspor" description="Tambah atau ubah banyak produk sekaligus, unduh katalog, dan update stok lewat file Excel / CSV." :back="auth()->user()->can('catalog.manage') ? route('admin.products.index') : route('admin.stock.index')" />

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Ekspor --}}
        <div class="card min-w-0 p-6" x-data="{ format: 'xlsx' }">
            <div class="flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-xl bg-sky-50 text-sky-700"><x-icon name="download" class="size-5" /></span>
                <div>
                    <h2 class="font-bold text-stone-900">Ekspor</h2>
                    <p class="text-sm text-stone-500">Unduh katalog atau daftar stok.</p>
                </div>
            </div>

            @if ($canProducts)
            <form method="GET" action="{{ route('admin.products.export') }}" class="mt-5 space-y-3">
                <x-form.category-select name="kategori" label="Kategori" :categories="$categories" placeholder="Semua kategori" />
                <x-form.select name="status" label="Status" :options="['aktif' => 'Hanya aktif', 'nonaktif' => 'Hanya nonaktif']" placeholder="Semua produk" />
                <fieldset>
                    <legend class="label">Format</legend>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (['xlsx' => 'Excel (.xlsx)', 'csv' => 'CSV'] as $value => $label)
                            <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-stone-200 px-3 py-2 text-sm has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                                <input type="radio" name="format" value="{{ $value }}" x-model="format" class="text-brand-600"> {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <button class="btn btn-primary w-full"><x-icon name="download" class="size-4" /> Ekspor produk</button>
            </form>
            @endif

            @if ($canStock)
            <div @class(['mt-4 border-t border-stone-100 pt-4' => $canProducts, 'mt-5' => ! $canProducts])>
                <a :href="@js(route('admin.stock.export')) + '?format=' + format" href="{{ route('admin.stock.export') }}" class="btn btn-secondary w-full">
                    <x-icon name="archive" class="size-4" /> Ekspor daftar stok
                </a>
                <p class="mt-2 text-xs text-stone-500">File stok bisa langsung diisi ulang lalu diunggah di "Update stok massal".</p>
            </div>
            @endif
        </div>

        {{-- Impor produk --}}
        @if ($canProducts)
        <div class="card min-w-0 p-6">
            <div class="flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-xl bg-emerald-50 text-emerald-700"><x-icon name="cube" class="size-5" /></span>
                <div>
                    <h2 class="font-bold text-stone-900">Impor produk</h2>
                    <p class="text-sm text-stone-500">Produk, varian, kategori, stok & foto.</p>
                </div>
            </div>

            <ol class="mt-5 space-y-2 text-sm text-stone-600">
                <li class="flex gap-2"><span class="font-bold text-stone-900">1.</span> Unduh template, atau ekspor produk untuk mengubah yang sudah ada.</li>
                <li class="flex gap-2"><span class="font-bold text-stone-900">2.</span> Isi satu baris per produk (atau per varian).</li>
                <li class="flex gap-2"><span class="font-bold text-stone-900">3.</span> Unggah, cek pratinjau, lalu jalankan impor.</li>
            </ol>

            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('admin.products.template', ['format' => 'xlsx']) }}" class="btn btn-secondary btn-sm"><x-icon name="download" class="size-3.5" /> Template Excel</a>
                <a href="{{ route('admin.products.template', ['format' => 'csv']) }}" class="btn btn-secondary btn-sm"><x-icon name="download" class="size-3.5" /> Template CSV</a>
            </div>

            <form method="POST" action="{{ route('admin.imports.store') }}" enctype="multipart/form-data" class="mt-5 space-y-3" x-data="{ busy: false }" @submit="busy = true">
                @csrf
                <input type="hidden" name="type" value="products">
                <x-form.file name="file" label="File produk" :extensions="['xlsx', 'csv']" :max-kb="$maxUploadKb" :bag="old('type', 'products') === 'products' ? 'default' : 'none'" required />
                <button class="btn btn-primary w-full" :disabled="busy">
                    <x-icon name="download" class="size-4 rotate-180" /> <span x-text="busy ? 'Memeriksa file...' : 'Unggah & periksa'">Unggah & periksa</span>
                </button>
            </form>
        </div>

        @endif

        {{-- Update stok massal --}}
        @if ($canStock)
        <div class="card min-w-0 p-6" id="stok">
            <div class="flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-xl bg-violet-50 text-violet-700"><x-icon name="archive" class="size-5" /></span>
                <div>
                    <h2 class="font-bold text-stone-900">Update stok massal</h2>
                    <p class="text-sm text-stone-500">Stock opname atau barang masuk sekaligus.</p>
                </div>
            </div>

            <p class="mt-5 text-sm text-stone-600">Kolom wajib: <code class="font-mono text-xs">sku</code> (atau <code class="font-mono text-xs">slug</code> + <code class="font-mono text-xs">nama_varian</code>) dan <code class="font-mono text-xs">stok</code>. Semua perubahan tercatat di riwayat stok.</p>

            <form method="POST" action="{{ route('admin.imports.store') }}" enctype="multipart/form-data" class="mt-5 space-y-3" x-data="{ busy: false }" @submit="busy = true">
                @csrf
                <input type="hidden" name="type" value="stock">
                <fieldset>
                    <legend class="label">Cara update</legend>
                    <div class="space-y-2">
                        @foreach ($modes as $value => $label)
                            <label class="flex cursor-pointer items-start gap-2 rounded-xl border border-stone-200 px-3 py-2 text-sm has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                                <input type="radio" name="mode" value="{{ $value }}" @checked(old('mode', 'set') === $value) class="mt-0.5 text-brand-600">
                                <span>
                                    <span class="font-semibold text-stone-800">{{ $label }}</span>
                                    <span class="block text-xs text-stone-500">{{ $value === 'set' ? 'Stok diganti dengan angka di file.' : 'Angka di file ditambahkan ke stok (minus untuk mengurangi).' }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <x-form.file name="file" id="stock-file" label="File stok" :extensions="['xlsx', 'csv']" :max-kb="$maxUploadKb" :bag="old('type') === 'stock' ? 'default' : 'none'" required />
                <button class="btn btn-primary w-full" :disabled="busy">
                    <x-icon name="download" class="size-4 rotate-180" /> <span x-text="busy ? 'Memeriksa file...' : 'Unggah & periksa'">Unggah & periksa</span>
                </button>
            </form>
        </div>
        @endif
    </div>

    {{-- Riwayat --}}
    <div id="riwayat" class="card mt-6 scroll-mt-24 overflow-hidden">
        <div class="border-b border-stone-100 px-6 py-4">
            <h2 class="font-bold text-stone-900">Riwayat impor</h2>
            <p class="text-sm text-stone-500">Terbaru di atas. Riwayat lebih dari 30 hari dihapus otomatis.</p>
        </div>
        @if ($imports->isEmpty())
            <x-empty-state icon="archive" title="Belum ada impor" description="File yang Anda unggah akan muncul di sini." />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead class="bg-stone-50/70"><tr><th>File</th><th>Jenis</th><th>Baris</th><th>Status</th><th>Waktu</th><th></th></tr></thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($imports as $import)
                            <tr>
                                <td class="max-w-64 truncate font-medium text-stone-900">{{ $import->filename }}</td>
                                <td class="whitespace-nowrap text-stone-600">{{ $import->type->label() }}{{ $import->mode ? ' · '.($import->mode === 'set' ? 'ganti' : 'tambah') : '' }}</td>
                                <td class="whitespace-nowrap text-stone-600">
                                    {{ number_format($import->total_rows, 0, ',', '.') }}
                                    @if ($import->error_rows)<span class="text-rose-600">· {{ $import->error_rows }} error</span>@endif
                                </td>
                                <td><span class="badge {{ $import->status->badgeClass() }}">{{ $import->status->label() }}</span></td>
                                <td class="whitespace-nowrap text-stone-500">{{ $import->created_at->translatedFormat('d M Y H:i') }} · {{ $import->user?->name ?? '—' }}</td>
                                <td class="text-right"><a href="{{ route('admin.imports.show', $import) }}" class="text-sm font-semibold text-brand-700 hover:text-brand-800">Lihat</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-admin.pagination :paginator="$imports" label="impor" />
        @endif
    </div>
</x-layouts.admin>
