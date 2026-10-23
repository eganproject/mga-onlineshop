<x-layouts.admin :title="$customer->name">
    <x-admin.page-header :title="$customer->name" :back="route('admin.customers.index')">
        @can('customers.manage')
        <form method="POST" action="{{ route('admin.customers.toggle', $customer) }}"
              data-confirm-title="{{ $customer->is_active ? 'Blokir '.$customer->name.'?' : 'Aktifkan kembali akun?' }}"
              data-confirm="{{ $customer->is_active ? 'Pelanggan tidak akan bisa login sampai akunnya diaktifkan lagi. Pesanan yang sudah ada tidak berubah.' : $customer->name.' bisa login dan berbelanja lagi.' }}"
              data-confirm-ok="{{ $customer->is_active ? 'Ya, blokir' : 'Ya, aktifkan' }}"
              data-confirm-tone="{{ $customer->is_active ? 'danger' : 'primary' }}">
            @csrf
            @method('PATCH')
            <button @class(['btn', 'btn-secondary text-rose-600' => $customer->is_active, 'btn-primary' => ! $customer->is_active])>
                {{ $customer->is_active ? 'Blokir akun' : 'Aktifkan akun' }}
            </button>
        </form>
        @endcan
    </x-admin.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <div class="card p-5 text-center">
                <span class="mx-auto grid size-16 place-items-center rounded-full bg-brand-100 text-xl font-bold text-brand-700">{{ $customer->initials() }}</span>
                <p class="mt-3 font-bold text-stone-900">{{ $customer->name }}</p>
                <p class="text-sm text-stone-500">{{ $customer->email }}</p>
                <p class="text-sm text-stone-500">{{ $customer->phone ?? 'No. HP belum diisi' }}</p>
                <div class="mt-5 grid grid-cols-2 gap-3 text-left">
                    <div class="rounded-xl bg-stone-50 p-3"><p class="text-xs text-stone-500">Total belanja</p><p class="font-bold text-stone-900">@rupiah($totalSpent)</p></div>
                    <div class="rounded-xl bg-stone-50 p-3"><p class="text-xs text-stone-500">Bergabung</p><p class="font-bold text-stone-900">{{ $customer->created_at->translatedFormat('M Y') }}</p></div>
                </div>
            </div>
            <div class="card p-5">
                <h2 class="font-bold text-stone-900">Alamat</h2>
                <ul class="mt-3 space-y-3 text-sm">
                    @forelse ($customer->addresses as $address)
                        <li class="rounded-xl border border-stone-200 p-3">
                            <p class="font-semibold text-stone-800">{{ $address->label }} @if($address->is_default)<span class="badge ml-1 bg-brand-50 text-brand-700 ring-brand-600/20">Utama</span>@endif</p>
                            <p class="text-stone-600">{{ $address->recipient_name }} · {{ $address->phone }}</p>
                            <p class="text-stone-500">{{ $address->fullAddress() }}</p>
                        </li>
                    @empty
                        <li class="text-stone-500">Belum ada alamat.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="card overflow-hidden lg:col-span-2">
            <div class="border-b border-stone-100 p-5"><h2 class="font-bold text-stone-900">Riwayat pesanan</h2></div>
            @if ($orders->isEmpty())
                <x-empty-state icon="receipt" title="Belum ada pesanan" />
            @else
                <div class="relative overflow-x-auto">
                    <table class="table-base">
                        <thead class="bg-stone-50/70"><tr><th>Pesanan</th><th>Total</th><th>Status</th><th></th></tr></thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach ($orders as $order)
                                <tr>
                                    <td><p class="font-semibold text-stone-900">{{ $order->code }}</p><p class="text-xs text-stone-500">{{ $order->created_at->translatedFormat('d M Y') }}</p></td>
                                    <td class="font-semibold">@rupiah($order->total)</td>
                                    <td><x-order-status :status="$order->status" /></td>
                                    <td class="text-right"><a href="{{ route('admin.orders.show', $order) }}" class="btn btn-ghost btn-sm">Detail</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <x-admin.pagination :paginator="$orders" label="pesanan" />
            @endif
        </div>
    </div>
</x-layouts.admin>
