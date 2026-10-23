<x-layouts.shop title="Keranjang">
    <div class="shop-container py-10 lg:py-14">
        <div class="flex items-end justify-between gap-4 border-b border-stone-200 pb-6">
            <h1 class="display text-4xl leading-none sm:text-6xl">Keranjang</h1>
            @if ($lines->isNotEmpty())
                <p class="text-sm text-stone-500">{{ $lines->sum('quantity') }} item</p>
            @endif
        </div>

        <x-cart-notices :notices="$adjustments" class="mt-6" />

        @if ($lines->isEmpty())
            <x-empty-state icon="bag" title="Keranjangmu masih kosong" description="Yuk, temukan aksesoris favoritmu dan tambahkan ke keranjang." class="py-20">
                <a href="{{ route('products.index') }}" class="btn btn-ink btn-pill">Mulai belanja</a>
            </x-empty-state>
        @else
            <div class="mt-2 grid gap-10 lg:grid-cols-12 lg:gap-14">
                <ul class="divide-y divide-stone-200 lg:col-span-8">
                    @foreach ($lines as $line)
                        <li class="flex gap-4 py-6 sm:gap-6">
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
                                            @if ($line->compareAtPrice() > $line->unitPrice())<span class="ml-1 text-xs text-stone-400 line-through">@rupiah($line->compareAtPrice())</span>@endif
                                        </p>
                                    </div>
                                    <p class="shrink-0 font-semibold">@rupiah($line->subtotal())</p>
                                </div>
                                <div class="mt-auto flex items-center justify-between pt-4">
                                    <div class="flex items-center gap-3">
                                    <form method="POST" action="{{ route('cart.update', $line->key()) }}" class="inline-flex items-center rounded-full border border-stone-300">
                                        @csrf
                                        @method('PATCH')
                                        <button name="quantity" value="{{ $line->quantity - 1 }}" class="grid size-9 place-items-center rounded-full hover:bg-sand-100" aria-label="Kurangi"><x-icon name="minus" class="size-3.5" /></button>
                                        <span class="w-8 text-center text-sm font-semibold">{{ $line->quantity }}</span>
                                        <button name="quantity" value="{{ $line->quantity + 1 }}" class="grid size-9 place-items-center rounded-full hover:bg-sand-100 disabled:opacity-30" aria-label="Tambah" @disabled($line->quantity >= $line->availableStock())><x-icon name="plus" class="size-3.5" /></button>
                                    </form>
                                    @if ($line->isLowStock())
                                        <span class="text-xs font-medium text-amber-700">Sisa {{ $line->availableStock() }}</span>
                                    @endif
                                    </div>
                                    <form method="POST" action="{{ route('cart.destroy', $line->key()) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="link-underline text-xs font-semibold text-stone-500 hover:text-rose-600">Hapus</button>
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
                            <div class="flex justify-between"><dt class="text-stone-500">Berat total</dt><dd class="text-stone-700">{{ number_format(max($weight, 1) / 1000, 2, ',', '.') }} kg</dd></div>
                            <div class="flex justify-between"><dt class="text-stone-500">Ongkir</dt><dd class="text-stone-500">Dihitung saat checkout</dd></div>
                        </dl>
                        <div class="mt-5 flex justify-between border-t border-stone-200 pt-5 text-base font-semibold">
                            <span>Total</span><span>@rupiah($subtotal)</span>
                        </div>
                        <a href="{{ route('checkout.index') }}" class="btn btn-ink btn-pill mt-6 w-full py-4">Checkout <x-icon name="arrow-right" class="size-4" /></a>
                        <a href="{{ route('products.index') }}" class="link-underline mt-4 block text-center text-sm font-semibold">Lanjut belanja</a>
                        <ul class="mt-6 space-y-2 border-t border-stone-200 pt-5 text-xs text-stone-500">
                            <li class="flex items-center gap-2"><x-icon name="lock" class="size-4" /> Pembayaran aman & terenkripsi</li>
                            <li class="flex items-center gap-2"><x-icon name="shield" class="size-4" /> Produk original bergaransi</li>
                        </ul>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-layouts.shop>
