{{--
    Tagihan pembayaran dari admin: pilih metode → QR / nomor VA langsung tampil,
    plus link tagihan untuk dibagikan ke pembeli (WhatsApp) atau dicetak.
    Variabel: $order, $billing = [payment, canCreate, options].
--}}
@php
    $payment = $billing['payment'];
    $payable = \App\Support\OrderSummary::payable($order, $payment);
    $isFake = $payment?->gateway === 'fake' && ! app()->isProduction();
    $billUrl = $order->billUrl();
    $phone = preg_replace('/\D+/', '', (string) $order->recipient_phone);
    $phone = str_starts_with($phone, '0') ? '62'.substr($phone, 1) : $phone;
    $waText = "Halo {$order->recipient_name}, berikut tagihan pesanan {$order->code} dari ".config('shop.full_name')
        .' sebesar '.\App\Support\Money::rupiah($payable).". Silakan bayar lewat link ini (QRIS / Virtual Account):\n{$billUrl}";
@endphp
<div id="tagihan" class="card scroll-mt-24 overflow-hidden" x-data="paymentPage({})">
    <div class="flex items-center justify-between gap-3 border-b border-stone-100 p-5">
        <div>
            <h2 class="font-bold text-stone-900">Tagihan pembayaran</h2>
            <p class="mt-0.5 text-xs text-stone-500">Bayar sebelum {{ $order->paymentDeadline()->timezone(config('app.timezone'))->translatedFormat('d M Y, H:i') }} WIB</p>
        </div>
        <x-tooltip label="Tagihan pembayaran" text="QRIS / nomor VA dibuat lewat Midtrans. Status pesanan berubah jadi Dibayar otomatis begitu pembeli membayar."
                   :tips="['Biaya transaksi mengikuti Admin › Pajak & Biaya.', 'Kartu kredit & PayLater hanya bisa dipilih pembeli sendiri lewat link tagihan.']" />
    </div>

    <div class="space-y-5 p-5">
        @if ($payment)
            <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    @if ($payment->paymentMethod())
                        <x-payment-badge :method="$payment->paymentMethod()" />
                    @endif
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-stone-900">{{ $payment->methodLabel() ?? 'Semua metode (Midtrans)' }}</p>
                        <p class="text-xs text-stone-500">@rupiah($payment->amount)@if ($payment->fee) · termasuk biaya @rupiah($payment->fee)@endif</p>
                    </div>
                </div>
            </div>

            @include('account.orders.partials.payment-instructions')
            <form id="check-form" method="POST" action="{{ route('admin.orders.payment.check', $order) }}" class="hidden">@csrf</form>

            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.orders.payment.check', $order) }}">
                    @csrf
                    <button class="btn btn-secondary btn-sm"><x-icon name="refresh" class="size-4" /> Cek status</button>
                </form>
                <form method="POST" action="{{ route('admin.orders.payment.reset', $order) }}"
                      data-confirm-title="Ganti metode pembayaran?" data-confirm-ok="Ya, ganti"
                      data-confirm="QR / nomor VA yang sekarang dibatalkan dan tidak bisa dipakai lagi.">
                    @csrf
                    <button class="btn btn-ghost btn-sm">Ganti metode</button>
                </form>
            </div>
        @elseif ($billing['canCreate'])
            @if ($billing['options']->isEmpty())
                <p class="rounded-xl bg-amber-50 p-3 text-sm text-amber-800">Belum ada metode QRIS / VA yang aktif. Aktifkan di Admin › Pajak & Biaya.</p>
            @else
                <form method="POST" action="{{ route('admin.orders.payment.choose', $order) }}" class="space-y-3" x-data="{ method: @js(old('method')) }">
                    @csrf
                    <p class="text-sm text-stone-600">Pilih metode, lalu tunjukkan QR / nomor VA ke pembeli atau kirim link tagihan.</p>
                    <x-input-error for="method" />
                    @foreach ($billing['options'] as $group => $options)
                        <fieldset>
                            <legend class="mb-1.5 text-xs font-bold tracking-wider text-stone-500 uppercase">{{ $group }}</legend>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach ($options as $option)
                                    @php($m = $option['method'])
                                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-stone-200 p-2.5 text-sm transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                                        <input type="radio" name="method" value="{{ $m->value }}" x-model="method" class="sr-only" required>
                                        <x-payment-badge :method="$m" />
                                        <span class="min-w-0 flex-1">
                                            <span class="block font-semibold text-stone-800">{{ $m->shortLabel() }}</span>
                                            <span class="block text-xs text-stone-500">{{ $option['fee'] ? '+ '.\App\Support\Money::rupiah($option['fee']).' dari pembeli' : 'Tanpa biaya pembeli' }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                    <button class="btn btn-primary w-full sm:w-auto" x-bind:disabled="!method"><x-icon name="qr-code" class="size-4" /> Buat tagihan</button>
                </form>
            @endif
        @else
            <p class="rounded-xl bg-rose-50 p-3 text-sm text-rose-700">Batas bayar sudah lewat. Pesanan akan dibatalkan otomatis.</p>
        @endif

        <div class="space-y-2 border-t border-stone-100 pt-4">
            <div class="label-row">
                <label for="bill-url" class="label">Link tagihan untuk pembeli</label>
                <x-tooltip label="Link tagihan" text="Pembeli bisa membuka link ini tanpa login untuk memilih metode bayar, melihat QR / nomor VA, dan mencetak tagihan." />
            </div>
            <div class="flex gap-2">
                <input id="bill-url" value="{{ $billUrl }}" readonly class="input min-w-0 flex-1 font-mono text-xs" x-on:focus="$el.select()">
                <button type="button" class="btn btn-secondary btn-sm shrink-0" x-on:click="copy(@js($billUrl), 'Link tagihan')">Salin</button>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($phone)
                    <a href="https://wa.me/{{ $phone }}?text={{ rawurlencode($waText) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><x-icon name="chat" class="size-4" /> Kirim via WhatsApp</a>
                @endif
                <a href="{{ $billUrl }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm"><x-icon name="printer" class="size-4" /> Buka & cetak</a>
            </div>
        </div>

        @can('payments.record')
            <a href="#catat-pembayaran" x-on:click="document.getElementById('catat-pembayaran')?.dispatchEvent(new CustomEvent('open-record'))"
               class="link-underline block border-t border-stone-100 pt-4 text-sm font-semibold text-stone-600 hover:text-ink">Dibayar tunai / transfer langsung / EDC? Catat pembayaran</a>
        @endcan
    </div>
</div>
