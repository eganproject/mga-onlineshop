<td class="whitespace-nowrap text-xs text-stone-500">{{ $row['sku'] ?? '—' }}</td>
<td>
    <span @class([
        'badge',
        'bg-rose-50 text-rose-700 ring-rose-600/20' => $row['stock'] === 0,
        'bg-amber-50 text-amber-700 ring-amber-600/20' => $row['stock'] > 0 && $row['low'],
        'bg-emerald-50 text-emerald-700 ring-emerald-600/20' => ! $row['low'],
    ])>{{ $row['stock'] }}</span>
</td>
<td class="text-right">
    <button type="button" class="btn btn-secondary btn-sm"
            @click="$dispatch('adjust-stock', @js([
                'product_id' => $product->id,
                'variant_id' => $row['variant']?->id,
                'label' => $row['variant'] ? $product->name.' — '.$row['name'] : $product->name,
                'stock' => $row['stock'],
            ]))">
        <x-icon name="pencil" class="size-4" /> Ubah
    </button>
</td>
