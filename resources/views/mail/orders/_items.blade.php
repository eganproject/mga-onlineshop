<x-mail::table>
| Produk | Jml | Subtotal |
|:-------|:---:|---------:|
@foreach ($items as $item)
| {{ $item->product_name }}@if ($item->variant_name) ({{ $item->variant_name }})@endif | {{ $item->quantity }} | @rupiah($item->subtotal) |
@endforeach
| Ongkir{{ $order->courierLabel() ? ' · '.$order->courierLabel() : '' }} | | @rupiah($order->shipping_cost) |
@if ($order->discount > 0)
| Diskon voucher{{ $order->voucher_code ? ' · '.$order->voucher_code : '' }} | | −@rupiah($order->discount) |
@endif
@if ($order->shipping_discount > 0)
| Potongan ongkir | | −@rupiah($order->shipping_discount) |
@endif
| **Total** | | **@rupiah($order->total)** |
</x-mail::table>
