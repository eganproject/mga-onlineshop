{{--
    Cara bayar tagihan aktif, langsung di halaman: nomor VA, kode bayar,
    QR, tombol buka aplikasi, atau formulir Midtrans (kartu/PayLater).
    Variabel: $order, $payment, $payable, $isFake.
--}}
@php
    $method = $payment->paymentMethod();
    $channel = $method?->channel() ?? \App\Enums\PaymentMethod::CHANNEL_SNAP;
    $number = $payment->instruction('number');
    $qrString = $payment->instruction('qr_string');
    $qrUrl = $payment->instruction('qr_url');
    $deeplink = $payment->instruction('deeplink');
    $steps = \App\Support\PaymentInstructions::steps($payment);
    $grouped = fn (?string $value) => $value ? trim(chunk_split($value, 4, ' ')) : null;
@endphp

<div class="space-y-5">
    @if (in_array($channel, [\App\Enums\PaymentMethod::CHANNEL_VA, \App\Enums\PaymentMethod::CHANNEL_STORE], true) && ($number || $payment->instruction('payment_code')))
        @php($code = $number ?? $payment->instruction('payment_code'))
        @php($codeLabel = $number ? 'Nomor Virtual Account '.$method->provider() : 'Kode pembayaran '.$method->label())
        <div class="border border-stone-200 p-4 sm:p-5">
            <p class="text-xs font-bold tracking-wider text-stone-500 uppercase">{{ $codeLabel }}</p>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <p class="min-w-0 break-all font-mono text-xl font-bold tracking-wide text-ink sm:text-3xl sm:tracking-wider" data-payment-code>{{ $grouped($code) }}</p>
                <button type="button" class="btn btn-outline btn-sm rounded-full px-4" x-on:click="copy(@js($code), @js($number ? 'Nomor VA' : 'Kode pembayaran'))">
                    <x-icon name="receipt" class="size-4" /> Salin
                </button>
            </div>
        </div>
    @elseif ($channel === \App\Enums\PaymentMethod::CHANNEL_BILL && $payment->instruction('bill_key'))
        <div class="grid grid-cols-1 divide-y divide-stone-200 border border-stone-200 sm:grid-cols-2 sm:divide-x sm:divide-y-0">
            @foreach (['biller_code' => 'Kode perusahaan', 'bill_key' => 'Kode pembayaran'] as $key => $label)
                <div class="p-4 sm:p-5">
                    <p class="text-xs font-bold tracking-wider text-stone-500 uppercase">{{ $label }}</p>
                    <div class="mt-2 flex items-center justify-between gap-3">
                        <p class="min-w-0 break-all font-mono text-xl font-bold tracking-wide text-ink sm:text-2xl" data-payment-code>{{ $payment->instruction($key) }}</p>
                        <button type="button" class="btn btn-outline btn-sm rounded-full px-4" x-on:click="copy(@js($payment->instruction($key)), @js($label))"><x-icon name="receipt" class="size-4" /> Salin</button>
                    </div>
                </div>
            @endforeach
        </div>
    @elseif ($channel === \App\Enums\PaymentMethod::CHANNEL_QR && ($qrString || $qrUrl))
        <div class="flex flex-col items-center gap-4 border border-stone-200 p-5 sm:flex-row sm:items-center sm:gap-6">
            <div class="grid size-56 shrink-0 place-items-center border border-stone-200 bg-white p-2 [&_svg]:size-full" role="img" aria-label="Kode QR {{ $method->label() }}">
                @if ($qrString)
                    <div class="size-full" x-init="renderQr($el, @js($qrString))">
                        <span class="grid size-full place-items-center text-xs text-stone-400">Memuat QR…</span>
                    </div>
                @else
                    <img src="{{ $qrUrl }}" alt="" class="size-full object-contain">
                @endif
            </div>
            <div class="w-full space-y-3 text-center sm:text-left">
                <p class="text-sm text-stone-600">Scan kode QR ini dengan {{ $method === \App\Enums\PaymentMethod::Gopay ? 'aplikasi Gojek atau GoPay' : 'aplikasi e-wallet atau m-banking yang mendukung QRIS' }}.</p>
                <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                    @if ($deeplink)
                        <a href="{{ $deeplink }}" class="btn btn-ink btn-pill lg:hidden"><x-icon name="phone" class="size-4" /> Buka aplikasi GoPay</a>
                    @endif
                    @if ($qrString)
                        <button type="button" class="btn btn-outline btn-pill" x-on:click="downloadQr(@js($qrString), @js('qris-'.$order->code.'.png'))"><x-icon name="download" class="size-4" /> Simpan QR</button>
                    @else
                        <a href="{{ $qrUrl }}" target="_blank" rel="noopener" download class="btn btn-outline btn-pill"><x-icon name="download" class="size-4" /> Simpan QR</a>
                    @endif
                </div>
            </div>
        </div>
    @elseif ($channel === \App\Enums\PaymentMethod::CHANNEL_APP)
        <div class="border border-stone-200 p-5">
            @if ($deeplink)
                <a href="{{ $deeplink }}" class="btn btn-ink btn-pill w-full sm:w-auto"><x-icon name="phone" class="size-4" /> Buka aplikasi {{ $method->label() }}</a>
            @endif
            <p class="mt-3 text-sm text-stone-600">{{ $method->label() }} hanya bisa dibuka dari HP yang ada aplikasi Shopee. Sedang di laptop? Pilih <span class="font-semibold">QRIS</span> lewat “Ganti metode”, lalu scan memakai aplikasi Shopee.</p>
        </div>
    @elseif ($payment->snap_token)
        <div class="border border-stone-200">
            <div id="snap-container" class="min-h-[30rem] w-full"
                 x-init="embedSnap(@js($payment->snap_token), @js($payment->redirect_url), 'check-form')">
            </div>
        </div>
        <p class="text-xs text-stone-500">Data {{ $method === \App\Enums\PaymentMethod::CreditCard ? 'kartu' : 'akun' }} diisi di formulir aman Midtrans di atas dan tidak disimpan oleh toko.</p>
    @elseif ($isFake)
        <div class="border border-dashed border-stone-300 bg-sand-50 p-5 text-sm text-stone-600">
            Formulir {{ $method?->label() ?? 'pembayaran' }} dari Midtrans akan tampil di sini. Mode uji coba: tekan “Simulasikan bayar”.
        </div>
    @endif

    @if (in_array($channel, [\App\Enums\PaymentMethod::CHANNEL_VA, \App\Enums\PaymentMethod::CHANNEL_BILL, \App\Enums\PaymentMethod::CHANNEL_STORE], true))
        <div class="flex flex-wrap items-center justify-between gap-3 bg-sand-50 px-4 py-3 text-sm">
            <span class="text-stone-600">Jumlah yang harus dibayar <span class="block font-display text-lg font-extrabold text-ink tabular-nums">@rupiah($payable)</span></span>
            <button type="button" class="link-underline font-semibold text-stone-600 hover:text-ink" x-on:click="copy(@js((string) $payable), 'Nominal')">Salin nominal</button>
        </div>
    @endif

    @if ($steps)
        <div class="divide-y divide-stone-200 border border-stone-200">
            <p class="px-4 py-3 text-xs font-bold tracking-wider text-stone-500 uppercase">Cara bayar</p>
            @foreach ($steps as $title => $list)
                <details class="group" @if ($loop->first) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-semibold text-stone-900 hover:bg-stone-50 [&::-webkit-details-marker]:hidden">
                        {{ $title }}
                        <x-icon name="chevron-down" class="size-4 shrink-0 text-stone-400 transition group-open:rotate-180" />
                    </summary>
                    <ol class="space-y-2 px-4 pb-4 text-sm text-stone-600">
                        @foreach ($list as $step)
                            <li class="flex gap-3">
                                <span class="grid size-5 shrink-0 place-items-center rounded-full bg-brand-50 text-[11px] font-bold text-brand-700">{{ $loop->iteration }}</span>
                                <span>{{ $step }}</span>
                            </li>
                        @endforeach
                    </ol>
                </details>
            @endforeach
        </div>
    @endif
</div>
