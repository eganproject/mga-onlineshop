<x-layouts.shop title="Checkout">
    <div class="shop-container py-10 lg:py-14">
        <h1 class="display border-b border-stone-200 pb-6 text-4xl leading-none sm:text-6xl">Checkout</h1>

        <x-cart-notices :notices="$adjustments" class="mt-4" />

        @error('cart')
            <div class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{{ $message }}</div>
        @enderror

        @php
            $voucherNotice = $voucherError ?: ($errors->first('voucher') ?: $errors->first('code'));
        @endphp
        <div x-data="checkout({
                  ratesUrl: @js(route('checkout.rates')),
                  voucherUrl: @js(route('checkout.voucher.apply')),
                  addressId: @js($address?->id),
                  rates: @js($quote['rates']),
                  error: @js($quote['error']),
                  subtotal: {{ $subtotal }},
                  selected: @js(old('shipping')),
                  voucher: @js($voucher),
                  voucherError: @js($voucherNotice),
                  code: @js(old('code', '')),
              })">
        {{-- Form voucher terpisah (tidak boleh bersarang di form checkout); input-nya memakai atribut form. --}}
        <form id="voucher-form" method="POST" action="{{ route('checkout.voucher.apply') }}" @submit.prevent="applyVoucher()">@csrf</form>
        <form id="voucher-remove-form" method="POST" action="{{ route('checkout.voucher.remove') }}" @submit.prevent="removeVoucher()">@csrf @method('DELETE')</form>

        <form method="POST" action="{{ route('checkout.store') }}" class="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-3 lg:gap-12"
              @submit="submitting = true">
            @csrf
            <div class="min-w-0 space-y-6 lg:col-span-2">
                {{-- Alamat --}}
                <section class="border border-stone-200 p-5 sm:p-6">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="flex items-center gap-2 font-display text-base font-extrabold tracking-tight uppercase sm:text-lg"><x-icon name="map-pin" class="size-5 text-ink" /> Alamat pengiriman</h2>
                        @if ($addresses->isNotEmpty())
                            <a href="{{ route('account.addresses.create', ['redirect_to' => 'checkout']) }}" class="shrink-0 text-sm font-semibold text-ink hover:underline hover:underline-offset-4">+ Baru</a>
                        @endif
                    </div>
                    @if ($addresses->isEmpty())
                        <div class="mt-4 border border-dashed border-stone-300 p-6 text-center">
                            <p class="text-sm text-stone-600">Kamu belum punya alamat pengiriman.</p>
                            <a href="{{ route('account.addresses.create', ['redirect_to' => 'checkout']) }}" class="btn btn-ink btn-pill mt-3">Tambah alamat</a>
                        </div>
                    @else
                        <div class="mt-4 grid gap-3 sm:grid-cols-2" role="radiogroup" aria-label="Alamat pengiriman">
                            @foreach ($addresses as $item)
                                <label class="relative block cursor-pointer border-2 p-4 text-sm transition"
                                       :class="addressId === '{{ $item->id }}' ? 'border-ink bg-sand-50' : 'border-stone-200 hover:border-stone-300'">
                                    <input type="radio" name="address_id" value="{{ $item->id }}" class="sr-only"
                                           @checked($address?->is($item)) @change="changeAddress({{ $item->id }})">
                                    <span class="flex items-center gap-2 font-semibold text-stone-900">
                                        {{ $item->label }}
                                        @if ($item->is_default)<span class="badge bg-brand-100 text-brand-700 ring-brand-600/20">Utama</span>@endif
                                    </span>
                                    <span class="mt-1 block text-stone-700">{{ $item->recipient_name }} · {{ $item->phone }}</span>
                                    <span class="block text-stone-500 line-clamp-2">{{ $item->fullAddress() }}</span>
                                    @unless ($item->canShip())
                                        <span class="mt-2 flex items-center gap-1.5 text-xs font-medium text-amber-700"><x-icon name="warning" class="size-4 shrink-0" /> Kecamatan belum dipilih</span>
                                    @endunless
                                    <a href="{{ route('account.addresses.edit', [$item, 'redirect_to' => 'checkout']) }}" class="mt-2 inline-block text-xs font-semibold text-stone-500 underline-offset-4 hover:text-ink hover:underline">
                                        {{ $item->canShip() ? 'Ubah' : 'Lengkapi alamat' }}
                                    </a>
                                </label>
                            @endforeach
                        </div>
                    @endif
                    <x-input-error for="address_id" />
                </section>

                {{-- Pengiriman --}}
                <section class="border border-stone-200 p-5 sm:p-6" aria-live="polite">
                    <div class="flex flex-wrap items-end justify-between gap-2">
                        <h2 class="flex items-center gap-2 font-display text-base font-extrabold tracking-tight uppercase sm:text-lg"><x-icon name="truck" class="size-5 text-ink" /> Pilih pengiriman</h2>
                        <p class="text-xs text-stone-500">Total berat {{ number_format(max($weight, 1) / 1000, 2, ',', '.') }} kg</p>
                    </div>

                    @if ($addresses->isEmpty())
                        <p class="mt-4 text-sm text-stone-500">Tambahkan alamat untuk melihat pilihan kurir.</p>
                    @else
                        {{-- Memuat --}}
                        <div x-show="loading" x-cloak class="mt-4 grid gap-2">
                            @for ($i = 0; $i < 3; $i++)
                                <div class="h-[70px] animate-pulse bg-stone-100"></div>
                            @endfor
                        </div>

                        {{-- Gagal / tidak ada kurir --}}
                        <div x-show="!loading && error" @if (! $quote['error']) x-cloak @endif class="mt-4 flex gap-2 rounded-2xl bg-amber-50 p-4 text-sm text-amber-800">
                            <x-icon name="warning" class="size-5 shrink-0" />
                            <div>
                                <p x-text="error">{{ $quote['error'] }}</p>
                                <button type="button" x-show="addressId" @click="changeAddress(addressId)" class="mt-1 text-xs font-semibold underline underline-offset-4">Coba lagi</button>
                            </div>
                        </div>

                        <div x-show="!loading && rates.length" x-cloak>
                            {{-- Filter kurir --}}
                            <div x-show="couriers.length > 1" class="-mx-1 mt-4 flex gap-2 overflow-x-auto px-1 pb-1">
                                <button type="button" @click="courier = ''" class="shrink-0 rounded-full border px-3.5 py-1.5 text-xs font-semibold transition"
                                        :class="courier === '' ? 'border-ink bg-ink text-white' : 'border-stone-300 text-stone-600 hover:border-stone-400'">Semua</button>
                                <template x-for="item in couriers" :key="item.code">
                                    <button type="button" @click="courier = item.code" class="shrink-0 rounded-full border px-3.5 py-1.5 text-xs font-semibold transition"
                                            :class="courier === item.code ? 'border-ink bg-ink text-white' : 'border-stone-300 text-stone-600 hover:border-stone-400'" x-text="item.name"></button>
                                </template>
                            </div>

                            <div class="mt-3 grid gap-2" role="radiogroup" aria-label="Layanan pengiriman">
                                <template x-for="item in visibleRates" :key="item.key">
                                    <label class="flex min-w-0 cursor-pointer items-center gap-3 border p-4 transition"
                                           :class="selected === item.key ? 'border-ink bg-sand-50' : 'border-stone-200 hover:border-stone-300'">
                                        <input type="radio" name="shipping" :value="item.key" x-model="selected" class="text-ink focus:ring-ink/20">
                                        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-stone-100 text-[11px] font-bold tracking-tight text-stone-700 uppercase" x-text="item.courier.slice(0, 4)"></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="flex flex-wrap items-center gap-1.5 text-sm font-semibold text-stone-900">
                                                <span x-text="`${item.courier_name} · ${item.service}`"></span>
                                                <span x-show="item.key === cheapestKey" class="badge bg-emerald-50 text-emerald-700 ring-emerald-600/20">Termurah</span>
                                                <span x-show="item.key === fastestKey" class="badge bg-sky-50 text-sky-700 ring-sky-600/20">Tercepat</span>
                                            </span>
                                            <span class="block text-xs text-stone-500" x-text="[item.description, item.etd ? `Estimasi ${item.etd}` : null].filter(Boolean).join(' · ')"></span>
                                        </span>
                                        <span class="shrink-0 text-sm font-bold text-stone-900" x-text="item.cost_label"></span>
                                    </label>
                                </template>
                            </div>
                        </div>

                        {{-- Tanpa JavaScript: tampilkan pilihan awal dari server. --}}
                        <noscript>
                            <div class="mt-4 grid gap-2">
                                @foreach ($quote['rates'] as $rate)
                                    <label class="flex items-center gap-3 border border-stone-200 p-4">
                                        <input type="radio" name="shipping" value="{{ $rate->key() }}" @checked($loop->first)>
                                        <span class="flex-1 text-sm font-semibold">{{ $rate->courierName }} · {{ $rate->service }} <span class="block text-xs font-normal text-stone-500">{{ $rate->etd }}</span></span>
                                        <span class="text-sm font-bold">@rupiah($rate->cost)</span>
                                    </label>
                                @endforeach
                            </div>
                        </noscript>
                    @endif
                    <x-input-error for="shipping" />
                </section>

                <section class="border border-stone-200 p-5 sm:p-6">
                    <x-form.textarea name="notes" label="Catatan untuk penjual" optional rows="2" maxlength="500" placeholder="Contoh: warna cadangan hitam" />
                </section>
            </div>

            {{-- Ringkasan --}}
            @php
                $initialShipping = $quote['rates'][0]->cost ?? 0;
                $initialDiscount = $voucher['discount'] ?? 0;
                $initialShippingDiscount = ($voucher['type'] ?? null) === 'free_shipping'
                    ? min($voucher['shipping_cap'] ?? $initialShipping, $initialShipping) : 0;
                $initialTotal = max(\App\Services\Promo\VoucherService::MIN_PAYABLE, $subtotal - $initialDiscount + $initialShipping - $initialShippingDiscount);
            @endphp
            <div class="min-w-0 lg:sticky lg:top-24 lg:self-start">
                <div class="border border-stone-200 p-5 sm:p-6">
                    <h2 class="font-display text-lg font-extrabold tracking-tight uppercase">Ringkasan pesanan</h2>
                    <ul class="mt-4 max-h-64 space-y-3 overflow-y-auto">
                        @foreach ($lines as $line)
                            <li class="flex gap-3">
                                @if ($line->variant?->image)
                                    <img src="{{ $line->imageUrl() }}" alt="" class="size-12 shrink-0 rounded-lg object-cover">
                                @else
                                    <x-product-image :product="$line->product" class="size-12 shrink-0 rounded-lg" />
                                @endif
                                <div class="min-w-0 flex-1 text-sm">
                                    <p class="truncate font-medium text-stone-800">{{ $line->product->name }}</p>
                                    @if ($line->variant)<p class="truncate text-xs text-stone-500">{{ $line->variant->name }}</p>@endif
                                    <p class="text-xs text-stone-500">{{ $line->quantity }} × @rupiah($line->unitPrice())</p>
                                </div>
                                <p class="text-sm font-semibold">@rupiah($line->subtotal())</p>
                            </li>
                        @endforeach
                    </ul>
                    {{-- Voucher --}}
                    <div class="mt-4 border-t border-stone-100 pt-4" aria-live="polite">
                        <p class="flex items-center gap-2 text-sm font-semibold text-stone-900"><x-icon name="ticket" class="size-4" /> Voucher</p>

                        <div x-show="voucher" @if (! $voucher) x-cloak @endif class="mt-3 flex items-center gap-3 border-2 border-dashed border-emerald-300 bg-emerald-50/70 p-3">
                            <x-icon name="check-circle" class="size-5 shrink-0 text-emerald-600" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-mono text-sm font-bold tracking-wide text-stone-900" x-text="voucher?.code">{{ $voucher['code'] ?? '' }}</p>
                                <p class="truncate text-xs text-emerald-800" x-text="voucher?.benefit">{{ $voucher['benefit'] ?? '' }}</p>
                            </div>
                            <button form="voucher-remove-form" class="shrink-0 text-xs font-semibold text-stone-500 underline-offset-4 hover:text-rose-600 hover:underline" :disabled="voucherLoading">Lepas</button>
                        </div>

                        <div x-show="!voucher" @if ($voucher) x-cloak @endif class="mt-3 flex gap-2">
                            <label for="voucher-code" class="sr-only">Kode voucher</label>
                            <input id="voucher-code" name="code" form="voucher-form" x-model="voucherCode" value="{{ old('code') }}" maxlength="32" autocomplete="off" autocapitalize="characters" spellcheck="false"
                                   placeholder="Masukkan kode voucher" class="input min-w-0 flex-1 uppercase placeholder:normal-case">
                            <button form="voucher-form" class="btn btn-outline shrink-0" :disabled="voucherLoading || !voucherCode.trim()">
                                <span x-text="voucherLoading ? '...' : 'Pakai'">Pakai</span>
                            </button>
                        </div>

                        <p x-show="voucherError" @unless ($voucherNotice) x-cloak @endunless class="mt-2 text-xs font-medium text-rose-600" x-text="voucherError">{{ $voucherNotice }}</p>
                        <p x-show="voucherMessage" x-cloak class="mt-2 text-xs font-medium text-emerald-700" x-text="voucherMessage"></p>

                        @if ($suggestions->isNotEmpty())
                            <div x-show="!voucher" @if ($voucher) x-cloak @endif class="mt-3 space-y-2">
                                <p class="text-xs text-stone-500">Voucher tersedia</p>
                                @foreach ($suggestions as ['voucher' => $item, 'error' => $itemError])
                                    <button type="button" @click="applyVoucher(@js($item->code))" :disabled="voucherLoading" @disabled($itemError)
                                            @class(['flex w-full items-center gap-3 border p-3 text-left transition', 'border-stone-200 hover:border-ink' => ! $itemError, 'cursor-not-allowed border-stone-100 opacity-60' => $itemError])>
                                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-600"><x-icon :name="$item->type === \App\Enums\VoucherType::FreeShipping ? 'truck' : 'ticket'" class="size-4" /></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-semibold text-stone-900">{{ $item->benefitLabel() }}</span>
                                            <span class="block text-xs text-stone-500">{{ $itemError ?: $item->termsLabel() }}</span>
                                        </span>
                                        <span class="shrink-0 font-mono text-[11px] font-bold text-stone-600">{{ $item->code }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <dl class="mt-4 space-y-2 border-t border-stone-100 pt-4 text-sm">
                        <div class="flex justify-between"><dt class="text-stone-500">Subtotal ({{ $lines->sum('quantity') }} item)</dt><dd>@rupiah($subtotal)</dd></div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-stone-500">Ongkir <span x-show="rate" x-cloak class="text-xs" x-text="rate ? `(${rate.courier_name} ${rate.service})` : ''"></span></dt>
                            <dd x-text="rate ? rupiah(shipping) : '—'">@rupiah($initialShipping)</dd>
                        </div>
                        <div x-show="discount > 0" @unless ($initialDiscount) x-cloak @endunless class="flex justify-between text-emerald-700">
                            <dt>Diskon voucher</dt><dd x-text="'−' + rupiah(discount)">−@rupiah($initialDiscount)</dd>
                        </div>
                        <div x-show="shippingDiscount > 0" @unless ($initialShippingDiscount) x-cloak @endunless class="flex justify-between text-emerald-700">
                            <dt>Potongan ongkir</dt><dd x-text="'−' + rupiah(shippingDiscount)">−@rupiah($initialShippingDiscount)</dd>
                        </div>
                        <div class="flex justify-between border-t border-stone-100 pt-2 text-base font-bold text-stone-900"><dt>Total</dt><dd x-text="rupiah(total)">@rupiah($initialTotal)</dd></div>
                    </dl>
                    <button class="btn btn-ink btn-pill mt-6 w-full py-4 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!canSubmit" @disabled(! $address || empty($quote['rates']))>
                        <x-icon name="lock" class="size-4" />
                        <span x-text="submitting ? 'Memproses...' : 'Buat pesanan & bayar'">Buat pesanan & bayar</span>
                    </button>
                    <p class="mt-3 text-center text-xs text-stone-400">Pembayaran aman diproses oleh payment gateway.</p>
                </div>
            </div>
        </form>
        </div>
    </div>
</x-layouts.shop>
