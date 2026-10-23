<x-layouts.admin title="Pesanan">
    <x-admin.page-header title="Pesanan" description="Pantau dan proses pesanan pelanggan." />

    {{-- Tab status --}}
    <div class="-mx-4 mb-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <div class="inline-flex gap-1 rounded-2xl bg-white p-1 ring-1 ring-stone-200/70">
            <a href="{{ route('admin.orders.index', request()->except('status', 'page')) }}"
               @class(['whitespace-nowrap rounded-xl px-3.5 py-2 text-sm font-semibold transition', 'bg-stone-900 text-white' => ! $status, 'text-stone-600 hover:bg-stone-50' => $status])>
                Semua <span class="ml-1 opacity-60">{{ $counts->sum() }}</span>
            </a>
            @foreach (\App\Enums\OrderStatus::cases() as $case)
                <a href="{{ route('admin.orders.index', ['status' => $case->value] + request()->except('status', 'page')) }}"
                   @class(['whitespace-nowrap rounded-xl px-3.5 py-2 text-sm font-semibold transition', 'bg-stone-900 text-white' => $status === $case, 'text-stone-600 hover:bg-stone-50' => $status !== $case])>
                    {{ $case->label() }} <span class="ml-1 opacity-60">{{ $counts[$case->value] ?? 0 }}</span>
                </a>
            @endforeach
        </div>
    </div>

    <div class="card overflow-hidden">
        <form method="GET" class="border-b border-stone-100 p-4">
            @if ($status)<input type="hidden" name="status" value="{{ $status->value }}">@endif
            <div class="relative max-w-sm">
                <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-stone-400" />
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Kode pesanan, nama, atau email..." class="input pl-10">
            </div>
        </form>

        @if ($orders->isEmpty())
            <x-empty-state icon="receipt" title="Tidak ada pesanan" description="Belum ada pesanan yang cocok dengan filter ini." />
        @else
            <div class="relative overflow-x-auto">
                <table class="table-base">
                    <thead class="bg-stone-50/70"><tr><th>Pesanan</th><th>Pelanggan</th><th>Item</th><th>Total</th><th>Kurir</th><th>Status</th><th></th></tr></thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($orders as $order)
                            <tr class="hover:bg-stone-50/50">
                                <td class="whitespace-nowrap"><p class="font-semibold text-stone-900">{{ $order->code }}</p><p class="text-xs text-stone-500">{{ $order->created_at->translatedFormat('d M Y, H:i') }}</p></td>
                                <td><p class="font-medium text-stone-800">{{ $order->user->name }}</p><p class="text-xs text-stone-500">{{ $order->user->email }}</p></td>
                                <td>{{ $order->items_count }}</td>
                                <td class="whitespace-nowrap font-semibold">@rupiah($order->total)</td>
                                <td class="whitespace-nowrap text-xs">{{ $order->courierLabel() ?? '—' }}</td>
                                <td><x-order-status :status="$order->status" /></td>
                                <td class="text-right"><a href="{{ route('admin.orders.show', $order) }}" class="btn btn-secondary btn-sm">Detail</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-stone-100 p-4">{{ $orders->links() }}</div>
        @endif
    </div>
</x-layouts.admin>
