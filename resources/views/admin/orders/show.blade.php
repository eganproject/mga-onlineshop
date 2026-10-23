<x-layouts.admin :title="'Pesanan '.$order->code">
    <x-admin.page-header :title="$order->code" :back="route('admin.orders.index')">
        <x-order-status :status="$order->status" class="px-3 py-1 text-sm" />
    </x-admin.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="card overflow-hidden">
                <div class="border-b border-stone-100 p-5"><h2 class="font-bold text-stone-900">Item pesanan</h2></div>
                <ul class="divide-y divide-stone-100">
                    @foreach ($order->items as $item)
                        <li class="flex items-center gap-4 p-5">
                            @if ($item->product)
                                <x-product-image :product="$item->product" class="size-14 shrink-0 rounded-xl" />
                            @else
                                <div class="grid size-14 shrink-0 place-items-center rounded-xl bg-stone-100 text-stone-400"><x-icon name="cube" /></div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-stone-900">{{ $item->product_name }}</p>
                                <p class="text-xs text-stone-500">{{ $item->sku ?? 'Tanpa SKU' }} · {{ $item->quantity }} × @rupiah($item->price)</p>
                            </div>
                            <p class="font-semibold text-stone-900">@rupiah($item->subtotal)</p>
                        </li>
                    @endforeach
                </ul>
                <dl class="space-y-2 border-t border-stone-100 bg-stone-50/50 p-5 text-sm">
                    <div class="flex justify-between"><dt class="text-stone-500">Subtotal</dt><dd>@rupiah($order->subtotal)</dd></div>
                    <div class="flex justify-between"><dt class="text-stone-500">Ongkir ({{ $order->courierLabel() }}, {{ number_format($order->total_weight / 1000, 2, ',', '.') }} kg)</dt><dd>@rupiah($order->shipping_cost)</dd></div>
                    <div class="flex justify-between border-t border-stone-200 pt-2 text-base font-bold text-stone-900"><dt>Total</dt><dd>@rupiah($order->total)</dd></div>
                </dl>
            </div>

            <div class="card overflow-hidden">
                <div class="border-b border-stone-100 p-5"><h2 class="font-bold text-stone-900">Riwayat pembayaran</h2></div>
                @forelse ($order->payments as $payment)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-100 p-5 last:border-0 text-sm">
                        <div>
                            <p class="font-semibold text-stone-800">{{ ucfirst($payment->gateway) }} @if($payment->payment_type)<span class="font-normal text-stone-500">· {{ str_replace('_', ' ', $payment->payment_type) }}</span>@endif</p>
                            <p class="text-xs text-stone-500">{{ $payment->reference ?? '—' }} · {{ $payment->created_at->translatedFormat('d M Y, H:i') }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold">@rupiah($payment->amount)</p>
                            <p class="text-xs text-stone-500">{{ $payment->status->label() }}</p>
                        </div>
                    </div>
                @empty
                    <p class="p-5 text-sm text-stone-500">Belum ada pembayaran.</p>
                @endforelse
            </div>
        </div>

        <div class="space-y-6">
            {{-- Update status --}}
            @if ($next = $order->status->nextStatuses())
                <div class="card p-5" x-data="{ status: '{{ $next[0]->value }}' }">
                    <h2 class="font-bold text-stone-900">Perbarui status</h2>
                    <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="mt-4 space-y-4">
                        @csrf
                        @method('PATCH')
                        <div class="grid gap-2">
                            @foreach ($next as $option)
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-stone-200 p-3 text-sm font-semibold transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                                    <input type="radio" name="status" value="{{ $option->value }}" x-model="status" class="text-brand-600 focus:ring-brand-500/30">
                                    {{ $option->label() }}
                                </label>
                            @endforeach
                        </div>
                        <div x-show="status === 'shipped'" x-cloak>
                            <label for="tracking_number" class="label">Nomor resi</label>
                            <input id="tracking_number" name="tracking_number" value="{{ old('tracking_number') }}" class="input @error('tracking_number') input-error @enderror">
                            <x-input-error for="tracking_number" />
                        </div>
                        <button class="btn btn-primary w-full" :class="status === 'cancelled' && '!bg-rose-600 hover:!bg-rose-700'"
                                onclick="return this.form.status.value !== 'cancelled' || confirm('Batalkan pesanan ini? Stok akan dikembalikan.')">
                            Simpan status
                        </button>
                    </form>
                </div>
            @endif

            <div class="card p-5">
                <h2 class="font-bold text-stone-900">Pelanggan</h2>
                <a href="{{ route('admin.customers.show', $order->user) }}" class="mt-3 flex items-center gap-3 rounded-xl p-2 -m-2 hover:bg-stone-50">
                    <span class="grid size-10 place-items-center rounded-full bg-brand-100 text-sm font-bold text-brand-700">{{ $order->user->initials() }}</span>
                    <span class="min-w-0"><span class="block truncate font-semibold text-stone-800">{{ $order->user->name }}</span><span class="block truncate text-xs text-stone-500">{{ $order->user->email }}</span></span>
                </a>
            </div>

            <div class="card p-5 text-sm">
                <h2 class="font-bold text-stone-900">Pengiriman</h2>
                <dl class="mt-3 space-y-3">
                    <div><dt class="text-xs text-stone-500">Penerima</dt><dd class="font-semibold text-stone-800">{{ $order->recipient_name }} · {{ $order->recipient_phone }}</dd></div>
                    <div><dt class="text-xs text-stone-500">Alamat</dt><dd class="text-stone-700">{{ $order->shipping_address }}</dd></div>
                    <div><dt class="text-xs text-stone-500">Kurir</dt><dd class="text-stone-700">{{ $order->courierLabel() ?? '—' }} @if($order->shipping_etd)<span class="text-stone-500">({{ $order->shipping_etd }})</span>@endif</dd></div>
                    @if ($order->tracking_number)
                        <div><dt class="text-xs text-stone-500">No. resi</dt><dd class="font-mono font-semibold text-stone-800">{{ $order->tracking_number }}</dd></div>
                    @endif
                    @if ($order->notes)
                        <div><dt class="text-xs text-stone-500">Catatan pembeli</dt><dd class="rounded-xl bg-amber-50 p-3 text-amber-800">{{ $order->notes }}</dd></div>
                    @endif
                </dl>
            </div>

            <div class="card p-5 text-sm">
                <h2 class="font-bold text-stone-900">Linimasa</h2>
                <ol class="mt-3 space-y-3">
                    @foreach ([['Dibuat', $order->created_at], ['Dibayar', $order->paid_at], ['Dikirim', $order->shipped_at], ['Selesai', $order->completed_at], ['Dibatalkan', $order->cancelled_at]] as [$label, $at])
                        @if ($at)
                            <li class="flex items-center gap-3"><span class="size-2 rounded-full bg-brand-500"></span><span class="flex-1 text-stone-700">{{ $label }}</span><span class="text-xs text-stone-500">{{ $at->translatedFormat('d M Y, H:i') }}</span></li>
                        @endif
                    @endforeach
                </ol>
            </div>
        </div>
    </div>
</x-layouts.admin>
