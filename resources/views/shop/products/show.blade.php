<x-layouts.shop :title="$product->name">
    <div class="shop-container pb-28 pt-6 lg:pb-0 lg:pt-8">
        <nav class="flex flex-wrap items-center gap-1.5 text-xs text-stone-500" aria-label="Breadcrumb">
            <a href="{{ route('home') }}" class="hover:text-ink">Beranda</a>
            <span>/</span>
            <a href="{{ route('products.index') }}" class="hover:text-ink">Produk</a>
            @if ($product->category)
                <span>/</span>
                <a href="{{ route('products.index', ['kategori' => $product->category->slug]) }}" class="hover:text-ink">{{ $product->category->name }}</a>
            @endif
        </nav>

        <div class="mt-5 grid gap-8 lg:grid-cols-12 lg:gap-14">
            {{-- Foto --}}
            <div class="-mx-4 sm:mx-0 lg:col-span-7">
                <div class="relative">
                    <x-product-image :product="$product" :eager="true" class="aspect-square sm:aspect-[4/5]" />
                    @if ($product->isOnSale())
                        <span class="absolute left-4 top-4 bg-brand-600 px-2.5 py-1 text-[11px] font-bold tracking-wider text-white uppercase">Hemat {{ $product->discountPercent() }}%</span>
                    @endif
                </div>
            </div>

            {{-- Info --}}
            <div class="lg:col-span-5">
                <div class="lg:sticky lg:top-28" x-data="{ qty: 1, max: {{ max(1, $product->stock) }} }">
                    @if ($product->category)
                        <a href="{{ route('products.index', ['kategori' => $product->category->slug]) }}" class="eyebrow text-stone-500 hover:text-ink">{{ $product->category->name }}</a>
                    @endif
                    <h1 class="mt-2 font-display text-3xl leading-[1.05] font-extrabold tracking-tight sm:text-4xl">{{ $product->name }}</h1>

                    <div class="mt-4 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <p @class(['text-2xl font-semibold', 'text-brand-700' => $product->isOnSale()])>@rupiah($product->price)</p>
                        @if ($product->isOnSale())
                            <p class="text-base text-stone-400 line-through">@rupiah($product->compare_at_price)</p>
                        @endif
                    </div>

                    <p class="mt-4 flex items-center gap-2 text-sm">
                        @if ($product->inStock())
                            <span class="size-2 rounded-full {{ $product->isLowStock() ? 'bg-amber-500' : 'bg-emerald-500' }}"></span>
                            {{ $product->isLowStock() ? 'Stok terbatas, sisa '.$product->stock : 'Stok tersedia, siap dikirim' }}
                        @else
                            <span class="size-2 rounded-full bg-stone-400"></span> Stok habis
                        @endif
                    </p>

                    @if ($product->inStock())
                        <form id="add-to-cart" method="POST" action="{{ route('cart.store', $product) }}" class="mt-8">
                            @csrf
                            <p class="eyebrow text-stone-500">Jumlah</p>
                            <div class="mt-3 inline-flex items-center rounded-full border border-stone-300">
                                <button type="button" @click="qty = Math.max(1, qty - 1)" class="grid size-11 place-items-center rounded-full hover:bg-sand-100" aria-label="Kurangi"><x-icon name="minus" class="size-4" /></button>
                                <input type="number" name="quantity" x-model.number="qty" min="1" :max="max" value="1" aria-label="Jumlah"
                                       class="w-12 border-0 bg-transparent p-0 text-center text-sm font-semibold focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none">
                                <button type="button" @click="qty = Math.min(max, qty + 1)" class="grid size-11 place-items-center rounded-full hover:bg-sand-100" aria-label="Tambah"><x-icon name="plus" class="size-4" /></button>
                            </div>
                            <div class="mt-6 grid gap-3">
                                <button class="btn btn-ink btn-pill w-full py-4 text-[15px]">Tambah ke keranjang</button>
                                <button name="buy_now" value="1" class="btn btn-outline btn-pill w-full py-4 text-[15px]">Beli sekarang</button>
                            </div>
                        </form>
                    @else
                        <div class="mt-8 bg-sand-100 p-5 text-sm text-stone-600">
                            Produk ini sedang habis.
                            @if (config('shop.whatsapp'))
                                <a href="https://wa.me/{{ config('shop.whatsapp') }}?text={{ urlencode('Halo, kapan '.$product->name.' tersedia lagi?') }}" target="_blank" rel="noopener" class="link-underline font-semibold text-ink">Tanya ketersediaan</a>
                            @endif
                        </div>
                    @endif

                    <ul class="mt-8 grid grid-cols-3 gap-2 border-y border-stone-200 py-5 text-center text-xs font-medium text-stone-600">
                        @foreach ([['shield', 'Original'], ['truck', 'Kirim se-Indonesia'], ['refresh', 'Garansi tukar']] as [$icon, $label])
                            <li class="flex flex-col items-center gap-2"><x-icon :name="$icon" class="size-6 text-ink" /> {{ $label }}</li>
                        @endforeach
                    </ul>

                    {{-- Akordion --}}
                    @php
                        $sections = array_filter([
                            'desc' => $product->description ? ['Deskripsi', null] : null,
                            'detail' => ['Detail produk', null],
                            'ship' => ['Pengiriman & pembayaran', 'Pesanan dikirim dari gudang kami dengan kurir pilihanmu (JNE, SiCepat, J&T, POS, dan lainnya). Ongkir dihitung otomatis saat checkout. Bayar dengan transfer bank, e-wallet, atau QRIS.'],
                            'warranty' => ['Garansi', 'Semua produk original. Produk cacat pabrik bisa ditukar, cukup hubungi CS kami dengan menyertakan nomor pesanan.'],
                        ]);
                    @endphp
                    <div class="divide-y divide-stone-200 border-b border-stone-200" x-data="{ open: @js(array_key_first($sections)) }">
                        @foreach ($sections as $key => [$label, $text])
                            <div>
                                <button type="button" @click="open = open === '{{ $key }}' ? null : '{{ $key }}'" :aria-expanded="open === '{{ $key }}'"
                                        class="flex w-full items-center justify-between py-5 text-left text-sm font-semibold tracking-wide uppercase">
                                    {{ $label }}
                                    <span class="transition" :class="open === '{{ $key }}' && 'rotate-45'"><x-icon name="plus" class="size-4" /></span>
                                </button>
                                <div x-show="open === '{{ $key }}'" @if (! $loop->first) x-cloak @endif class="pb-6 text-sm leading-relaxed text-stone-600">
                                    @if ($key === 'desc')
                                        <div class="whitespace-pre-line">{{ $product->description }}</div>
                                    @elseif ($key === 'detail')
                                        <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2">
                                            @if ($product->sku)<dt class="text-stone-400">SKU</dt><dd>{{ $product->sku }}</dd>@endif
                                            @if ($product->category)<dt class="text-stone-400">Kategori</dt><dd>{{ $product->category->name }}</dd>@endif
                                            <dt class="text-stone-400">Berat</dt><dd>{{ $product->weight }} gr</dd>
                                        </dl>
                                    @else
                                        {{ $text }}
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        @if ($related->isNotEmpty())
            <section class="mt-20 lg:mt-28">
                <div class="flex items-end justify-between gap-4">
                    <h2 class="display text-3xl leading-none sm:text-5xl">Kamu mungkin suka</h2>
                    @if ($product->category)
                        <a href="{{ route('products.index', ['kategori' => $product->category->slug]) }}" class="link-underline shrink-0 text-sm font-semibold">Lihat semua</a>
                    @endif
                </div>
                <div class="no-scrollbar -mx-4 mt-8 flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-px-4 px-4 sm:mx-0 sm:grid sm:grid-cols-4 sm:gap-5 sm:overflow-visible sm:px-0">
                    @foreach ($related as $item)
                        <x-product-card :product="$item" class="w-[46%] shrink-0 snap-start sm:w-auto" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    {{-- Bar beli di mobile --}}
    @if ($product->inStock())
        <div class="fixed inset-x-0 bottom-0 z-30 border-t border-stone-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden">
            <div class="flex items-center gap-3 px-4 py-3">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs text-stone-500">{{ $product->name }}</p>
                    <p class="text-sm font-semibold">@rupiah($product->price)</p>
                </div>
                <button form="add-to-cart" class="btn btn-ink rounded-full px-6 py-3">Tambah ke keranjang</button>
            </div>
        </div>
    @endif
</x-layouts.shop>
