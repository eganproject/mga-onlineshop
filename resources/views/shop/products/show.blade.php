<x-layouts.shop :title="$product->name">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <nav class="mb-6 flex flex-wrap items-center gap-1.5 text-xs text-stone-500">
            <a href="{{ route('home') }}" class="hover:text-stone-800">Beranda</a>
            <x-icon name="chevron-right" class="size-3" />
            <a href="{{ route('products.index') }}" class="hover:text-stone-800">Produk</a>
            @if ($product->category?->parent)
                <x-icon name="chevron-right" class="size-3" />
                <a href="{{ route('products.index', ['kategori' => $product->category->parent->slug]) }}" class="hover:text-stone-800">{{ $product->category->parent->name }}</a>
            @endif
            @if ($product->category)
                <x-icon name="chevron-right" class="size-3" />
                <a href="{{ route('products.index', ['kategori' => $product->category->slug]) }}" class="hover:text-stone-800">{{ $product->category->name }}</a>
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
        @endphp
        <div class="grid gap-8 lg:grid-cols-2 lg:gap-12" x-data="productPage(@js($pageConfig))">
            {{-- Galeri --}}
            <div class="min-w-0 lg:sticky lg:top-24 lg:self-start">
                <div class="relative overflow-hidden rounded-3xl border border-stone-200/70 bg-white p-2">
                    @if ($product->images->isEmpty())
                        <x-product-image :product="$product" class="aspect-square rounded-2xl" />
                    @else
                        <div class="relative aspect-square overflow-hidden rounded-2xl bg-stone-100">
                            @foreach ($product->images as $index => $image)
                                <img src="{{ $image->url() }}" alt="{{ $product->name }} — foto {{ $index + 1 }}"
                                     @if ($index > 0) loading="lazy" @endif
                                     x-show="active === {{ $index }}" @if ($index > 0) x-cloak @endif
                                     x-transition.opacity.duration.200ms
                                     class="absolute inset-0 size-full object-cover">
                            @endforeach
                        </div>
                        @if ($product->images->count() > 1)
                            <button type="button" @click="next(-1)" class="absolute left-4 top-1/2 grid size-10 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-stone-700 shadow-lg transition hover:bg-white" aria-label="Foto sebelumnya"><x-icon name="chevron-left" class="size-5" /></button>
                            <button type="button" @click="next(1)" class="absolute right-4 top-1/2 grid size-10 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-stone-700 shadow-lg transition hover:bg-white" aria-label="Foto berikutnya"><x-icon name="chevron-right" class="size-5" /></button>
                            <span class="absolute bottom-4 right-4 rounded-full bg-stone-900/70 px-2.5 py-1 text-xs font-semibold text-white" x-text="`${active + 1} / ${images.length}`">1 / {{ $product->images->count() }}</span>
                        @endif
                    @endif
                </div>
                @if ($product->images->count() > 1)
                    <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                        @foreach ($product->images as $index => $image)
                            <button type="button" @click="active = {{ $index }}"
                                    class="size-16 shrink-0 overflow-hidden rounded-xl border-2 transition sm:size-20"
                                    :class="active === {{ $index }} ? 'border-brand-500' : 'border-transparent opacity-70 hover:opacity-100'"
                                    aria-label="Lihat foto {{ $index + 1 }}">
                                <img src="{{ $image->url() }}" alt="" loading="lazy" class="size-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div>
                @if ($product->category)
                    <p class="text-xs font-bold tracking-wider text-brand-600 uppercase">{{ $product->category->name }}</p>
                @endif
                <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-stone-900 sm:text-3xl">{{ $product->name }}</h1>
                @if ($product->sku)<p class="mt-1 text-xs text-stone-400">SKU: {{ $product->sku }}</p>@endif

                <div class="mt-5 flex flex-wrap items-end gap-3">
                    <p class="text-3xl font-extrabold text-stone-900">
                        @if ($priceRange)<span x-show="!selected" class="text-base font-semibold text-stone-500">Mulai</span>@endif
                        <span x-text="rupiah(price)">@rupiah($product->price)</span>
                    </p>
                    <template x-if="compareAt">
                        <div class="flex items-end gap-3">
                            <p class="pb-1 text-base text-stone-400 line-through" x-text="rupiah(compareAt)"></p>
                            <span class="mb-1 rounded-lg bg-rose-50 px-2 py-0.5 text-xs font-bold text-rose-600" x-text="`Hemat ${discount}%`"></span>
                        </div>
                    </template>
                </div>

                <div class="mt-5 flex flex-wrap gap-2 text-sm">
                    <span x-show="stock > 0" class="badge bg-emerald-50 py-1 text-emerald-700 ring-emerald-600/20">
                        <x-icon name="check" class="size-3.5" />
                        <span x-text="stock <= {{ config('shop.low_stock_threshold') }} ? `Stok tersedia · sisa ${stock}` : 'Stok tersedia'">Stok tersedia</span>
                    </span>
                    <span x-show="stock < 1" @if ($product->inStock()) x-cloak @endif class="badge bg-stone-100 py-1 text-stone-600 ring-stone-500/20">Stok habis</span>
                    <span class="badge bg-stone-50 py-1 text-stone-600 ring-stone-500/10">Berat {{ $product->weight }} gr</span>
                </div>

                @if ($variants->isNotEmpty())
                    <div class="mt-6">
                        <p class="label">
                            {{ $product->variant_label ?: 'Varian' }}:
                            <span class="font-normal text-stone-500" x-text="selected ? selected.name : 'Pilih salah satu'"></span>
                        </p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($variants as $variant)
                                <button type="button" @click="select(@js(['id' => $variant->id, 'stock' => $variant->stock, 'image_id' => $variant->product_image_id]))"
                                        @disabled(! $variant->inStock())
                                        :class="selectedId === {{ $variant->id }} ? 'border-brand-500 bg-brand-50 text-brand-700 ring-2 ring-brand-500/20' : 'border-stone-200 bg-white text-stone-700 hover:border-stone-300'"
                                        class="inline-flex items-center gap-2 rounded-xl border px-3.5 py-2 text-sm font-semibold transition disabled:cursor-not-allowed disabled:border-dashed disabled:text-stone-300">
                                    @if ($variant->image)
                                        <img src="{{ $variant->image->url() }}" alt="" class="size-6 rounded-md object-cover">
                                    @endif
                                    {{ $variant->name }}
                                    @unless ($variant->inStock())<span class="text-[10px] font-medium">(habis)</span>@endunless
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($product->inStock())
                    <form method="POST" action="{{ route('cart.store', $product) }}" class="mt-8 space-y-4">
                        @csrf
                        @if ($variants->isNotEmpty())
                            <input type="hidden" name="variant_id" :value="selectedId ?? ''">
                        @endif
                        <div>
                            <p class="label">Jumlah</p>
                            <div class="inline-flex items-center rounded-xl border border-stone-200 bg-white">
                                <button type="button" @click="qty = Math.max(1, qty - 1)" class="p-3 text-stone-500 hover:text-stone-900" aria-label="Kurangi"><x-icon name="minus" class="size-4" /></button>
                                <input type="number" name="quantity" x-model.number="qty" min="1" :max="max" class="w-14 border-0 text-center text-sm font-semibold focus:ring-0" aria-label="Jumlah">
                                <button type="button" @click="qty = Math.min(max, qty + 1)" class="p-3 text-stone-500 hover:text-stone-900" aria-label="Tambah"><x-icon name="plus" class="size-4" /></button>
                            </div>
                        </div>
                        <p x-show="needsVariant" x-cloak class="text-sm font-medium text-amber-700">Pilih {{ mb_strtolower($product->variant_label ?: 'varian') }} terlebih dahulu.</p>
                        <div class="flex flex-col gap-3 sm:flex-row">
                            <button :disabled="needsVariant || stock < 1" class="btn btn-secondary flex-1 py-3 disabled:opacity-50"><x-icon name="cart" class="size-5" /> Tambah ke keranjang</button>
                            <button name="buy_now" value="1" :disabled="needsVariant || stock < 1" class="btn btn-primary flex-1 py-3 disabled:opacity-50">Beli sekarang</button>
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
