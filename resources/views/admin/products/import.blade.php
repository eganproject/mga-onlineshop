@use('App\Enums\ImportStatus')
@use('App\Enums\ImportType')
@use('App\Support\Money')
@php
    $fmt = fn (?int $n) => $n === null ? '—' : number_format($n, 0, ',', '.');
    $summary = $import->summary ?? [];
    $result = $import->result ?? [];
    $isStock = $import->type === ImportType::Stock;
    $problems = collect([...($import->errors ?? []), ...($result['failed'] ?? [])])->sortBy('row');
    $warnings = collect($result['warnings'] ?? []);
@endphp
<x-layouts.admin :title="$import->type->label()">
    <x-admin.page-header :title="$import->type->label()" :description="$import->filename.($import->mode ? ' · '.$modes[$import->mode] : '')" :back="route('admin.products.transfer')">
        <span class="badge {{ $import->status->badgeClass() }}">{{ $import->status->label() }}</span>
    </x-admin.page-header>

    @if ($import->status->isRunning())
        <div class="card flex items-center gap-4 p-6" x-data x-init="setTimeout(() => window.location.reload(), 3000)">
            <span class="size-6 shrink-0 animate-spin rounded-full border-2 border-stone-200 border-t-brand-600"></span>
            <div>
                <p class="font-semibold text-stone-900">{{ $import->status === ImportStatus::Validating ? 'Memeriksa isi file...' : 'Menyimpan data...' }}</p>
                <p class="text-sm text-stone-500">File besar diproses di latar belakang. Halaman ini memuat ulang otomatis; Anda boleh meninggalkannya.</p>
            </div>
        </div>
    @elseif ($import->status === ImportStatus::Failed)
        <div class="flex gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
            <x-icon name="x-circle" class="size-5 shrink-0" />
            <div>
                <p class="font-semibold">Impor gagal</p>
                <p>{{ $import->failure }}</p>
            </div>
        </div>
    @endif

    @if (in_array($import->status, [ImportStatus::Ready, ImportStatus::Completed], true))
        {{-- Ringkasan --}}
        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-admin.stat-card label="Baris di file" :value="$fmt($import->total_rows)" icon="archive" />
            @if ($isStock)
                <x-admin.stat-card :label="$import->status === ImportStatus::Completed ? 'Stok diperbarui' : 'Akan diubah'" :value="$fmt($result['changed'] ?? $summary['changed'] ?? 0)" icon="check-circle" tone="emerald" :hint="($summary['unchanged'] ?? 0).' baris tanpa perubahan'" />
                <x-admin.stat-card label="Unit masuk / keluar" :value="'+'.$fmt($summary['units_in'] ?? 0).' / −'.$fmt($summary['units_out'] ?? 0)" icon="trending-up" tone="sky" />
            @else
                <x-admin.stat-card :label="$import->status === ImportStatus::Completed ? 'Produk baru dibuat' : 'Produk baru'" :value="$fmt($result['created'] ?? $summary['create'] ?? 0)" icon="plus" tone="emerald" :hint="($summary['variants'] ?? 0).' varian'" />
                <x-admin.stat-card label="Produk diperbarui" :value="$fmt($result['updated'] ?? $summary['update'] ?? 0)" icon="pencil" tone="sky" />
            @endif
            <x-admin.stat-card label="Baris error (dilewati)" :value="$fmt($import->error_rows + count($result['failed'] ?? []))" icon="warning" tone="violet" />
        </div>

        @if (! $isStock && ! empty($summary['new_categories']) && $import->status === ImportStatus::Ready)
            <div class="mb-6 flex gap-3 rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800">
                <x-icon name="info" class="size-5 shrink-0" />
                <p>Kategori baru yang akan dibuat: <strong>{{ implode(', ', $summary['new_categories']) }}</strong>.</p>
            </div>
        @endif

        @if ($import->status === ImportStatus::Ready)
            <div class="card mb-6 flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm">
                    @if ($import->canCommit())
                        <p class="font-semibold text-stone-900">File sudah diperiksa. {{ $fmt($import->validRows()) }} baris siap diimpor.</p>
                        <p class="text-stone-500">
                            @if ($import->error_rows)
                                {{ $fmt($import->error_rows) }} baris error akan dilewati. Perbaiki lalu unggah ulang bila perlu.
                            @else
                                Tidak ada error. Data baru disimpan setelah Anda menekan tombol impor.
                            @endif
                        </p>
                    @else
                        <p class="font-semibold text-rose-700">Tidak ada baris yang bisa diimpor.</p>
                        <p class="text-stone-500">Perbaiki error di bawah, lalu unggah ulang file.</p>
                    @endif
                </div>
                <div class="flex shrink-0 gap-2">
                    <form method="POST" action="{{ route('admin.imports.destroy', $import) }}"
                          data-confirm-title="Batalkan impor?" data-confirm-ok="Ya, batalkan"
                          data-confirm="File ini tidak akan diimpor. Data katalog tidak berubah.">
                        @csrf @method('DELETE')
                        <button class="btn btn-secondary">Batalkan</button>
                    </form>
                    @if ($import->canCommit())
                        <form method="POST" action="{{ route('admin.imports.commit', $import) }}" x-data="{ busy: false }" @submit="busy = true"
                              data-confirm-title="Impor sekarang?" data-confirm-ok="Ya, impor" data-confirm-tone="primary"
                              data-confirm="{{ number_format($import->total_rows - $import->error_rows, 0, ',', '.') }} baris akan diterapkan ke {{ $isStock ? 'stok' : 'katalog' }}. Langkah ini tidak bisa dibatalkan otomatis.">
                            @csrf
                            <button class="btn btn-primary" :disabled="busy"><x-icon name="check" class="size-4" /> <span x-text="busy ? 'Mengimpor...' : 'Impor sekarang'">Impor sekarang</span></button>
                        </form>
                    @endif
                </div>
            </div>
        @elseif ($import->status === ImportStatus::Completed)
            <div class="mb-6 flex gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                <x-icon name="check-circle" class="size-5 shrink-0" />
                <p>
                    Impor selesai {{ $import->completed_at?->translatedFormat('d M Y H:i') }}.
                    @if (! $isStock && ($result['categories'] ?? 0)) {{ $result['categories'] }} kategori baru dibuat. @endif
                    <a href="{{ $isStock ? route('admin.stock.history') : route('admin.products.index') }}" class="font-semibold underline">{{ $isStock ? 'Lihat riwayat stok' : 'Lihat daftar produk' }}</a>
                </p>
            </div>
        @endif
    @endif

    {{-- Error per baris --}}
    @if ($problems->isNotEmpty() || $warnings->isNotEmpty())
        <div class="card mb-6 overflow-hidden">
            <div class="border-b border-stone-100 px-6 py-4">
                <h2 class="font-bold text-stone-900">Perlu diperbaiki</h2>
                <p class="text-sm text-stone-500">
                    Nomor baris sesuai dengan file Anda.
                    @unless ($isStock) Produk yang salah satu barisnya error dilewati seluruhnya. @endunless
                    @if (count($import->errors ?? []) >= \App\Services\Catalog\Transfer\Importer::MAX_ERRORS) Menampilkan {{ count($import->errors) }} error pertama. @endif
                </p>
            </div>
            <div class="max-h-96 overflow-y-auto">
                <table class="table-base">
                    <thead class="sticky top-0 bg-stone-50"><tr><th class="w-24">Baris</th><th>Keterangan</th></tr></thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($problems as $problem)
                            <tr><td class="font-mono text-stone-500">{{ $problem['row'] }}</td><td class="text-rose-700">{{ $problem['message'] }}</td></tr>
                        @endforeach
                        @foreach ($warnings as $warning)
                            <tr><td class="font-mono text-stone-500">{{ $warning['row'] }}</td><td class="text-amber-700">{{ $warning['message'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Pratinjau --}}
    @if (! empty($import->preview) && in_array($import->status, [ImportStatus::Ready, ImportStatus::Completed], true))
        <div class="card overflow-hidden">
            <div class="border-b border-stone-100 px-6 py-4">
                <h2 class="font-bold text-stone-900">Pratinjau</h2>
                <p class="text-sm text-stone-500">{{ count($import->preview) >= 100 ? '100 data pertama yang valid.' : 'Data valid yang '.($import->status === ImportStatus::Completed ? 'diimpor' : 'akan diimpor').'.' }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="table-base">
                    @if ($isStock)
                        <thead class="bg-stone-50/70"><tr><th>Baris</th><th>Produk</th><th>SKU</th><th class="text-right">Stok lama</th><th class="text-right">Stok baru</th></tr></thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach ($import->preview as $row)
                                <tr>
                                    <td class="font-mono text-stone-500">{{ $row['row'] }}</td>
                                    <td class="font-medium text-stone-900">{{ $row['name'] }}@if ($row['variant'])<span class="text-stone-500"> · {{ $row['variant'] }}</span>@endif</td>
                                    <td class="font-mono text-xs text-stone-500">{{ $row['sku'] ?? '—' }}</td>
                                    <td class="text-right text-stone-500">{{ $fmt($row['current']) }}</td>
                                    <td @class(['text-right font-semibold', 'text-emerald-700' => $row['after'] > $row['current'], 'text-rose-700' => $row['after'] < $row['current']])>{{ $fmt($row['after']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    @else
                        <thead class="bg-stone-50/70"><tr><th>Baris</th><th>Aksi</th><th>Produk</th><th>Kategori</th><th class="text-right">Varian</th><th class="text-right">Harga</th><th class="text-right">Stok</th></tr></thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach ($import->preview as $row)
                                <tr>
                                    <td class="font-mono text-stone-500">{{ $row['row'] }}</td>
                                    <td>
                                        @if ($row['action'] === 'create')
                                            <span class="badge bg-emerald-50 text-emerald-700 ring-emerald-600/20">Baru</span>
                                        @else
                                            <span class="badge bg-sky-50 text-sky-700 ring-sky-600/20">Ubah</span>
                                        @endif
                                    </td>
                                    <td class="font-medium text-stone-900">{{ $row['name'] }}</td>
                                    <td class="text-stone-600">{{ $row['category'] ?? '—' }}</td>
                                    <td class="text-right text-stone-600">{{ $row['variants'] ?: '—' }}</td>
                                    <td class="whitespace-nowrap text-right text-stone-900">{{ $row['price'] === null ? '—' : Money::rupiah($row['price']) }}</td>
                                    <td class="text-right text-stone-600">{{ $fmt($row['stock']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    @endif
                </table>
            </div>
        </div>
    @endif

    @if (in_array($import->status, [ImportStatus::Completed, ImportStatus::Failed], true))
        <div class="mt-6 flex justify-end">
            <form method="POST" action="{{ route('admin.imports.destroy', $import) }}"
                  data-confirm-title="Hapus riwayat impor?" data-confirm="Hanya catatan impor ini yang dihapus. Data produk/stok tidak berubah.">
                @csrf @method('DELETE')
                <button class="btn btn-ghost btn-sm text-stone-500"><x-icon name="trash" class="size-3.5" /> Hapus riwayat ini</button>
            </form>
        </div>
    @endif
</x-layouts.admin>
