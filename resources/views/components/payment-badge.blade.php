@props(['method', 'size' => 'md'])
{{-- Lencana teks metode bayar (tanpa logo resmi pihak ketiga). --}}
@php
    $tone = match ($method) {
        \App\Enums\PaymentMethod::BcaVa, \App\Enums\PaymentMethod::BriVa => 'text-blue-800',
        \App\Enums\PaymentMethod::BniVa => 'text-orange-600',
        \App\Enums\PaymentMethod::MandiriBill => 'text-blue-900',
        \App\Enums\PaymentMethod::PermataVa, \App\Enums\PaymentMethod::CimbVa => 'text-rose-700',
        \App\Enums\PaymentMethod::Gopay => 'text-sky-600',
        \App\Enums\PaymentMethod::Shopeepay => 'text-orange-600',
        \App\Enums\PaymentMethod::Qris => 'text-rose-600',
        \App\Enums\PaymentMethod::Indomaret => 'text-blue-700',
        \App\Enums\PaymentMethod::Alfamart => 'text-red-600',
        default => 'text-ink',
    };
@endphp
<span {{ $attributes->class([
    'grid shrink-0 place-items-center rounded-md border border-stone-200 bg-white px-1 font-extrabold tracking-tight',
    'h-9 w-16 text-[11px]' => $size === 'md',
    'h-11 w-20 text-xs' => $size === 'lg',
    $tone,
]) }} aria-hidden="true">
    @if ($method === \App\Enums\PaymentMethod::CreditCard)
        <x-icon name="credit-card" class="size-5" />
    @else
        {{ $method->badge() }}
    @endif
</span>
