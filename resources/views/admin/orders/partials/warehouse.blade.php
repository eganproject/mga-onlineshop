{{-- Status outbound manual di warehouse29 (pesanan website & penjualan manual). Variabel: $order. --}}
@php([$label, $badge] = $order->outboundStatusLabel())
<div class="card p-5 text-sm">
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-1.5">
            <h2 class="font-bold text-stone-900">Gudang</h2>
            <x-tooltip label="Gudang" text="Pesanan yang sudah lunas (website & penjualan manual) dikirim ke warehouse29 sebagai Outbound Manual. Stok gudang terpotong setelah admin gudang menyetujuinya." />
        </div>
        <span class="badge {{ $badge }}">{{ $label }}</span>
    </div>
    <dl class="mt-3 space-y-2">
        @if ($order->warehouse_outbound_code)
            <div class="flex justify-between gap-3"><dt class="text-stone-500">Kode gudang</dt><dd class="text-right font-mono text-xs font-semibold">{{ $order->warehouse_outbound_code }}</dd></div>
        @endif
        @if ($order->warehouse_outbound_sent_at)
            <div class="flex justify-between gap-3"><dt class="text-stone-500">Dikirim</dt><dd>{{ $order->warehouse_outbound_sent_at->translatedFormat('d M Y, H:i') }}</dd></div>
        @endif
        @if ($order->warehouse_outbound_approved_at)
            <div class="flex justify-between gap-3"><dt class="text-stone-500">Disetujui</dt><dd>{{ $order->warehouse_outbound_approved_at->translatedFormat('d M Y, H:i') }}</dd></div>
        @endif
    </dl>
    @if ($order->warehouse_outbound_error)
        <p class="mt-3 rounded-xl bg-rose-50 p-3 text-xs text-rose-700">{{ $order->warehouse_outbound_error }}</p>
    @endif
    @if (! $order->paid_at)
        <p class="mt-3 text-xs text-stone-500">Dikirim otomatis ke gudang setelah pesanan lunas.</p>
    @elseif ($order->warehouse_outbound_status === \App\Models\Order::OUTBOUND_PENDING && $order->status === \App\Enums\OrderStatus::Cancelled)
        <p class="mt-3 rounded-xl bg-amber-50 p-3 text-xs text-amber-800">Pesanan dibatalkan, tapi outbound di gudang masih pending. Minta admin gudang menghapusnya.</p>
    @endif

    @can('orders.create')
        <div class="mt-4 flex flex-wrap gap-2">
            @if ($order->paid_at && ! in_array($order->warehouse_outbound_status, [\App\Models\Order::OUTBOUND_PENDING, \App\Models\Order::OUTBOUND_APPROVED], true))
                <form method="POST" action="{{ route('admin.orders.warehouse.send', $order) }}">
                    @csrf
                    <button class="btn btn-secondary btn-sm"><x-icon name="upload" class="size-4" /> {{ $order->warehouse_outbound_status ? 'Kirim ulang' : 'Kirim ke gudang' }}</button>
                </form>
            @endif
            @if (in_array($order->warehouse_outbound_status, [\App\Models\Order::OUTBOUND_PENDING, \App\Models\Order::OUTBOUND_APPROVED], true))
                <form method="POST" action="{{ route('admin.orders.warehouse.refresh', $order) }}">
                    @csrf
                    <button class="btn btn-ghost btn-sm"><x-icon name="refresh" class="size-4" /> Cek status gudang</button>
                </form>
            @endif
        </div>
    @endcan
</div>
