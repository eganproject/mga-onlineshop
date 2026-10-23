<x-layouts.account title="Pesanan Saya" heading="Pesanan saya">
    <div class="-mx-4 mb-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <div class="inline-flex gap-1 rounded-2xl bg-white p-1 ring-1 ring-stone-200/70">
            <a href="{{ route('account.orders.index') }}" @class(['whitespace-nowrap rounded-xl px-3.5 py-2 text-sm font-semibold', 'bg-stone-900 text-white' => ! $status, 'text-stone-600 hover:bg-stone-50' => $status])>Semua</a>
            @foreach (\App\Enums\OrderStatus::cases() as $case)
                <a href="{{ route('account.orders.index', ['status' => $case->value]) }}" @class(['whitespace-nowrap rounded-xl px-3.5 py-2 text-sm font-semibold', 'bg-stone-900 text-white' => $status === $case, 'text-stone-600 hover:bg-stone-50' => $status !== $case])>{{ $case->label() }}</a>
            @endforeach
        </div>
    </div>

    <div class="space-y-4">
        @forelse ($orders as $order)
            <div class="card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-stone-100 bg-stone-50/50 px-5 py-3 text-sm">
                    <div class="flex items-center gap-2">
                        <x-icon name="bag" class="size-4 text-stone-400" />
                        <span class="font-semibold text-stone-900">{{ $order->code }}</span>
                        <span class="text-stone-400">·</span>
                        <span class="text-stone-500">{{ $order->created_at->translatedFormat('d M Y') }}</span>
                    </div>
                    <x-order-status :status="$order->status" />
                </div>
                <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-stone-900">{{ $order->items->first()?->product_name }}</p>
                        <p class="text-sm text-stone-500">
                            {{ $order->items->first()?->quantity }} barang
                            @if ($order->items->count() > 1) · +{{ $order->items->count() - 1 }} produk lainnya @endif
                        </p>
                    </div>
                    <div class="flex items-center justify-between gap-4 sm:justify-end">
                        <div class="sm:text-right"><p class="text-xs text-stone-500">Total belanja</p><p class="font-bold text-stone-900">@rupiah($order->total)</p></div>
                        <a href="{{ route('account.orders.show', $order) }}" class="btn {{ $order->status === \App\Enums\OrderStatus::PendingPayment ? 'btn-primary' : 'btn-secondary' }}">
                            {{ $order->status === \App\Enums\OrderStatus::PendingPayment ? 'Bayar' : 'Detail' }}
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="card">
                <x-empty-state icon="receipt" title="Belum ada pesanan" description="Pesanan dengan status ini belum ada.">
                    <a href="{{ route('products.index') }}" class="btn btn-primary">Mulai belanja</a>
                </x-empty-state>
            </div>
        @endforelse
    </div>
    <div class="mt-6">{{ $orders->links() }}</div>
</x-layouts.account>
