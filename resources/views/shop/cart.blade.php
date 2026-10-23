<x-layouts.shop title="Keranjang">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-extrabold tracking-tight text-stone-900 sm:text-3xl">Keranjang belanja</h1>

        @if ($lines->isEmpty())
            <div class="card mt-6">
                <x-empty-state icon="cart" title="Keranjangmu masih kosong" description="Yuk, temukan aksesoris favoritmu dan tambahkan ke keranjang.">
                    <a href="{{ route('products.index') }}" class="btn btn-primary">Mulai belanja</a>
                </x-empty-state>
            </div>
        @else
            <div class="mt-6 grid gap-6 lg:grid-cols-3">
                <div class="card divide-y divide-stone-100 lg:col-span-2">
                    @foreach ($lines as $line)
                        <div class="flex gap-4 p-4 sm:p-5">
                            <a href="{{ route('products.show', $line->product) }}" class="shrink-0">
                                <x-product-image :product="$line->product" class="size-20 rounded-xl sm:size-24" />
                            </a>
                            <div class="flex min-w-0 flex-1 flex-col">
                                <div class="flex items-start justify-between gap-3">
                                    <a href="{{ route('products.show', $line->product) }}" class="font-semibold text-stone-900 line-clamp-2 hover:text-brand-700">{{ $line->product->name }}</a>
                                    <form method="POST" action="{{ route('cart.destroy', $line->product) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-stone-400 hover:text-rose-600" aria-label="Hapus"><x-icon name="trash" class="size-5" /></button>
                                    </form>
                                </div>
                                <p class="mt-1 text-sm text-stone-500">@rupiah($line->product->price)</p>
                                <div class="mt-auto flex items-center justify-between pt-3">
                                    <form method="POST" action="{{ route('cart.update', $line->product) }}" class="inline-flex items-center rounded-xl border border-stone-200 bg-white">
                                        @csrf
                                        @method('PATCH')
                                        <button name="quantity" value="{{ $line->quantity - 1 }}" class="p-2 text-stone-500 hover:text-stone-900" aria-label="Kurangi"><x-icon name="minus" class="size-4" /></button>
                                        <span class="w-10 text-center text-sm font-semibold">{{ $line->quantity }}</span>
                                        <button name="quantity" value="{{ $line->quantity + 1 }}" class="p-2 text-stone-500 hover:text-stone-900 disabled:opacity-30" aria-label="Tambah" @disabled($line->quantity >= $line->product->stock)><x-icon name="plus" class="size-4" /></button>
                                    </form>
                                    <p class="font-bold text-stone-900">@rupiah($line->subtotal())</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="lg:sticky lg:top-24 lg:self-start">
                    <div class="card p-5">
                        <h2 class="font-bold text-stone-900">Ringkasan belanja</h2>
                        <dl class="mt-4 space-y-2 text-sm">
                            <div class="flex justify-between"><dt class="text-stone-500">Total item</dt><dd>{{ $lines->sum('quantity') }} pcs</dd></div>
                            <div class="flex justify-between"><dt class="text-stone-500">Subtotal</dt><dd class="font-semibold">@rupiah($subtotal)</dd></div>
                            <div class="flex justify-between"><dt class="text-stone-500">Ongkir</dt><dd class="text-stone-400">Dihitung saat checkout</dd></div>
                        </dl>
                        <a href="{{ route('checkout.index') }}" class="btn btn-primary mt-5 w-full py-3">Lanjut ke checkout <x-icon name="arrow-right" class="size-4" /></a>
                        <a href="{{ route('products.index') }}" class="btn btn-ghost mt-2 w-full">Lanjut belanja</a>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-layouts.shop>
