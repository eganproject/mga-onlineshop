<x-layouts.admin title="Pelanggan">
    <x-admin.page-header title="Pelanggan" description="Daftar pelanggan yang terdaftar di toko." />

    <div class="card overflow-hidden">
        <form method="GET" class="flex flex-col gap-3 border-b border-stone-100 p-4 sm:flex-row" x-data @change="$el.submit()">
            <x-admin.keep-query />
            <div class="relative flex-1 sm:max-w-sm">
                <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-stone-400" />
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Nama, email, atau no. HP..." class="input pl-10" aria-label="Cari pelanggan">
            </div>
            <select name="status" class="input sm:w-44" x-searchable aria-label="Filter status">
                <option value="">Semua status</option>
                <option value="aktif" @selected(request('status') === 'aktif')>Aktif</option>
                <option value="diblokir" @selected(request('status') === 'diblokir')>Diblokir</option>
            </select>
        </form>

        @if ($customers->isEmpty())
            @if (request()->hasAny(['q', 'status']))
                <x-empty-state icon="search" title="Pelanggan tidak ditemukan" description="Coba kata kunci lain atau hapus filter.">
                    <a href="{{ route('admin.customers.index') }}" class="btn btn-secondary">Hapus filter</a>
                </x-empty-state>
            @else
                <x-empty-state icon="users" title="Belum ada pelanggan" description="Pelanggan yang mendaftar akan muncul di sini." />
            @endif
        @else
            <div class="relative overflow-x-auto">
                <table class="table-base">
                    <thead class="bg-stone-50/70"><tr><th><x-admin.sort-link column="nama">Pelanggan</x-admin.sort-link></th><th class="hidden sm:table-cell">No. HP</th><th class="hidden sm:table-cell"><x-admin.sort-link column="pesanan" desc>Pesanan</x-admin.sort-link></th><th class="hidden sm:table-cell"><x-admin.sort-link column="belanja" desc>Total belanja</x-admin.sort-link></th><th class="hidden sm:table-cell"><x-admin.sort-link column="terdaftar" desc>Terdaftar</x-admin.sort-link></th><th class="hidden sm:table-cell">Status</th><th></th></tr></thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($customers as $customer)
                            <tr class="hover:bg-stone-50/50">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-brand-100 text-sm font-bold text-brand-700">{{ $customer->initials() }}</span>
                                        <div class="min-w-0 max-sm:max-w-[calc(100vw-13rem)]"><p class="truncate font-semibold text-stone-900">{{ $customer->name }}</p><p class="truncate text-xs text-stone-500">{{ $customer->email }}</p><p class="mt-0.5 text-xs text-stone-600 sm:hidden">{{ $customer->orders_count }} pesanan · <span class="font-semibold text-stone-900">@rupiah($customer->total_spent)</span>@unless ($customer->is_active) · <span class="font-semibold text-rose-600">Diblokir</span>@endunless</p></div>
                                    </div>
                                </td>
                                <td class="hidden whitespace-nowrap sm:table-cell">{{ $customer->phone ?? '—' }}</td>
                                <td class="hidden sm:table-cell">{{ $customer->orders_count }}</td>
                                <td class="hidden whitespace-nowrap font-semibold sm:table-cell">@rupiah($customer->total_spent)</td>
                                <td class="hidden whitespace-nowrap text-xs text-stone-500 sm:table-cell">{{ $customer->created_at->translatedFormat('d M Y') }}</td>
                                <td class="hidden sm:table-cell">
                                    @if ($customer->is_active)
                                        <span class="badge bg-emerald-50 text-emerald-700 ring-emerald-600/20">Aktif</span>
                                    @else
                                        <span class="badge bg-rose-50 text-rose-700 ring-rose-600/20">Diblokir</span>
                                    @endif
                                </td>
                                <td class="text-right"><a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-secondary btn-sm">Detail</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-admin.pagination :paginator="$customers" label="pelanggan" />
        @endif
    </div>
</x-layouts.admin>
