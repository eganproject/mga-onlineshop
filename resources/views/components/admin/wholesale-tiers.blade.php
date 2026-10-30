{{--
    Editor tingkat harga grosir (form produk: produk tanpa varian & tiap kartu varian).
    list     = ekspresi Alpine array tingkat, mis. "tiers" / "variant.tiers"
    name     = ekspresi JS awalan nama input, mis. "'wholesale_tiers'" / "`variants[${index}][wholesale_tiers]`"
    base     = ekspresi Alpine harga normal (cek & persen hemat)
    disabled = ekspresi Alpine kapan input dimatikan
--}}
@props(['list', 'name', 'base', 'disabled' => 'false'])
<div class="space-y-3" data-wholesale-editor>
    <template x-for="(tier, t) in {{ $list }}" :key="tier.uid">
        <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)_auto] items-end gap-2">
            <div class="min-w-0">
                <label class="mb-1 block text-xs font-semibold text-stone-500" :for="`tier-${tier.uid}-qty`">Mulai (pcs)</label>
                <input :id="`tier-${tier.uid}-qty`" type="number" min="2" max="9999" inputmode="numeric" required
                       x-bind:name="`${ {{ $name }} }[${t}][min_qty]`" x-model="tier.min_qty" x-bind:disabled="{{ $disabled }}"
                       class="input tabular-nums" placeholder="10">
            </div>
            <div class="min-w-0">
                <label class="mb-1 block text-xs font-semibold text-stone-500" :for="`tier-${tier.uid}-price`">Harga per pcs</label>
                <div class="relative"><span class="input-addon left-3">Rp</span>
                    <input :id="`tier-${tier.uid}-price`" x-money required
                           x-bind:name="`${ {{ $name }} }[${t}][price]`" x-model="tier.price" x-bind:disabled="{{ $disabled }}"
                           class="input pl-9 tabular-nums" placeholder="65.000">
                </div>
            </div>
            <button type="button" @click="{{ $list }}.splice(t, 1)" class="btn btn-ghost btn-sm mb-1 px-2 text-rose-600 hover:bg-rose-50" :aria-label="`Hapus tingkat ${t + 1}`" title="Hapus tingkat"><x-icon name="trash" class="size-4" /></button>
            <p class="col-span-3 -mt-1 text-xs" :class="tierProblem({{ $list }}, t, {{ $base }}) ? 'font-medium text-rose-600' : 'text-stone-500'"
               x-text="tierProblem({{ $list }}, t, {{ $base }}) || tierSummary({{ $list }}, t, {{ $base }})"></p>
        </div>
    </template>
    <button type="button" @click="addTier({{ $list }}, {{ $base }})" x-show="{{ $list }}.length < {{ \App\Services\Catalog\Wholesale::MAX_TIERS }}" x-bind:disabled="{{ $disabled }}"
            class="btn btn-secondary btn-sm"><x-icon name="plus" class="size-4" /> Tambah tingkat grosir</button>
</div>
