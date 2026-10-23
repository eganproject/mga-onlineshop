<x-layouts.shop :title="$product->name">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <nav class="mb-6 flex flex-wrap items-center gap-1.5 text-xs text-stone-500">
            <a href="{{ route('home') }}" class="hover:text-stone-800">Beranda</a>
            <x-icon name="chevron-right" class="size-3" />
            <a href="{{ route('products.index') }}" class="hover:text-stone-800">Produk</a>
            @if ($product->category)
                <x-icon name="chevron-right" class="size-3" />
                <a href="{{ route('products.index', ['kategori' => $product->category->slug]) }}" class="hover:text-stone-800">{{ $product->category->name }}</a>
            @endif
        </nav>

        <div class="grid gap-8 lg:grid-cols-2 lg:gap-12">
            <div class="overflow-hidden rounded-3xl border border-stone-200/70 bg-white p-2">
                <x-product-image :product="$product" class="aspect-square rounded-2xl" />
            </div>

            <div x-data="{ qty: 1, max: {{ max(1, $product->stock) }} }">
                @if ($product->category)
                    <p class="text-xs font-bold tracking-wider text-brand-600 uppercase">{{ $product->category->name }}</p>
                @endif
                <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-stone-900 sm:text-3xl">{{ $product->name }}</h1>
                @if ($product->sku)<p class="mt-1 text-xs text-stone-400">SKU: {{ $product->sku }}</p>@endif

                <div class="mt-5 flex flex-wrap items-end gap-3">
                    <p class="text-3xl font-extrabold text-stone-900">@rupiah($product->price)</p>
                    @if ($product->isOnSale())
                        <p class="pb-1 text-base text-stone-400 line-through">@rupiah($product->compare_at_price)</p>
                        <span class="mb-1 rounded-lg bg-rose-50 px-2 py-0.5 text-xs font-bold text-rose-600">Hemat {{ $product->discountPercent() }}%</span>
                    @endif
                </div>

                <div class="mt-5 flex flex-wrap gap-2 text-sm">
                    @if ($product->inStock())
                        <span class="badge bg-emerald-50 py-1 text-emerald-700 ring-emerald-600/20"><x-icon name="check" class="size-3.5" /> Stok tersedia{{ $product->isLowStock() ? ' · sisa '.$product->stock : '' }}</span>
                    @else
                        <span class="badge bg-stone-100 py-1 text-stone-600 ring-stone-500/20">Stok habis</span>
                    @endif
                    <span class="badge bg-stone-50 py-1 text-stone-600 ring-stone-500/10">Berat {{ $product->weight }} gr</span>
                </div>

                @if ($product->inStock())
                    <form method="POST" action="{{ route('cart.store', $product) }}" class="mt-8 space-y-4">
                        @csrf
                        <div>
                            <p class="label">Jumlah</p>
                            <div class="inline-flex items-center rounded-xl border border-stone-200 bg-white">
                                <button type="button" @click="qty = Math.max(1, qty - 1)" class="p-3 text-stone-500 hover:text-stone-900" aria-label="Kurangi"><x-icon name="minus" class="size-4" /></button>
                                <input type="number" name="quantity" x-model.number="qty" min="1" :max="max" class="w-14 border-0 text-center text-sm font-semibold focus:ring-0">
                                <button type="button" @click="qty = Math.min(max, qty + 1)" class="p-3 text-stone-500 hover:text-stone-900" aria-label="Tambah"><x-icon name="plus" class="size-4" /></button>
                            </div>
                        </div>
                        <div class="flex flex-col gap-3 sm:flex-row">
                            <button class="btn btn-secondary flex-1 py-3"><x-icon name="cart" class="size-5" /> Tambah ke keranjang</button>
                            <button name="buy_now" value="1" class="btn btn-primary flex-1 py-3">Beli sekarang</button>
                        </div>
                    </form>
                @endif

                <div class="mt-8 grid grid-cols-3 gap-3 text-center text-xs">
                    @foreach ([['shield', 'Original'], ['truck', 'Kirim cepat'], ['refresh', 'Garansi tukar']] as [$icon, $label])
                        <div class="rounded-2xl bg-white p-3 ring-1 ring-stone-200/70">
                            <x-icon :name="$icon" class="mx-auto size-6 text-brand-600" />
                            <p class="mt-1.5 font-semibold text-stone-700">{{ $label }}</p>
                        </div>
                    @endforeach
                </div>

                @if ($product->description)
                    <div class="mt-8 border-t border-stone-200 pt-6">
                        <h2 class="font-bold text-stone-900">Deskripsi</h2>
                        <div class="mt-3 text-sm leading-relaxed whitespace-pre-line text-stone-600">{{ $product->description }}</div>
                    </div>
                @endif
            </div>
        </div>

        @if ($related->isNotEmpty())
            <section class="mt-16">
                <h2 class="text-xl font-extrabold tracking-tight text-stone-900">Produk serupa</h2>
                <div class="mt-5 grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-4">
                    @foreach ($related as $item)
                        <x-product-card :product="$item" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.shop>
