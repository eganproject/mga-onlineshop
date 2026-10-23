<x-layouts.shop title="Checkout">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-extrabold tracking-tight text-stone-900 sm:text-3xl">Checkout</h1>

        @error('cart')
            <div class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{{ $message }}</div>
        @enderror

        <form method="POST" action="{{ route('checkout.store') }}" class="mt-6 grid gap-6 lg:grid-cols-3" x-data>
            @csrf
            <div class="space-y-6 lg:col-span-2">
                {{-- Alamat --}}
                <div class="card p-5">
                    <div class="flex items-center justify-between">
                        <h2 class="flex items-center gap-2 font-bold text-stone-900"><x-icon name="map-pin" class="size-5 text-brand-600" /> Alamat pengiriman</h2>
                        <a href="{{ route('account.addresses.create', ['redirect_to' => 'checkout']) }}" class="text-sm font-semibold text-brand-600 hover:text-brand-700">+ Alamat baru</a>
                    </div>
                    @if ($addresses->isEmpty())
                        <div class="mt-4 rounded-2xl border border-dashed border-stone-300 p-6 text-center">
                            <p class="text-sm text-stone-600">Kamu belum punya alamat pengiriman.</p>
                            <a href="{{ route('account.addresses.create', ['redirect_to' => 'checkout']) }}" class="btn btn-primary mt-3">Tambah alamat</a>
                        </div>
                    @else
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            @foreach ($addresses as $item)
                                <a href="{{ route('checkout.index', ['alamat' => $item->id]) }}"
                                   @class(['block rounded-2xl border-2 p-4 text-sm transition', 'border-brand-500 bg-brand-50/50' => $address?->is($item), 'border-stone-200 hover:border-stone-300' => ! $address?->is($item)])>
                                    <p class="flex items-center gap-2 font-semibold text-stone-900">
                                        {{ $item->label }}
                                        @if ($item->is_default)<span class="badge bg-brand-100 text-brand-700 ring-brand-600/20">Utama</span>@endif
                                    </p>
                                    <p class="mt-1 text-stone-700">{{ $item->recipient_name }} · {{ $item->phone }}</p>
                                    <p class="text-stone-500 line-clamp-2">{{ $item->fullAddress() }}</p>
                                </a>
                            @endforeach
                        </div>
                        <input type="hidden" name="address_id" value="{{ $address?->id }}">
                    @endif
                    <x-input-error for="address_id" />
                </div>

                {{-- Pengiriman --}}
                <div class="card p-5">
                    <h2 class="flex items-center gap-2 font-bold text-stone-900"><x-icon name="truck" class="size-5 text-brand-600" /> Pilih pengiriman</h2>
                    <p class="mt-1 text-xs text-stone-500">Total berat {{ number_format($weight / 1000, 2, ',', '.') }} kg</p>
                    @if ($shippingError)
                        <div class="mt-4 flex gap-2 rounded-2xl bg-amber-50 p-4 text-sm text-amber-800"><x-icon name="warning" class="size-5 shrink-0" /> {{ $shippingError }}</div>
                    @elseif (! $address)
                        <p class="mt-4 text-sm text-stone-500">Tambahkan alamat untuk melihat pilihan kurir.</p>
                    @else
                        <div class="mt-4 grid gap-2">
                            @foreach ($rates as $rate)
                                <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-stone-200 p-4 transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50/50">
                                    <input type="radio" name="shipping" value="{{ $rate->key() }}" class="text-brand-600 focus:ring-brand-500/30" @checked(old('shipping') === $rate->key() || ($loop->first && ! old('shipping')))
                                           data-cost="{{ $rate->cost }}" @change="$dispatch('shipping-changed', {{ $rate->cost }})">
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold text-stone-900">{{ $rate->courierName }} · {{ $rate->service }}</p>
                                        <p class="text-xs text-stone-500">{{ $rate->description }}@if($rate->etd) · Estimasi {{ $rate->etd }}@endif</p>
                                    </div>
                                    <p class="text-sm font-bold text-stone-900">@rupiah($rate->cost)</p>
                                </label>
                            @endforeach
                        </div>
                    @endif
                    <x-input-error for="shipping" />
                </div>

                <div class="card p-5">
                    <label for="notes" class="label">Catatan untuk penjual <span class="font-normal text-stone-400">(opsional)</span></label>
                    <textarea id="notes" name="notes" rows="2" placeholder="Contoh: warna cadangan hitam" class="input">{{ old('notes') }}</textarea>
                </div>
            </div>

            {{-- Ringkasan --}}
            @php($initialShipping = collect($rates)->first(fn ($r) => $r->key() === old('shipping'))?->cost ?? ($rates[0]->cost ?? 0))
            <div class="lg:sticky lg:top-24 lg:self-start" x-data="{ shipping: {{ $initialShipping }}, subtotal: {{ $subtotal }} }" @shipping-changed.window="shipping = $event.detail">
                <div class="card p-5">
                    <h2 class="font-bold text-stone-900">Ringkasan pesanan</h2>
                    <ul class="mt-4 max-h-64 space-y-3 overflow-y-auto">
                        @foreach ($lines as $line)
                            <li class="flex gap-3">
                                <x-product-image :product="$line->product" class="size-12 shrink-0 rounded-lg" />
                                <div class="min-w-0 flex-1 text-sm">
                                    <p class="truncate font-medium text-stone-800">{{ $line->product->name }}</p>
                                    <p class="text-xs text-stone-500">{{ $line->quantity }} × @rupiah($line->product->price)</p>
                                </div>
                                <p class="text-sm font-semibold">@rupiah($line->subtotal())</p>
                            </li>
                        @endforeach
                    </ul>
                    <dl class="mt-4 space-y-2 border-t border-stone-100 pt-4 text-sm">
                        <div class="flex justify-between"><dt class="text-stone-500">Subtotal</dt><dd>@rupiah($subtotal)</dd></div>
                        <div class="flex justify-between"><dt class="text-stone-500">Ongkir</dt><dd x-text="rupiah(shipping)">@rupiah($initialShipping)</dd></div>
                        <div class="flex justify-between border-t border-stone-100 pt-2 text-base font-bold text-stone-900"><dt>Total</dt><dd x-text="rupiah(subtotal + shipping)">@rupiah($subtotal + $initialShipping)</dd></div>
                    </dl>
                    <button class="btn btn-primary mt-5 w-full py-3" @disabled(! $address || empty($rates))>
                        <x-icon name="lock" class="size-4" /> Buat pesanan & bayar
                    </button>
                    <p class="mt-3 text-center text-xs text-stone-400">Pembayaran aman diproses oleh payment gateway.</p>
                </div>
            </div>
        </form>
    </div>
</x-layouts.shop>
