<x-mail::message>
# Terima kasih, pesananmu sudah kami terima!

Nomor pesanan: **{{ $order->code }}**<br>
Tanggal: {{ $order->created_at->translatedFormat('d F Y, H:i') }} WIB

@include('mail.orders._items')

@if ($order->status === \App\Enums\OrderStatus::PendingPayment)
Segera selesaikan pembayaran dalam **{{ config('shop.payment.expiry_hours') }} jam**. Lewat dari itu, pesanan akan dibatalkan otomatis.

<x-mail::button :url="route('account.orders.payment', $order)">
Bayar sekarang
</x-mail::button>
@else
<x-mail::button :url="route('account.orders.show', $order)">
Lihat pesanan
</x-mail::button>
@endif

<x-mail::panel>
**Dikirim ke:** {{ $order->recipient_name }} ({{ $order->recipient_phone }})<br>
{{ $order->shipping_address }}
</x-mail::panel>

Salam,<br>
Tim {{ config('shop.name') }}
</x-mail::message>
