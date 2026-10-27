<x-mail::table>
| Produk | Jml | Subtotal |
|:-------|:---:|---------:|
@foreach ($items as $item)
| {{ $item->product_name }}@if ($item->variant_name) ({{ $item->variant_name }})@endif | {{ $item->quantity }} | @rupiah($item->subtotal) |
@endforeach
@foreach (\App\Support\OrderSummary::rows($order) as $row)
@continue(str_starts_with($row['label'], 'Subtotal'))
@if (in_array($row['tone'], ['total', 'subtotal'], true))
| **{{ $row['label'] }}** | | **{{ \App\Support\OrderSummary::format($row) }}** |
@else
| {{ $row['label'] }}{{ ! empty($row['note']) ? ' · '.$row['note'] : '' }} | | {{ \App\Support\OrderSummary::format($row) }} |
@endif
@endforeach
</x-mail::table>
