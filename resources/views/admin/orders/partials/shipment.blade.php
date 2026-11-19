{{-- Pesan kurir lewat Biteship + status pengirimannya. Variabel: $order, $shipment ['blocker', 'instant']. --}}
@php([$label, $badge] = \App\Services\Shipping\BiteshipShipment::statusLabel($order->shipment_status))
<div class="card p-5 text-sm">
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-1.5">
            <h2 class="font-bold text-stone-900">Pengiriman Biteship</h2>
            <x-tooltip label="Pengiriman Biteship" text="Memesan kurir lewat Biteship untuk pesanan ini. Nomor resi terisi otomatis, pesanan menjadi Dikirim, dan label resi bisa langsung dicetak." :tips="['Biaya kirim dipotong dari saldo Biteship.', 'Pembatalan pengiriman dilakukan di dashboard Biteship.']" />
        </div>
        <span class="badge {{ $order->shipment_id ? $badge : 'bg-stone-100 text-stone-600 ring-stone-500/20' }}">{{ $order->shipment_id ? ($order->shipment_status ? $label : 'Dibuat') : 'Belum dibuat' }}</span>
    </div>

    @if ($order->shipment_id)
        <dl class="mt-3 space-y-2">
            <div class="flex justify-between gap-3"><dt class="text-stone-500">Kurir</dt><dd class="text-right font-semibold">{{ $order->courierLabel() ?: '—' }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-stone-500">Nomor resi</dt><dd class="text-right font-mono text-xs font-semibold break-all">{{ $order->tracking_number ?: 'Belum terbit' }}</dd></div>
            @if ($order->shipment_price !== null)
                <div class="flex justify-between gap-3"><dt class="text-stone-500">Biaya di Biteship</dt><dd>{{ \App\Support\Money::rupiah($order->shipment_price) }}</dd></div>
            @endif
            @if ($order->shipment_method)
                <div class="flex justify-between gap-3"><dt class="text-stone-500">Serah terima</dt><dd>{{ $order->shipment_method === \App\Services\Shipping\BiteshipShipment::METHOD_DROP_OFF ? 'Diantar ke gerai kurir' : 'Dijemput kurir' }}</dd></div>
            @endif
            @if ($order->shipment_booked_at)
                <div class="flex justify-between gap-3"><dt class="text-stone-500">Dibuat</dt><dd>{{ $order->shipment_booked_at->translatedFormat('d M Y, H:i') }}</dd></div>
            @endif
        </dl>
        <div class="mt-4 flex flex-wrap gap-2">
            @if ($order->tracking_number)
                <a href="{{ route('admin.orders.shipping-label', $order) }}" target="_blank" class="btn btn-primary btn-sm"><x-icon name="printer" class="size-4" /> Cetak label resi</a>
            @endif
            @can('orders.manage')
                <form method="POST" action="{{ route('admin.orders.shipment.refresh', $order) }}">
                    @csrf
                    <button class="btn btn-ghost btn-sm"><x-icon name="refresh" class="size-4" /> Cek status</button>
                </form>
            @endcan
            @if ($order->shipment_link)
                <a href="{{ $order->shipment_link }}" target="_blank" rel="noopener noreferrer" class="btn btn-ghost btn-sm"><x-icon name="external" class="size-4" /> Lacak di kurir</a>
            @endif
        </div>
    @endif

    @if ($shipment['blocker'])
        @unless ($order->shipment_id)
            <p class="mt-3 text-xs text-stone-500">{{ $shipment['blocker'] }}</p>
        @endunless
    @else
        @if ($order->shipment_id)
            <p class="mt-4 rounded-xl bg-amber-50 p-3 text-xs text-amber-800">Pengiriman sebelumnya tidak berjalan. Kurir bisa dipesan ulang.</p>
        @endif
        <p class="mt-3 text-xs text-stone-500">
            {{ $order->courierLabel() }} · {{ number_format($order->total_weight / 1000, 2, ',', '.') }} kg · ongkir dibayar pembeli {{ \App\Support\Money::rupiah($order->shipping_cost) }}
        </p>
        @can('orders.manage')
            <form method="POST" action="{{ route('admin.orders.shipment.store', $order) }}" class="mt-3 space-y-3"
                data-confirm="Kurir {{ $order->courierLabel() }} akan dipesan sekarang dan biaya kirim (sekitar {{ \App\Support\Money::rupiah($order->shipping_cost) }}) dipotong dari saldo Biteship. Pastikan paket sudah siap."
                data-confirm-title="Buat pengiriman Biteship?" data-confirm-ok="Ya, pesan kurir" data-confirm-tone="primary">
                @csrf
                @if ($shipment['instant'])
                    <input type="hidden" name="method" value="{{ \App\Services\Shipping\BiteshipShipment::METHOD_PICKUP }}">
                    <p class="rounded-xl bg-sky-50 p-3 text-xs text-sky-800">Kurir instan langsung mencari driver untuk menjemput paket di gudang.</p>
                @else
                    <x-form.select name="method" label="Serah terima paket" tooltip="Dijemput = kurir datang ke alamat gudang. Diantar = staf membawa paket ke gerai kurir. Tidak semua kurir mendukung keduanya.">
                        <option value="{{ \App\Services\Shipping\BiteshipShipment::METHOD_PICKUP }}">Dijemput kurir di gudang</option>
                        <option value="{{ \App\Services\Shipping\BiteshipShipment::METHOD_DROP_OFF }}">Diantar ke gerai kurir</option>
                    </x-form.select>
                @endif
                <button class="btn btn-primary btn-sm w-full"><x-icon name="truck" class="size-4" /> Buat pengiriman Biteship</button>
            </form>
        @else
            <p class="mt-3 text-xs text-stone-500">Pengiriman dibuat oleh staf yang berhak memproses pesanan.</p>
        @endcan
    @endif
</div>
