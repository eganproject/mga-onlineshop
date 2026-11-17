{{--
    Link tagihan untuk pembeli (penjualan manual / dibagikan staf). Tanpa login,
    lewat URL bertanda tangan. Bisa dicetak: tombol & pilihan metode disembunyikan.
--}}
@php
    $usesSnap = $payment?->gateway === 'midtrans' && $payment->snap_token;
    $isFake = $payment?->gateway === 'fake' && ! app()->isProduction();
    $payable = \App\Support\OrderSummary::payable($order, $payment);
    $flatOptions = collect($methodOptions)->flatten(1);
    $paid = (bool) $order->paid_at && $order->status !== \App\Enums\OrderStatus::Cancelled;
    $cancelled = $order->status === \App\Enums\OrderStatus::Cancelled;
    $whatsapp = preg_replace('/\D+/', '', (string) config('shop.whatsapp'));
@endphp
<x-layouts.base :title="'Tagihan '.$order->code" noindex body-class="bg-stone-100 text-stone-800 print:bg-white">
    <x-slot:head>
        <style>@page { size: A4; margin: 12mm; }</style>
    </x-slot:head>

    <main class="mx-auto max-w-2xl px-4 py-6 sm:py-10 print:max-w-none print:p-0"
          x-data="paymentPage({
              statusUrl: @js($payment ? $urls['status'] : null),
              amounts: @js($flatOptions->mapWithKeys(fn ($o) => [$o['method']->value => $o['amount']])),
              labels: @js($flatOptions->mapWithKeys(fn ($o) => [$o['method']->value => $o['method']->shortLabel()])),
              selected: @js(old('method')),
          })">
        <article class="rounded-2xl bg-white p-5 shadow-sm sm:p-8 print:rounded-none print:p-0 print:shadow-none">
            <header class="flex items-start justify-between gap-4 border-b border-stone-200 pb-5">
                <div>
                    <x-logo class="h-10" />
                    <p class="mt-2 text-xs text-stone-500">{{ config('shop.full_name') }}@if (config('shop.address')) · {{ config('shop.address') }}@endif</p>
                </div>
                <div class="text-right">
                    <p class="text-xs font-bold tracking-wider text-stone-500 uppercase">Tagihan</p>
                    <p class="font-mono text-sm font-semibold text-stone-900">{{ $order->code }}</p>
                    <p class="text-xs text-stone-500">{{ $order->created_at->translatedFormat('d M Y, H:i') }}</p>
                </div>
            </header>

            <section class="py-5">
                <p class="text-sm text-stone-500">Untuk {{ $order->billedName() }}</p>
                <p class="mt-1 font-display text-4xl font-extrabold tracking-tight text-ink tabular-nums">@rupiah($payable)</p>

                @if ($paid)
                    <p class="mt-3 inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-sm font-bold text-emerald-700"><x-icon name="check-circle" class="size-4" /> Lunas {{ $order->paid_at->translatedFormat('d M Y, H:i') }}@if ($order->paymentMethodLabel()) · {{ $order->paymentMethodLabel() }}@endif</p>
                @elseif ($cancelled)
                    <p class="mt-3 inline-flex items-center gap-2 rounded-full bg-rose-50 px-3 py-1 text-sm font-bold text-rose-700"><x-icon name="x-circle" class="size-4" /> Dibatalkan</p>
                @else
                    <div class="mt-4 flex items-start gap-3 rounded-xl bg-amber-50 p-4 text-sm"
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
                            <p class="text-stone-600 print:hidden">Sisa waktu <span class="font-mono font-bold text-ink" x-text="left"></span></p>
                        </div>
                    </div>
                @endif
            </section>

            @if ($pending)
                @if ($error)
                    <div class="mb-5 flex gap-3 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700" role="alert">
                        <x-icon name="warning" class="size-5 shrink-0" /> <p>{{ $error }}</p>
                    </div>
                @endif

                @if ($payment)
                    <div x-show="!changing">
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
                            @if ($canChoose && $methodOptions)
                                <button type="button" class="btn btn-outline btn-sm shrink-0 rounded-full px-4 print:hidden" x-on:click="changing = true">Ganti metode</button>
                            @endif
                        </div>

                        @include('account.orders.partials.payment-instructions')

                        <p class="mt-5 flex items-center gap-2 text-sm text-stone-600 print:hidden" role="status">
                            <span class="relative flex size-2.5 shrink-0">
                                <span class="absolute inline-flex size-full animate-ping rounded-full bg-brand-400 opacity-75 motion-reduce:hidden"></span>
                                <span class="relative inline-flex size-2.5 rounded-full bg-brand-500"></span>
                            </span>
                            Menunggu pembayaran. Halaman ini berubah otomatis begitu pembayaran diterima.
                        </p>
                        <form id="check-form" method="GET" action="{{ $order->billUrl() }}" class="hidden"></form>
                    </div>

                    @if ($canChoose && $methodOptions)
                        <div x-show="changing" x-cloak class="print:hidden">
                            @include('account.orders.partials.payment-methods', ['replacing' => true, 'methodAction' => $urls['method']])
                        </div>
                    @endif
                @elseif ($canChoose && $methodOptions)
                    <div class="print:hidden">
                        @include('account.orders.partials.payment-methods', ['replacing' => false, 'methodAction' => $urls['method']])
                    </div>
                @elseif ($canChoose)
                    <p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Belum ada metode pembayaran yang aktif. Silakan hubungi kami.</p>
                @endif

                @if ($isFake)
                    <p class="mt-4 text-xs text-stone-500 print:hidden">Mode uji coba: nomor & kode di atas hanya contoh.</p>
                @endif
            @endif

            <section class="mt-6 border-t border-stone-200 pt-5">
                <h2 class="text-xs font-bold tracking-wider text-stone-500 uppercase">Rincian</h2>
                <ul class="mt-3 divide-y divide-stone-100 text-sm">
                    @foreach ($order->items as $item)
                        <li class="flex justify-between gap-3 py-2">
                            <span class="min-w-0 text-stone-700">{{ $item->product_name }}@if ($item->variant_name) <span class="text-stone-500">({{ $item->variant_name }})</span>@endif × {{ $item->quantity }}</span>
                            <span class="shrink-0 font-semibold tabular-nums">@rupiah($item->subtotal)</span>
                        </li>
                    @endforeach
                </ul>
                <dl class="mt-3 space-y-2 border-t border-stone-200 pt-3 text-sm">
                    <x-order-totals :order="$order" :payment="$payment" />
                </dl>
                @if ($order->shipping_address)
                    <p class="mt-4 text-xs text-stone-500">Dikirim ke: {{ $order->recipient_name }} · {{ $order->recipient_phone }} · {{ $order->shipping_address }}</p>
                @endif
            </section>
        </article>

        <div class="mt-5 flex flex-wrap items-center justify-center gap-3 text-sm print:hidden">
            <button type="button" onclick="window.print()" class="btn btn-secondary btn-sm"><x-icon name="printer" class="size-4" /> Cetak / simpan PDF</button>
            @if ($whatsapp)
                <a href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode('Halo, saya mau tanya tagihan '.$order->code) }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm"><x-icon name="chat" class="size-4" /> Tanya lewat WhatsApp</a>
            @endif
        </div>
        <p class="mt-4 flex items-center justify-center gap-2 text-xs text-stone-500 print:hidden"><x-icon name="lock" class="size-4 shrink-0" /> Pembayaran diproses aman oleh Midtrans.</p>
    </main>

    @if ($usesSnap)
        <x-slot:scripts>
            <script src="{{ config('services.midtrans.is_production') ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js' }}" data-client-key="{{ config('services.midtrans.client_key') }}"></script>
        </x-slot:scripts>
    @endif
</x-layouts.base>
