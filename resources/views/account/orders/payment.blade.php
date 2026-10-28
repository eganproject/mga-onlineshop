@php
    $usesSnap = $payment?->gateway === 'midtrans' && $payment->snap_token;
    $isFake = $payment?->gateway === 'fake' && ! app()->isProduction();
    $payable = \App\Support\OrderSummary::payable($order, $payment);
    $flatOptions = collect($methodOptions)->flatten(1);
    $hasFees = $flatOptions->contains(fn ($o) => $o['fee'] > 0);
@endphp
<x-layouts.account :title="'Pembayaran '.$order->code">
    <a href="{{ route('account.orders.show', $order) }}" class="mb-3 inline-flex items-center gap-1 text-sm font-medium text-stone-500 hover:text-stone-800"><x-icon name="arrow-left" class="size-4" /> Detail pesanan</a>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
        <section class="min-w-0 lg:col-span-3"
                 x-data="paymentPage({
                     statusUrl: @js($payment ? route('account.orders.payment.status', $order) : null),
                     amounts: @js($flatOptions->mapWithKeys(fn ($o) => [$o['method']->value => $o['amount']])),
                     labels: @js($flatOptions->mapWithKeys(fn ($o) => [$o['method']->value => $o['method']->shortLabel()])),
                     selected: @js(old('method')),
                 })">
            <div class="border border-stone-200 p-5 sm:p-8">
                <p class="eyebrow text-stone-500">Pembayaran · {{ $order->code }}</p>
                <p class="display mt-3 text-4xl leading-none sm:text-5xl">@rupiah($payable)</p>
                @if ($payment?->fee)
                    <p class="mt-2 text-sm text-stone-500">Termasuk {{ str(config('shop.billing.payment_fee_label') ?: 'biaya transaksi')->lower() }} {{ $payment->methodLabel() }} @rupiah($payment->fee)</p>
                @endif

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

                @if ($payment)
                    <div x-show="!changing" class="mt-6">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                @if ($payment->paymentMethod())
                                    <x-payment-badge :method="$payment->paymentMethod()" />
                                @endif
                                <div class="min-w-0">
                                    <p class="text-xs text-stone-500">Metode pembayaran</p>
                                    <p class="text-sm font-semibold text-stone-900">{{ $payment->methodLabel() ?? 'Semua metode (Midtrans)' }}</p>
                                </div>
                            </div>
                            @if ($canRegenerate && $methodOptions)
                                <button type="button" class="btn btn-outline btn-sm shrink-0 rounded-full px-4" x-on:click="changing = true">Ganti metode</button>
                            @endif
                        </div>

                        @include('account.orders.partials.payment-instructions')

                        <div class="mt-5 flex items-start gap-3 border border-brand-100 bg-brand-50/60 p-4 text-sm" role="status">
                            <span class="relative mt-1 flex size-2.5 shrink-0">
                                <span class="absolute inline-flex size-full animate-ping rounded-full bg-brand-400 opacity-75 motion-reduce:hidden"></span>
                                <span class="relative inline-flex size-2.5 rounded-full bg-brand-500"></span>
                            </span>
                            <p class="text-stone-700">Menunggu pembayaran. Halaman ini pindah otomatis begitu pembayaran kami terima.</p>
                        </div>

                        <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                            @if ($isFake)
                                <form method="POST" action="{{ route('account.orders.simulate-payment', $order) }}">
                                    @csrf
                                    <button class="btn btn-ink btn-pill w-full sm:w-auto"><x-icon name="credit-card" class="size-4" /> Simulasikan bayar (dev)</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('account.orders.payment.check', $order) }}" id="check-form">
                                @csrf
                                <button class="btn btn-outline btn-pill w-full sm:w-auto"><x-icon name="refresh" class="size-4" /> Sudah bayar? Cek status</button>
                            </form>
                        </div>
                    </div>

                    @if ($canRegenerate && $methodOptions)
                        <div x-show="changing" x-cloak class="mt-6">
                            @include('account.orders.partials.payment-methods', ['replacing' => true])
                        </div>
                    @endif
                @elseif ($choosing && $methodOptions)
                    <div class="mt-6">
                        @include('account.orders.partials.payment-methods', ['replacing' => false])
                    </div>
                @elseif ($choosing)
                    <p class="mt-6 border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Belum ada metode pembayaran yang aktif. Silakan hubungi kami.</p>
                @endif

                @if ($isFake)
                    <p class="mt-4 text-xs text-stone-500">Mode uji coba: nomor & kode di atas hanya contoh, pembayaran disimulasikan.</p>
                @endif

                <div class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-3 border-t border-stone-200 pt-5 text-sm">
                    <form method="POST" action="{{ route('account.orders.cancel', $order) }}"
                          data-confirm-title="Batalkan pesanan?" data-confirm-ok="Ya, batalkan" data-confirm-tone="danger"
                          data-confirm="Pesanan {{ $order->code }} akan dibatalkan dan tidak bisa dibayar lagi.">
                        @csrf
                        <button class="link-underline font-semibold text-stone-500 hover:text-rose-600">Batalkan pesanan</button>
                    </form>
                </div>
            </div>

            <p class="mt-4 flex items-center gap-2 text-xs text-stone-500"><x-icon name="lock" class="size-4 shrink-0" /> Pembayaran diproses aman oleh Midtrans. Status pesanan diperbarui otomatis setelah pembayaran diterima.</p>
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
                    <x-order-totals :order="$order" :payment="$payment" />
                    @if (! $payment && $hasFees)
                        <p class="pt-1 text-xs text-stone-500">Biaya transaksi ditambahkan sesuai metode yang dipilih.</p>
                    @endif
                </dl>
            </div>
        </aside>
    </div>

    @if ($usesSnap)
        <x-slot:scripts>
            <script src="{{ config('services.midtrans.is_production') ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js' }}" data-client-key="{{ config('services.midtrans.client_key') }}"></script>
        </x-slot:scripts>
    @endif
</x-layouts.account>
