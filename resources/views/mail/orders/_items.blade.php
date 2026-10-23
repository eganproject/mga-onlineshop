<x-mail::table>
| Produk | Jml | Subtotal |
|:-------|:---:|---------:|
@foreach ($items as $item)
| {{ $item->product_name }}@if ($item->variant_name) ({{ $item->variant_name }})@endif | {{ $item->quantity }} | @rupiah($item->subtotal) |
@endforeach
| Ongkir{{ $order->courierLabel() ? ' · '.$order->courierLabel() : '' }} | | @rupiah($order->shipping_cost) |
| **Total** | | **@rupiah($order->total)** |
</x-mail::table>
