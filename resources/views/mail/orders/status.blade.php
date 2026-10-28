@php($S = \App\Enums\OrderStatus::class)
<x-mail::message>
@switch($status)
@case($S::Paid)
# Pembayaran berhasil

Pembayaran untuk pesanan **{{ $order->code }}** sudah kami terima. Tim gudang segera menyiapkan pesananmu.
@break
@case($S::Shipped)
# Pesananmu dalam perjalanan

Pesanan **{{ $order->code }}** sudah diserahkan ke kurir **{{ $order->courierLabel() ?? '-' }}**.

@if ($order->tracking_number)
<x-mail::panel>
Nomor resi: **{{ $order->tracking_number }}**
</x-mail::panel>
@endif

<x-mail::button :url="route('account.orders.show', $order)">
Lihat pesanan
</x-mail::button>
@break
@case($S::Delivered)
# Paketmu sudah sampai

Pesanan **{{ $order->code }}** tercatat sudah sampai di alamat tujuan. Jika paket sudah kamu terima dan isinya sesuai, tekan **Pesanan diterima** di halaman pesanan.
@if (config('shop.orders.auto_complete_days') > 0)

Pesanan akan selesai otomatis dalam {{ config('shop.orders.auto_complete_days') }} hari.
@endif
@break
@case($S::Completed)
# Pesanan selesai

Pesanan **{{ $order->code }}** sudah selesai. Terima kasih sudah belanja di {{ config('shop.full_name') }}!

Puas dengan produknya? Ceritakan pengalamanmu supaya pembeli lain terbantu.

<x-mail::button :url="route('account.reviews.index')">
Tulis ulasan
</x-mail::button>
@break
@case($S::Cancelled)
# Pesanan dibatalkan

Pesanan **{{ $order->code }}** telah dibatalkan.
@if ($order->cancellation_reason)

Alasan: {{ $order->cancellation_reason }}
@endif

Jika kamu sudah membayar, tim kami akan menghubungimu untuk proses pengembalian dana.
@break
@default
# Status pesanan diperbarui

Status pesanan **{{ $order->code }}** sekarang: **{{ $status->label() }}**.
@endswitch

@include('mail.orders._items')

@unless (in_array($status, [$S::Completed, $S::Shipped], true))
<x-mail::button :url="route('account.orders.show', $order)">
Lihat pesanan
</x-mail::button>
@endunless

Ada pertanyaan? Balas email ini{{ config('shop.whatsapp') ? ' atau chat WhatsApp kami di wa.me/'.config('shop.whatsapp') : '' }}.

Salam,<br>
Tim {{ config('shop.name') }}
</x-mail::message>
