{{--
    Isi halaman keranjang. Dirender ulang oleh CartController saat jumlah
    diubah/dihapus lewat fetch, lalu di-morph ke [data-cart-root] (lihat cart.js).
--}}
<div data-cart-root>
    <div class="flex items-end justify-between gap-4 border-b border-stone-200 pb-6">
        <h1 class="display text-4xl leading-none sm:text-6xl">Keranjang</h1>
        @if ($lines->isNotEmpty())
            <p class="text-sm text-stone-500">{{ $lines->sum('quantity') }} item</p>
        @endif
    </div>

    <x-cart-notices :notices="$adjustments" class="mt-6" />

    @foreach ($belowMinimum ?? [] as $row)
        <p class="mt-4 flex items-start gap-2 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800" role="alert">
            <x-icon name="warning" class="mt-0.5 size-4 shrink-0" /> {{ $row['message'] }}
        </p>
    @endforeach

    @if ($lines->isEmpty())
        <x-empty-state icon="bag" title="Keranjangmu masih kosong" description="Yuk, temukan aksesoris favoritmu dan tambahkan ke keranjang." class="py-20">
            <a href="{{ route('products.index') }}" class="btn btn-ink btn-pill">Mulai belanja</a>
        </x-empty-state>
    @else
        <div class="mt-2 grid grid-cols-1 gap-10 lg:grid-cols-12 lg:gap-14">
            <ul class="divide-y divide-stone-200 lg:col-span-8">
                @foreach ($lines as $line)
                    <li class="flex gap-4 py-6 sm:gap-6" data-cart-line="{{ $line->key() }}">
                        <a href="{{ route('products.show', $line->product) }}" class="group w-24 shrink-0 sm:w-32">
                            @if ($line->variant?->image)
                                <img src="{{ $line->imageUrl() }}" alt="{{ $line->product->name }}" class="aspect-[4/5] w-full object-cover">
                            @else
                                <x-product-image :product="$line->product" class="aspect-[4/5]" />
                            @endif
                        </a>
                        <div class="flex min-w-0 flex-1 flex-col">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <a href="{{ route('products.show', $line->product) }}" class="font-semibold leading-snug line-clamp-2 hover:underline hover:underline-offset-4">{{ $line->product->name }}</a>
                                    @if ($line->variant)
                                        <p class="mt-1 text-xs font-medium text-stone-600">{{ $line->product->variant_label ?: 'Varian' }}: {{ $line->variant->name }}</p>
                                    @endif
                                    <p class="mt-1 text-sm text-stone-500">
                                        @rupiah($line->unitPrice())
                                        @if ($line->isWholesale())
                                            <span class="ml-1 text-xs text-stone-400 line-through">@rupiah($line->regularPrice())</span>
                                            <span class="badge ml-1 bg-accent-50 text-accent-700">Harga grosir</span>
                                        @elseif ($line->compareAtPrice() > $line->unitPrice())
                                            <span class="ml-1 text-xs text-stone-400 line-through">@rupiah($line->compareAtPrice())</span>
                                        @endif
                                    </p>
                                    @if ($next = $line->nextWholesaleTier())
                                        <p class="mt-1 text-xs font-medium text-accent-700" data-wholesale-nudge>
                                            <x-icon name="tag" class="inline size-3.5 align-[-2px]" />
                                            Tambah {{ $next['need'] }} pcs lagi{{ $line->variant && $line->product->has_variants ? ' (boleh varian lain)' : '' }}, jadi @rupiah($next['price'])/pcs
                                        </p>
                                    @endif
                                </div>
                                <p class="shrink-0 font-semibold">@rupiah($line->subtotal())</p>
                            </div>
                            <div class="mt-auto flex items-center justify-between pt-4">
                                <div class="flex items-center gap-3">
                                <form method="POST" action="{{ route('cart.update', $line->key()) }}" data-cart-form class="inline-flex items-center rounded-full border border-stone-300">
                                    @csrf
                                    @method('PATCH')
                                    <button name="quantity" value="{{ $line->quantity - 1 }}" class="grid size-10 place-items-center rounded-full hover:bg-sand-100 lg:size-9" aria-label="Kurangi"><x-icon name="minus" class="size-3.5" /></button>
                                    <span class="w-8 text-center text-sm font-semibold" aria-live="polite" data-cart-qty>{{ $line->quantity }}</span>
                                    <button name="quantity" value="{{ $line->quantity + 1 }}" class="grid size-10 place-items-center rounded-full hover:bg-sand-100 disabled:opacity-30 lg:size-9" aria-label="Tambah" @disabled($line->quantity >= $line->availableStock())><x-icon name="plus" class="size-3.5" /></button>
                                </form>
                                @if ($line->isLowStock())
                                    <span class="text-xs font-medium text-amber-700">Sisa {{ $line->availableStock() }}</span>
                                @endif
                                </div>
                                <form method="POST" action="{{ route('cart.destroy', $line->key()) }}" data-cart-form>
                                    @csrf
                                    @method('DELETE')
                                    <button class="link-underline -my-2 py-3 text-xs font-semibold text-stone-500 hover:text-rose-600 lg:my-0 lg:py-0">Hapus</button>
                                </form>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="lg:col-span-4">
                <div class="bg-sand-50 p-6 lg:sticky lg:top-28 lg:mt-6">
                    <h2 class="font-display text-lg font-extrabold tracking-tight uppercase">Ringkasan</h2>
                    <dl class="mt-5 space-y-3 text-sm">
                        <div class="flex justify-between"><dt class="text-stone-500">Subtotal</dt><dd class="font-semibold">@rupiah($subtotal)</dd></div>
                        @if (($wholesaleSavings ?? 0) > 0)
                            <div class="flex justify-between text-accent-700"><dt>Hemat harga grosir</dt><dd class="font-semibold">@rupiah($wholesaleSavings)</dd></div>
                        @endif
                        <div class="flex justify-between"><dt class="text-stone-500">Berat total</dt><dd class="text-stone-700">{{ number_format(max($weight, 1) / 1000, 2, ',', '.') }} kg</dd></div>
                        <div class="flex justify-between"><dt class="text-stone-500">Ongkir</dt><dd class="text-stone-500">Dihitung saat checkout</dd></div>
                    </dl>
                    <div class="mt-5 flex justify-between border-t border-stone-200 pt-5 text-base font-semibold">
                        <span>Total</span><span>@rupiah($subtotal)</span>
                    </div>
                    @php $billing = \App\Services\Billing\BillingSettings::current(); @endphp
                    @if ($billing->priceNote() || $billing->serviceFee())
                        <p class="mt-2 text-xs text-stone-500">{{ collect([$billing->priceNote(), $billing->serviceFee() ? $billing->serviceFeeLabel().' '.\App\Support\Money::rupiah($billing->serviceFee()) : null])->filter()->implode(' · ') }}, dihitung saat checkout.</p>
                    @endif
                    @if (! empty($belowMinimum))
                        <button type="button" disabled class="btn btn-ink btn-pill mt-6 w-full py-4 opacity-50">Checkout <x-icon name="arrow-right" class="size-4" /></button>
                        <p class="mt-2 text-center text-xs text-amber-700">Penuhi minimal pembelian dulu untuk lanjut.</p>
                    @else
                        <a href="{{ route('checkout.index') }}" class="btn btn-ink btn-pill mt-6 w-full py-4">Checkout <x-icon name="arrow-right" class="size-4" /></a>
                    @endif
                    <a href="{{ route('products.index') }}" class="link-underline mx-auto mt-2 block w-fit py-3 text-center text-sm font-semibold lg:mt-4 lg:py-0">Lanjut belanja</a>
                    <ul class="mt-6 space-y-2 border-t border-stone-200 pt-5 text-xs text-stone-500">
                        <li class="flex items-center gap-2"><x-icon name="lock" class="size-4" /> Pembayaran aman & terenkripsi</li>
                        <li class="flex items-center gap-2"><x-icon name="shield" class="size-4" /> Produk original bergaransi</li>
                    </ul>
                </div>
            </div>
        </div>
    @endif
</div>
