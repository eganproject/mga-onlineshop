<x-layouts.shop :title="$product->seoTitle()" :description="$product->metaDescription()" :image="$product->imageUrl()" type="product" :noindex="! $product->is_active" :bottom-nav="! $product->inStock()" :whatsapp-context="$product->name.' — '.route('products.show', $product)">
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
        @unless ($product->is_active)
            {{-- Hanya staf yang bisa sampai di sini (lihat Product::isViewableBy). --}}
            <div class="mb-5 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200" role="status">
                <x-icon name="eye" class="size-4 shrink-0" />
                <p class="min-w-0 flex-1"><span class="font-semibold">Pratinjau admin.</span> Produk ini nonaktif, jadi pelanggan belum bisa melihat atau membelinya.</p>
                @can('catalog.manage')
                    <a href="{{ route('admin.products.edit', $product) }}" class="font-semibold underline underline-offset-4">Edit produk</a>
                @endcan
            </div>
        @endunless
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
                    'tiers' => \App\Services\Catalog\Wholesale::tiers($product, $v),
                ])->values(),
                // Sebelum varian dipilih, tabel grosir memakai varian termurah yang punya grosir.
                'tiers' => $variants->isEmpty()
                    ? \App\Services\Catalog\Wholesale::tiers($product)
                    : (($v = $variants->sortBy('price')->first(fn ($v) => \App\Services\Catalog\Wholesale::tiers($product, $v) !== [])) ? \App\Services\Catalog\Wholesale::tiers($product, $v) : []),
                'minQty' => min($product->minOrderQty(), max(1, $product->stock)),
                // Pilih otomatis bila hanya ada satu varian yang tersedia.
                'selectedId' => old('variant_id') ? (int) old('variant_id') : ($variants->where('stock', '>', 0)->count() === 1 ? $variants->firstWhere('stock', '>', 0)->id : null),
                'price' => $product->price,
                'compareAt' => $product->compare_at_price,
                'stock' => $product->stock,
            ];
            $priceRange = $variants->isNotEmpty() && $variants->pluck('price')->unique()->count() > 1;
            $lowStock = config('shop.low_stock_threshold');
            $imageCount = $product->images->count();
        @endphp
        <div class="mt-5 grid grid-cols-1 gap-8 lg:grid-cols-12 lg:gap-14" x-data="productPage(@js($pageConfig))">
            {{-- Galeri: desktop = thumbnail vertikal di kiri + zoom saat kursor diarahkan;
                 HP = geser foto, ketuk untuk layar penuh (bisa dicubit/zoom). --}}
            <div class="-mx-4 min-w-0 sm:mx-0 lg:col-span-7">
                <div @class(['flex flex-col', 'lg:grid lg:grid-cols-[72px_minmax(0,1fr)] lg:gap-4 xl:grid-cols-[84px_minmax(0,1fr)]' => $imageCount > 1])>
                    <div class="relative lg:order-2">
                        @if ($product->images->isEmpty())
                            <x-product-image :product="$product" :eager="true" class="aspect-square sm:aspect-[4/5]" />
                        @else
                            <div x-ref="track" @scroll.debounce.80ms="syncFromScroll()"
                                 class="no-scrollbar flex aspect-square snap-x snap-mandatory overflow-x-auto overscroll-x-contain bg-sand-100 sm:aspect-[4/5]"
                                 aria-roledescription="carousel" aria-label="Foto {{ $product->name }}">
                                @foreach ($product->images as $index => $image)
                                    <a href="{{ $image->url() }}" @click.prevent="openLightbox({{ $index }})"
                                       @mouseenter="zoomStart($event)" @mousemove="zoomMove($event)" @mouseleave="zoomEnd($event)"
                                       class="gallery-slide relative block size-full shrink-0 snap-center snap-always overflow-hidden"
                                       aria-label="Perbesar foto {{ $index + 1 }} dari {{ $imageCount }}">
                                        <img src="{{ $image->url() }}" alt="{{ $product->name }} — foto {{ $index + 1 }}"
                                             @if ($index > 0) loading="lazy" @else fetchpriority="high" @endif decoding="async"
                                             class="gallery-zoom size-full object-cover" draggable="false">
                                    </a>
                                @endforeach
                            </div>
                            @if ($imageCount > 1)
                                <button type="button" @click="next(-1)" class="absolute left-3 top-1/2 hidden size-10 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-ink shadow transition hover:bg-white sm:grid" aria-label="Foto sebelumnya"><x-icon name="chevron-left" class="size-5" /></button>
                                <button type="button" @click="next(1)" class="absolute right-3 top-1/2 hidden size-10 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-ink shadow transition hover:bg-white sm:grid" aria-label="Foto berikutnya"><x-icon name="chevron-right" class="size-5" /></button>
                                <span class="pointer-events-none absolute bottom-4 right-4 rounded-full bg-ink/70 px-2.5 py-1 text-xs font-semibold text-white" x-text="`${active + 1} / ${images.length}`">1 / {{ $imageCount }}</span>
                            @endif
                            <button type="button" @click="openLightbox(active)"
                                    class="absolute bottom-4 left-4 inline-flex items-center gap-1.5 rounded-full bg-white/90 px-3 py-1.5 text-xs font-semibold text-ink shadow transition hover:bg-white"
                                    aria-label="Lihat foto layar penuh">
                                <x-icon name="search" class="size-3.5" />
                                <span class="hidden lg:inline">Arahkan kursor untuk zoom · klik untuk layar penuh</span>
                                <span class="lg:hidden">Ketuk untuk perbesar</span>
                            </button>
                        @endif
                        <template x-if="discount">
                            <span class="pointer-events-none absolute left-4 top-4 bg-brand-600 px-2.5 py-1 text-[11px] font-bold tracking-wider text-white uppercase" x-text="`Hemat ${discount}%`"></span>
                        </template>
                    </div>

                    @if ($imageCount > 1)
                        {{-- Thumbnail: baris di bawah (HP/tablet), kolom vertikal di kiri (desktop). --}}
                        <div class="relative mt-3 lg:order-1 lg:mt-0">
                            <div x-ref="thumbs" class="no-scrollbar flex gap-2 overflow-x-auto px-4 sm:px-0 lg:absolute lg:inset-0 lg:flex-col lg:overflow-x-hidden lg:overflow-y-auto lg:overscroll-contain">
                                @foreach ($product->images as $index => $image)
                                    <button type="button" @click="go({{ $index }})" @mouseenter="hoverThumb({{ $index }})"
                                            class="aspect-square w-16 shrink-0 overflow-hidden border-2 bg-sand-100 transition sm:w-20 lg:aspect-[4/5] lg:w-full"
                                            :class="active === {{ $index }} ? 'border-ink' : 'border-transparent opacity-60 hover:opacity-100'"
                                            :aria-current="active === {{ $index }}"
                                            aria-label="Lihat foto {{ $index + 1 }}">
                                        <img src="{{ $image->url() }}" alt="" loading="lazy" decoding="async" class="size-full object-cover">
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Info --}}
            <div class="lg:col-span-5">
                <div class="lg:sticky lg:top-28">
                    @if ($product->category)
                        <a href="{{ route('products.index', ['kategori' => $product->category->slug]) }}" class="eyebrow text-stone-500 hover:text-ink">{{ $product->category->name }}</a>
                    @endif
                    <h1 class="mt-2 font-display text-3xl leading-[1.05] font-extrabold tracking-tight sm:text-4xl">{{ $product->name }}</h1>
                    @if ($product->hasReviews() || $product->sold_count > 0)
                        <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                            @if ($product->hasReviews())
                                <a href="#ulasan" class="inline-flex items-center gap-2">
                                    <x-star-rating :rating="$product->rating_avg" />
                                    <span class="font-semibold">{{ number_format($product->rating_avg, 1, ',', '.') }}</span>
                                    <span class="text-stone-500 underline underline-offset-4">{{ $product->review_count }} ulasan</span>
                                </a>
                            @endif
                            @if ($product->hasReviews() && $product->sold_count > 0)
                                <span aria-hidden="true" class="h-4 w-px bg-stone-300"></span>
                            @endif
                            @if ($product->sold_count > 0)
                                <span class="text-stone-500"><span class="font-semibold text-ink">{{ $product->soldLabel() }}</span> terjual</span>
                            @endif
                        </div>
                    @endif

                    <div class="mt-4 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <p class="text-2xl font-semibold" :class="compareAt && 'text-brand-700'">
                            @if ($priceRange)<span x-show="!selected" class="text-base font-medium text-stone-500">Mulai</span>@endif
                            <span x-text="rupiah(price)">@rupiah($product->price)</span>
                        </p>
                        <p x-show="compareAt" @unless ($product->isOnSale()) x-cloak @endunless class="text-base text-stone-400 line-through" x-text="rupiah(compareAt)">@rupiah($product->compare_at_price)</p>
                    </div>
                    @if ($taxNote = \App\Services\Billing\BillingSettings::current()->priceNote())
                        <p class="mt-1 text-xs text-stone-500">{{ $taxNote }}</p>
                    @endif

                    {{-- Harga grosir bertingkat: baris aktif mengikuti jumlah; klik baris = isi jumlahnya. --}}
                    @if ($product->hasWholesale())
                        <div class="mt-5 rounded-2xl border border-accent-200 bg-accent-50/50 p-4" x-show="tiers.length" data-wholesale-table>
                            <p class="flex items-center gap-2 text-sm font-semibold text-ink">
                                <x-icon name="tag" class="size-4 text-accent-600" /> Harga grosir
                                @if ($variants->isNotEmpty())<span class="text-xs font-normal text-stone-500" x-text="selected ? selected.name : 'boleh campur varian'"></span>@endif
                            </p>
                            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4" role="list">
                                <template x-for="row in tierRows" :key="row.min_qty">
                                    <button type="button" role="listitem" @click="setQty(row.min_qty)"
                                            class="rounded-xl border bg-white px-3 py-2 text-left transition"
                                            :class="row.active ? 'border-accent-500 ring-2 ring-accent-500/20' : 'border-stone-200 hover:border-stone-400'">
                                        <span class="block text-xs text-stone-500" x-text="row.label"></span>
                                        <span class="block text-sm font-semibold tabular-nums" x-text="rupiah(row.price)"></span>
                                        <span x-show="row.save > 0" class="block text-[11px] font-semibold text-accent-700" x-text="`Hemat ${row.save}%`"></span>
                                    </button>
                                </template>
                            </div>
                            @if ($variants->isNotEmpty())
                                <p class="mt-2 text-xs text-stone-500">Jumlah dihitung gabungan semua {{ mb_strtolower($product->variant_label ?: 'varian') }} produk ini di keranjang.</p>
                            @endif
                        </div>
                    @endif

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
                        <form id="add-to-cart" method="POST" action="{{ route('cart.store', $product) }}" class="mt-8" data-cart-form
                              @submit="if (needsVariant) { $event.preventDefault(); $refs.variantHint.classList.remove('hidden'); $refs.variantHint.scrollIntoView({ behavior: 'smooth', block: 'center' }) }">
                            @csrf
                            @if ($variants->isNotEmpty())
                                <input type="hidden" name="variant_id" :value="selectedId ?? ''">
                            @endif
                            <p class="eyebrow text-stone-500">Jumlah</p>
                            <div class="mt-3 inline-flex items-center rounded-full border border-stone-300">
                                <button type="button" @click="setQty(qty - 1)" class="grid size-11 place-items-center rounded-full hover:bg-sand-100" aria-label="Kurangi"><x-icon name="minus" class="size-4" /></button>
                                <input type="number" name="quantity" x-model.number="qty" min="1" :max="max" value="{{ min($product->minOrderQty(), max(1, $product->stock)) }}" aria-label="Jumlah"
                                       class="w-14 border-0 bg-transparent p-0 text-center text-base font-semibold lg:text-sm focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none">
                                <button type="button" @click="setQty(qty + 1)" class="grid size-11 place-items-center rounded-full hover:bg-sand-100" aria-label="Tambah"><x-icon name="plus" class="size-4" /></button>
                            </div>
                            @if ($product->minOrderQty() > 1)
                                <p class="mt-2 text-xs text-stone-500">Minimal pembelian {{ $product->minOrderQty() }} pcs{{ $variants->isNotEmpty() ? ' (boleh campur varian)' : '' }}.</p>
                            @endif
                            <p x-show="activeTier" x-cloak class="mt-2 text-sm text-accent-700" aria-live="polite">
                                Harga grosir <span class="font-semibold" x-text="rupiah(unitPrice)"></span>/pcs ·
                                total <span class="font-semibold" x-text="rupiah(unitPrice * qty)"></span>
                            </p>
                            <p x-ref="variantHint" x-show="needsVariant" class="mt-4 hidden text-sm font-medium text-brand-700">Pilih {{ mb_strtolower($product->variant_label ?: 'varian') }} terlebih dahulu.</p>
                            <div class="mt-6 grid gap-3">
                                <button class="btn btn-ink btn-pill w-full py-4 text-[15px]">Tambah ke keranjang</button>
                                <button name="buy_now" value="1" data-cart-native class="btn btn-outline btn-pill w-full py-4 text-[15px]">Beli sekarang</button>
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

                    @if (config('shop.whatsapp') && $product->inStock())
                        @php
                            $lastTier = $product->hasWholesale() ? collect($pageConfig['tiers'])->last() : null;
                        @endphp
                        <p class="mt-4 text-sm text-stone-600" data-wholesale-whatsapp>
                            {{ $lastTier ? 'Butuh lebih dari '.$lastTier['min_qty'].' pcs?' : 'Beli dalam jumlah banyak?' }}
                            <a href="https://wa.me/{{ config('shop.whatsapp') }}?text={{ urlencode('Halo, saya ingin beli '.$product->name.' dalam jumlah banyak. Bisa minta harga khusus?') }}"
                               x-bind:href="'https://wa.me/{{ config('shop.whatsapp') }}?text=' + encodeURIComponent('Halo, saya ingin beli ' + @js($product->name) + (selected ? ' (' + selected.name + ')' : '') + ' sebanyak ' + qty + ' pcs. Bisa minta harga khusus?')"
                               target="_blank" rel="noopener" class="link-underline font-semibold text-ink">Minta harga khusus</a>
                        </p>
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

                    <div class="mt-6">
                        @include('shop.partials.share', ['shareUrl' => route('products.show', $product), 'shareText' => $product->name])
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
