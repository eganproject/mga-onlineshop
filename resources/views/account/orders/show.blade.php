@php
    use App\Enums\OrderStatus;

    $payment = $order->latestPayment;
    $steps = [
        [OrderStatus::PendingPayment, 'Dipesan', 'receipt'],
        [OrderStatus::Paid, 'Dibayar', 'credit-card'],
        [OrderStatus::Processing, 'Diproses', 'cube'],
        [OrderStatus::Shipped, 'Dikirim', 'truck'],
        [OrderStatus::Completed, 'Selesai', 'check-circle'],
    ];
    $currentIndex = collect($steps)->search(fn ($s) => $s[0] === $order->status);
@endphp
<x-layouts.account :title="'Pesanan '.$order->code">
    <a href="{{ route('account.orders.index') }}" class="mb-3 inline-flex items-center gap-1 text-sm font-medium text-stone-500 hover:text-stone-800"><x-icon name="arrow-left" class="size-4" /> Semua pesanan</a>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-stone-900">{{ $order->code }}</h1>
            <p class="text-sm text-stone-500">Dipesan {{ $order->created_at->translatedFormat('d F Y, H:i') }}</p>
        </div>
        <x-order-status :status="$order->status" class="px-3 py-1 text-sm" />
    </div>

    {{-- Pembayaran --}}
    @if ($order->status === OrderStatus::PendingPayment)
        @php($previousFailed = $payment && $payment->status->isUnpaidFinal())
        <div class="mb-6 rounded-3xl border border-amber-200 bg-gradient-to-br from-amber-50 to-brand-50 p-5 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="flex items-center gap-2 font-bold text-stone-900"><x-icon name="clock" class="size-5 text-amber-600" /> Selesaikan pembayaran</p>
                    <p class="mt-1 text-sm text-stone-600">
                        Total <span class="font-bold">@rupiah($order->total)</span>
                        · bayar sebelum {{ $order->paymentDeadline()->timezone(config('app.timezone'))->translatedFormat('d M Y, H:i') }}
                    </p>
                    @if ($previousFailed)
                        <p class="mt-1 text-sm text-rose-700">Pembayaran sebelumnya {{ str($payment->status->label())->lower() }}. Silakan bayar ulang dengan link baru.</p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('account.orders.payment', $order) }}" class="btn btn-ink rounded-full">
                        <x-icon name="credit-card" class="size-4" /> {{ $previousFailed ? 'Bayar ulang' : 'Bayar sekarang' }}
                    </a>
                    <form method="POST" action="{{ route('account.orders.cancel', $order) }}" onsubmit="return confirm('Batalkan pesanan ini?')">
                        @csrf
                        <button class="btn btn-secondary">Batalkan</button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Progres --}}
    @if ($order->status !== OrderStatus::Cancelled)
        <div class="card mb-6 p-5">
            <ol class="grid grid-cols-5 gap-1">
                @foreach ($steps as $i => [$status, $label, $icon])
                    @php($done = $currentIndex !== false && $i <= $currentIndex)
                    <li class="relative flex flex-col items-center text-center">
                        @if ($i > 0)
                            <span @class(['absolute right-1/2 top-5 h-0.5 w-full -translate-y-1/2', 'bg-brand-500' => $done, 'bg-stone-200' => ! $done])></span>
                        @endif
                        <span @class(['relative grid size-10 place-items-center rounded-full ring-4 ring-white', 'bg-brand-600 text-white' => $done, 'bg-stone-100 text-stone-400' => ! $done])>
                            <x-icon :name="$icon" class="size-5" />
                        </span>
                        <span @class(['mt-2 text-[11px] font-semibold sm:text-xs', 'text-stone-900' => $done, 'text-stone-400' => ! $done])>{{ $label }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
    @else
        <div class="mb-6 flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
            <x-icon name="x-circle" class="size-5" /> Pesanan ini dibatalkan{{ $order->cancelled_at ? ' pada '.$order->cancelled_at->translatedFormat('d M Y, H:i') : '' }}.
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card overflow-hidden xl:col-span-2">
            <div class="border-b border-stone-100 p-5"><h2 class="font-bold text-stone-900">Produk</h2></div>
            <ul class="divide-y divide-stone-100">
                @foreach ($order->items as $item)
                    <li class="flex items-center gap-4 p-5">
                        @if ($item->product)
                            <a href="{{ route('products.show', $item->product) }}"><x-product-image :product="$item->product" class="size-16 shrink-0 rounded-xl" /></a>
                        @else
                            <div class="grid size-16 shrink-0 place-items-center rounded-xl bg-stone-100 text-stone-400"><x-icon name="cube" /></div>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-stone-900">{{ $item->product_name }}</p>
                            @if ($item->variant_name)<p class="text-xs text-stone-500">{{ $item->variant_name }}</p>@endif
                            <p class="text-sm text-stone-500">{{ $item->quantity }} × @rupiah($item->price)</p>
                        </div>
                        <p class="font-semibold">@rupiah($item->subtotal)</p>
                    </li>
                @endforeach
            </ul>
            <dl class="space-y-2 border-t border-stone-100 bg-stone-50/50 p-5 text-sm">
                <div class="flex justify-between"><dt class="text-stone-500">Subtotal</dt><dd>@rupiah($order->subtotal)</dd></div>
                <div class="flex justify-between"><dt class="text-stone-500">Ongkir</dt><dd>@rupiah($order->shipping_cost)</dd></div>
                <div class="flex justify-between border-t border-stone-200 pt-2 text-base font-bold text-stone-900"><dt>Total</dt><dd>@rupiah($order->total)</dd></div>
            </dl>
        </div>
        <div class="space-y-6">
            <div class="card p-5 text-sm">
                <h2 class="font-bold text-stone-900">Pengiriman</h2>
                <dl class="mt-3 space-y-3">
                    <div><dt class="text-xs text-stone-500">Penerima</dt><dd class="font-semibold text-stone-800">{{ $order->recipient_name }} · {{ $order->recipient_phone }}</dd></div>
                    <div><dt class="text-xs text-stone-500">Alamat</dt><dd class="text-stone-700">{{ $order->shipping_address }}</dd></div>
                    <div><dt class="text-xs text-stone-500">Kurir</dt><dd class="text-stone-700">{{ $order->courierLabel() }} @if($order->shipping_etd)<span class="text-stone-500">· {{ $order->shipping_etd }}</span>@endif</dd></div>
                    @if ($order->tracking_number)
                        <div><dt class="text-xs text-stone-500">No. resi</dt><dd class="font-mono font-semibold text-stone-900">{{ $order->tracking_number }}</dd></div>
                    @endif
                </dl>
            </div>
            @if ($payment)
                <div class="card p-5 text-sm">
                    <h2 class="font-bold text-stone-900">Pembayaran</h2>
                    <dl class="mt-3 space-y-2">
                        <div class="flex justify-between"><dt class="text-stone-500">Status</dt><dd class="font-semibold">{{ $payment->status->label() }}</dd></div>
                        @if ($payment->payment_type)
                            <div class="flex justify-between"><dt class="text-stone-500">Metode</dt><dd>{{ ucwords(str_replace('_', ' ', $payment->payment_type)) }}</dd></div>
                        @endif
                        @if ($payment->paid_at)
                            <div class="flex justify-between"><dt class="text-stone-500">Dibayar</dt><dd>{{ $payment->paid_at->translatedFormat('d M Y, H:i') }}</dd></div>
                        @endif
                    </dl>
                </div>
            @endif
        </div>
    </div>
</x-layouts.account>
