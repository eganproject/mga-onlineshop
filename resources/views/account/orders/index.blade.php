<x-layouts.account title="Pesanan Saya" heading="Pesanan saya">
    <form method="GET" class="mb-4">
        @if ($status)<input type="hidden" name="status" value="{{ $status->value }}">@endif
        <div class="relative max-w-sm">
            <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-stone-400" />
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari kode pesanan atau nama produk..." class="input pl-10" aria-label="Cari pesanan">
        </div>
    </form>
    <div class="-mx-4 mb-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <div class="inline-flex gap-1 rounded-2xl bg-white p-1 ring-1 ring-stone-200/70">
            <a href="{{ route('account.orders.index', array_filter(['q' => request('q')])) }}" @class(['whitespace-nowrap rounded-xl px-3.5 py-2.5 text-sm font-semibold lg:py-2', 'bg-stone-900 text-white' => ! $status, 'text-stone-600 hover:bg-stone-50' => $status])>Semua</a>
            @foreach (\App\Enums\OrderStatus::cases() as $case)
                <a href="{{ route('account.orders.index', array_filter(['status' => $case->value, 'q' => request('q')])) }}" @class(['whitespace-nowrap rounded-xl px-3.5 py-2.5 text-sm font-semibold lg:py-2', 'bg-stone-900 text-white' => $status === $case, 'text-stone-600 hover:bg-stone-50' => $status !== $case])>{{ $case->label() }}</a>
            @endforeach
        </div>
    </div>

    @if ($orders->isEmpty())
        <div class="card">
            <x-empty-state icon="receipt" title="Belum ada pesanan" description="Pesanan dengan status ini belum ada.">
                <a href="{{ route('products.index') }}" class="btn btn-ink rounded-full">Mulai belanja</a>
            </x-empty-state>
        </div>
    @else
    @fragment('infinite-list')
    <x-infinite-list :paginator="$orders" label="pesanan" skeleton="order" :skeleton-count="2" items-class="space-y-4">
        @foreach ($orders as $order)
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
                    @if ($order->items->first()?->product)
                        <x-product-image :product="$order->items->first()->product" class="hidden size-16 shrink-0 rounded-xl sm:block" />
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-stone-900">{{ $order->items->first()?->product_name }}@if ($order->items->first()?->variant_name) <span class="font-normal text-stone-500">· {{ $order->items->first()->variant_name }}</span>@endif</p>
                        <p class="text-sm text-stone-500">
                            {{ $order->items->first()?->quantity }} barang
                            @if ($order->items->count() > 1) · +{{ $order->items->count() - 1 }} produk lainnya @endif
                        </p>
                        @if ($order->tracking_number && $order->status->isTrackable())
                            <p class="mt-1 text-xs text-stone-500">{{ $order->courierLabel() }} · <span class="font-mono">{{ $order->tracking_number }}</span></p>
                        @endif
                    </div>
                    <div class="flex items-center justify-between gap-4 sm:justify-end">
                        <div class="sm:text-right"><p class="text-xs text-stone-500">Total belanja</p><p class="font-bold text-stone-900">@rupiah($order->total)</p></div>
                        <a href="{{ route('account.orders.show', $order) }}" class="btn {{ in_array($order->status, [\App\Enums\OrderStatus::PendingPayment, \App\Enums\OrderStatus::Shipped, \App\Enums\OrderStatus::Delivered], true) ? 'btn-primary' : 'btn-secondary' }}">
                            {{ match ($order->status) {
                                \App\Enums\OrderStatus::PendingPayment => 'Bayar',
                                \App\Enums\OrderStatus::Shipped, \App\Enums\OrderStatus::Delivered => 'Lacak',
                                default => 'Detail',
                            } }}
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </x-infinite-list>
    @endfragment
    @endif
</x-layouts.account>
