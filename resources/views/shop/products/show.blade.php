<x-layouts.shop :title="$product->name" :description="$product->metaDescription()" :image="$product->imageUrl()" type="product" :bottom-nav="! $product->inStock()" :whatsapp-context="$product->name.' — '.route('products.show', $product)">
    <x-slot:head>
        <meta property="product:price:amount" content="{{ $product->price }}">
        <meta property="product:price:currency" content="IDR">
        <meta property="product:availability" content="{{ $product->inStock() ? 'in stock' : 'out of stock' }}">
        {!! \App\Support\StructuredData::script(
            \App\Support\StructuredData::product($product, $reviews->getCollection()),
            \App\Support\StructuredData::breadcrumbs(array_values(array_filter([
                ['Beranda', route('home')],
                ['Produk', route('products.index')],
                $product->category?->parent ? [$product->category->parent->name, route('products.index', ['kategori' => $product->category->parent->slug])] : null,
                $product->category ? [$product->category->name, route('products.index', ['kategori' => $product->category->slug])] : null,
                [$product->name, route('products.show', $product)],
            ]))),
        ) !!}
    </x-slot:head>
    <div class="shop-container pb-28 pt-6 lg:pb-0 lg:pt-8">
        <nav class="flex flex-wrap items-center gap-1.5 text-xs text-stone-500" aria-label="Breadcrumb">
            <a href="{{ route('home') }}" class="hover:text-ink">Beranda</a>
            <span>/</span>
            <a href="{{ route('products.index') }}" class="hover:text-ink">Produk</a>
            @if ($product->category?->parent)
                <span>/</span>
                <a href="{{ route('products.index', ['kategori' => $product->category->parent->slug]) }}" class="hover:text-ink">{{ $product->category->parent->name }}</a>
            @endif
            @if ($product->category)
                <span>/</span>
                <a href="{{ route('products.index', ['kategori' => $product->category->slug]) }}" class="hover:text-ink">{{ $product->category->name }}</a>
            @endif
        </nav>

        @php
            $variants = $product->activeVariants;
            $pageConfig = [
                'images' => $product->images->map(fn ($i) => ['id' => $i->id, 'url' => $i->url()])->values(),
                'variants' => $variants->map(fn ($v) => [
                    'id' => $v->id, 'name' => $v->name, 'price' => $v->price, 'compare_at_price' => $v->compare_at_price,
                    'stock' => $v->stock, 'image_id' => $v->product_image_id, 'sku' => $v->sku,
                ])->values(),
                // Pilih otomatis bila hanya ada satu varian yang tersedia.
                'selectedId' => old('variant_id') ? (int) old('variant_id') : ($variants->where('stock', '>', 0)->count() === 1 ? $variants->firstWhere('stock', '>', 0)->id : null),
                'price' => $product->price,
                'compareAt' => $product->compare_at_price,
                'stock' => $product->stock,
            ];
            $priceRange = $variants->isNotEmpty() && $variants->pluck('price')->unique()->count() > 1;
            $lowStock = config('shop.low_stock_threshold');
        @endphp
        <div class="mt-5 grid gap-8 lg:grid-cols-12 lg:gap-14" x-data="productPage(@js($pageConfig))">
            {{-- Galeri --}}
            <div class="-mx-4 min-w-0 sm:mx-0 lg:col-span-7">
                <div class="relative">
                    @if ($product->images->isEmpty())
                        <x-product-image :product="$product" :eager="true" class="aspect-square sm:aspect-[4/5]" />
                    @else
                        <div class="relative aspect-square overflow-hidden bg-sand-100 sm:aspect-[4/5]">
                            @foreach ($product->images as $index => $image)
                                <img src="{{ $image->url() }}" alt="{{ $product->name }} — foto {{ $index + 1 }}"
                                     @if ($index > 0) loading="lazy" x-cloak @endif decoding="async"
                                     x-show="active === {{ $index }}" x-transition.opacity.duration.200ms
                                     class="absolute inset-0 size-full object-cover">
                            @endforeach
                        </div>
                        @if ($product->images->count() > 1)
                            <button type="button" @click="next(-1)" class="absolute left-3 top-1/2 grid size-10 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-ink shadow transition hover:bg-white" aria-label="Foto sebelumnya"><x-icon name="chevron-left" class="size-5" /></button>
                            <button type="button" @click="next(1)" class="absolute right-3 top-1/2 grid size-10 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-ink shadow transition hover:bg-white" aria-label="Foto berikutnya"><x-icon name="chevron-right" class="size-5" /></button>
                            <span class="absolute bottom-4 right-4 rounded-full bg-ink/70 px-2.5 py-1 text-xs font-semibold text-white" x-text="`${active + 1} / ${images.length}`">1 / {{ $product->images->count() }}</span>
                        @endif
                    @endif
                    <template x-if="discount">
                        <span class="absolute left-4 top-4 bg-brand-600 px-2.5 py-1 text-[11px] font-bold tracking-wider text-white uppercase" x-text="`Hemat ${discount}%`"></span>
                    </template>
                </div>
                @if ($product->images->count() > 1)
                    <div class="no-scrollbar mt-3 flex gap-2 overflow-x-auto px-4 sm:px-0">
                        @foreach ($product->images as $index => $image)
                            <button type="button" @click="active = {{ $index }}"
                                    class="size-16 shrink-0 overflow-hidden border-2 transition sm:size-20"
                                    :class="active === {{ $index }} ? 'border-ink' : 'border-transparent opacity-60 hover:opacity-100'"
                                    aria-label="Lihat foto {{ $index + 1 }}">
                                <img src="{{ $image->url() }}" alt="" loading="lazy" class="size-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Info --}}
            <div class="lg:col-span-5">
                <div class="lg:sticky lg:top-28">
                    @if ($product->category)
                        <a href="{{ route('products.index', ['kategori' => $product->category->slug]) }}" class="eyebrow text-stone-500 hover:text-ink">{{ $product->category->name }}</a>
                    @endif
                    <h1 class="mt-2 font-display text-3xl leading-[1.05] font-extrabold tracking-tight sm:text-4xl">{{ $product->name }}</h1>
                    @if ($product->hasReviews())
                        <a href="#ulasan" class="mt-3 inline-flex items-center gap-2 text-sm">
                            <x-star-rating :rating="$product->rating_avg" />
                            <span class="font-semibold">{{ number_format($product->rating_avg, 1, ',', '.') }}</span>
                            <span class="text-stone-500 underline underline-offset-4">{{ $product->review_count }} ulasan</span>
                        </a>
                    @endif

                    <div class="mt-4 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <p class="text-2xl font-semibold" :class="compareAt && 'text-brand-700'">
                            @if ($priceRange)<span x-show="!selected" class="text-base font-medium text-stone-500">Mulai</span>@endif
                            <span x-text="rupiah(price)">@rupiah($product->price)</span>
                        </p>
                        <p x-show="compareAt" @unless ($product->isOnSale()) x-cloak @endunless class="text-base text-stone-400 line-through" x-text="rupiah(compareAt)">@rupiah($product->compare_at_price)</p>
                    </div>

                    <p class="mt-4 flex items-center gap-2 text-sm">
                        <span class="size-2 rounded-full" :class="stock < 1 ? 'bg-stone-400' : (stock <= {{ $lowStock }} ? 'bg-amber-500' : 'bg-emerald-500')"></span>
                        <span x-text="stock < 1 ? 'Stok habis' : (stock <= {{ $lowStock }} ? `Stok terbatas, sisa ${stock}` : 'Stok tersedia, siap dikirim')">
                            {{ ! $product->inStock() ? 'Stok habis' : ($product->isLowStock() ? 'Stok terbatas, sisa '.$product->stock : 'Stok tersedia, siap dikirim') }}
                        </span>
                    </p>

                    @if ($variants->isNotEmpty())
                        <div class="mt-8">
                            <p class="eyebrow text-stone-500">
                                {{ $product->variant_label ?: 'Varian' }}:
                                <span class="normal-case tracking-normal text-ink" x-text="selected ? selected.name : 'pilih salah satu'"></span>
                            </p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($variants as $variant)
                                    <button type="button" @click="select(@js(['id' => $variant->id, 'stock' => $variant->stock, 'image_id' => $variant->product_image_id]))"
                                            @disabled(! $variant->inStock())
                                            :aria-pressed="selectedId === {{ $variant->id }}"
                                            :class="selectedId === {{ $variant->id }} ? 'chip-active' : ''"
                                            class="chip inline-flex items-center gap-2 disabled:cursor-not-allowed disabled:border-dashed disabled:text-stone-300 disabled:line-through">
                                        @if ($variant->image)
                                            <img src="{{ $variant->image->url() }}" alt="" class="size-5 rounded-full object-cover">
                                        @endif
                                        {{ $variant->name }}
                                        @unless ($variant->inStock())<span class="sr-only">(habis)</span>@endunless
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($product->inStock())
                        <form id="add-to-cart" method="POST" action="{{ route('cart.store', $product) }}" class="mt-8"
                              @submit="if (needsVariant) { $event.preventDefault(); $refs.variantHint.classList.remove('hidden'); $refs.variantHint.scrollIntoView({ behavior: 'smooth', block: 'center' }) }">
                            @csrf
                            @if ($variants->isNotEmpty())
                                <input type="hidden" name="variant_id" :value="selectedId ?? ''">
                            @endif
                            <p class="eyebrow text-stone-500">Jumlah</p>
                            <div class="mt-3 inline-flex items-center rounded-full border border-stone-300">
                                <button type="button" @click="qty = Math.max(1, qty - 1)" class="grid size-11 place-items-center rounded-full hover:bg-sand-100" aria-label="Kurangi"><x-icon name="minus" class="size-4" /></button>
                                <input type="number" name="quantity" x-model.number="qty" min="1" :max="max" value="1" aria-label="Jumlah"
                                       class="w-12 border-0 bg-transparent p-0 text-center text-base font-semibold lg:text-sm focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none">
                                <button type="button" @click="qty = Math.min(max, qty + 1)" class="grid size-11 place-items-center rounded-full hover:bg-sand-100" aria-label="Tambah"><x-icon name="plus" class="size-4" /></button>
                            </div>
                            <p x-ref="variantHint" x-show="needsVariant" class="mt-4 hidden text-sm font-medium text-brand-700">Pilih {{ mb_strtolower($product->variant_label ?: 'varian') }} terlebih dahulu.</p>
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

                    <x-wishlist-button :product="$product" variant="button" class="mt-3" />

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
                                            @if ($product->sku)<dt class="text-stone-400">SKU</dt><dd><span x-text="selected?.sku || @js($product->sku)">{{ $product->sku }}</span></dd>@endif
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

        @include('shop.products.partials.reviews')

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
                    <p class="text-sm font-semibold">{{ $priceRange ? 'Mulai ' : '' }}@rupiah($product->price)</p>
                </div>
                <button form="add-to-cart" class="btn btn-ink rounded-full px-6 py-3">Tambah ke keranjang</button>
            </div>
        </div>
    @endif
</x-layouts.shop>
