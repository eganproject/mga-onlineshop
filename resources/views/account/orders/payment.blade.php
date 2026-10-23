@php
    $isMidtrans = $payment?->gateway === 'midtrans' && $payment->snap_token;
    $isFake = $payment?->gateway === 'fake' && ! app()->isProduction();
@endphp
<x-layouts.account :title="'Pembayaran '.$order->code">
    <a href="{{ route('account.orders.show', $order) }}" class="mb-3 inline-flex items-center gap-1 text-sm font-medium text-stone-500 hover:text-stone-800"><x-icon name="arrow-left" class="size-4" /> Detail pesanan</a>

    <div class="grid gap-6 lg:grid-cols-5">
        <section class="lg:col-span-3">
            <div class="border border-stone-200 p-5 sm:p-8">
                <p class="eyebrow text-stone-500">Pembayaran · {{ $order->code }}</p>
                <p class="display mt-3 text-4xl leading-none sm:text-5xl">@rupiah($order->total)</p>

                <div class="mt-5 flex items-start gap-3 bg-sand-50 p-4 text-sm"
                     x-data="{
                         deadline: {{ $deadline->getTimestampMs() }},
                         left: '',
                         tick() {
                             const s = Math.max(0, Math.floor((this.deadline - Date.now()) / 1000));
                             const h = Math.floor(s / 3600), m = Math.floor(s % 3600 / 60), d = s % 60;
                             this.left = s ? [h, m, d].map(n => String(n).padStart(2, '0')).join(':') : 'waktu habis';
                         },
                     }"
                     x-init="tick(); setInterval(() => tick(), 1000)">
                    <x-icon name="clock" class="size-5 shrink-0 text-amber-600" />
                    <div>
                        <p class="font-semibold text-stone-900">Bayar sebelum {{ $deadline->timezone(config('app.timezone'))->translatedFormat('d M Y, H:i') }} WIB</p>
                        <p class="text-stone-600">Sisa waktu <span class="font-mono font-bold text-ink" x-text="left"></span>. Lewat dari itu pesanan dibatalkan otomatis.</p>
                    </div>
                </div>

                @if ($error)
                    <div class="mt-5 flex gap-3 border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700" role="alert">
                        <x-icon name="warning" class="size-5 shrink-0" />
                        <div>
                            <p>{{ $error }}</p>
                            @if ($canRegenerate)
                                <a href="{{ route('account.orders.payment', $order) }}" class="mt-2 inline-flex items-center gap-1 font-semibold underline underline-offset-4"><x-icon name="refresh" class="size-4" /> Coba lagi</a>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                    @if ($isMidtrans)
                        <button type="button" id="pay-button" class="btn btn-ink btn-pill w-full sm:w-auto"
                                data-token="{{ $payment->snap_token }}" data-redirect="{{ $payment->redirect_url }}">
                            <x-icon name="credit-card" class="size-4" /> Bayar sekarang
                        </button>
                    @elseif ($isFake)
                        <form method="POST" action="{{ route('account.orders.simulate-payment', $order) }}">
                            @csrf
                            <button class="btn btn-ink btn-pill w-full sm:w-auto"><x-icon name="credit-card" class="size-4" /> Simulasikan bayar (dev)</button>
                        </form>
                    @endif

                    @if ($payment)
                        <form method="POST" action="{{ route('account.orders.payment.check', $order) }}" id="check-form">
                            @csrf
                            <button class="btn btn-outline btn-pill w-full sm:w-auto"><x-icon name="refresh" class="size-4" /> Sudah bayar? Cek status</button>
                        </form>
                    @endif
                </div>

                @if ($isFake)
                    <p class="mt-4 text-xs text-stone-500">Mode development: PAYMENT_DRIVER=fake. Isi MIDTRANS_SERVER_KEY & MIDTRANS_CLIENT_KEY lalu set PAYMENT_DRIVER=midtrans untuk pembayaran asli.</p>
                @endif

                <div class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-3 border-t border-stone-200 pt-5 text-sm">
                    @if ($payment && $canRegenerate)
                        <form method="POST" action="{{ route('account.orders.payment.regenerate', $order) }}"
                              onsubmit="return confirm('Buat link pembayaran baru? Kode bayar / nomor VA sebelumnya tidak bisa dipakai lagi.')">
                            @csrf
                            <button class="link-underline font-semibold text-stone-600 hover:text-ink">Ganti metode pembayaran</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('account.orders.cancel', $order) }}" onsubmit="return confirm('Batalkan pesanan ini?')">
                        @csrf
                        <button class="link-underline font-semibold text-stone-500 hover:text-rose-600">Batalkan pesanan</button>
                    </form>
                </div>
            </div>

            <p class="mt-4 flex items-center gap-2 text-xs text-stone-500"><x-icon name="lock" class="size-4" /> Pembayaran diproses aman oleh Midtrans: transfer bank (VA), e-wallet, QRIS, dan kartu.</p>
        </section>

        <aside class="lg:col-span-2">
            <div class="border border-stone-200">
                <h2 class="border-b border-stone-200 p-5 font-display text-base font-extrabold tracking-tight uppercase">Ringkasan</h2>
                <ul class="divide-y divide-stone-100">
                    @foreach ($order->items as $item)
                        <li class="flex justify-between gap-3 px-5 py-3 text-sm">
                            <span class="min-w-0 text-stone-700">{{ $item->product_name }}@if ($item->variant_name) <span class="text-stone-500">({{ $item->variant_name }})</span>@endif × {{ $item->quantity }}</span>
                            <span class="shrink-0 font-semibold">@rupiah($item->subtotal)</span>
                        </li>
                    @endforeach
                </ul>
                <dl class="space-y-2 border-t border-stone-200 p-5 text-sm">
                    <div class="flex justify-between"><dt class="text-stone-500">Subtotal</dt><dd>@rupiah($order->subtotal)</dd></div>
                    <div class="flex justify-between"><dt class="text-stone-500">Ongkir {{ $order->courierLabel() }}</dt><dd>@rupiah($order->shipping_cost)</dd></div>
                    <x-order-discounts :order="$order" />
                    <div class="flex justify-between border-t border-stone-200 pt-2 text-base font-bold text-ink"><dt>Total</dt><dd>@rupiah($order->total)</dd></div>
                </dl>
            </div>
        </aside>
    </div>

    @if ($isMidtrans)
        <x-slot:scripts>
            <script src="{{ config('services.midtrans.is_production') ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js' }}" data-client-key="{{ config('services.midtrans.client_key') }}"></script>
            <script>
                document.getElementById('pay-button')?.addEventListener('click', (event) => {
                    const button = event.currentTarget;
                    // Snap gagal dimuat (diblokir/offline): pakai halaman pembayaran Midtrans.
                    if (! window.snap) {
                        window.location.href = button.dataset.redirect;
                        return;
                    }
                    const check = () => document.getElementById('check-form').submit();
                    window.snap.pay(button.dataset.token, { onSuccess: check, onPending: check });
                });
            </script>
        </x-slot:scripts>
    @endif
</x-layouts.account>
