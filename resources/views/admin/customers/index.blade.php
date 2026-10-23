<x-layouts.admin title="Pelanggan">
    <x-admin.page-header title="Pelanggan" description="Daftar pelanggan yang terdaftar di toko." />

    <div class="card overflow-hidden">
        <form method="GET" class="border-b border-stone-100 p-4">
            <div class="relative max-w-sm">
                <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-stone-400" />
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Nama, email, atau no. HP..." class="input pl-10">
            </div>
        </form>

        @if ($customers->isEmpty())
            <x-empty-state icon="users" title="Belum ada pelanggan" description="Pelanggan yang mendaftar akan muncul di sini." />
        @else
            <div class="relative overflow-x-auto">
                <table class="table-base">
                    <thead class="bg-stone-50/70"><tr><th>Pelanggan</th><th>No. HP</th><th>Pesanan</th><th>Total belanja</th><th>Status</th><th></th></tr></thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($customers as $customer)
                            <tr class="hover:bg-stone-50/50">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-brand-100 text-sm font-bold text-brand-700">{{ $customer->initials() }}</span>
                                        <div class="min-w-0"><p class="truncate font-semibold text-stone-900">{{ $customer->name }}</p><p class="truncate text-xs text-stone-500">{{ $customer->email }}</p></div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">{{ $customer->phone ?? '—' }}</td>
                                <td>{{ $customer->orders_count }}</td>
                                <td class="whitespace-nowrap font-semibold">@rupiah($customer->total_spent)</td>
                                <td>
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
            <div class="border-t border-stone-100 p-4">{{ $customers->links() }}</div>
        @endif
    </div>
</x-layouts.admin>
