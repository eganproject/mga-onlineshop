<x-layouts.admin :title="'Pesanan '.$order->code">
    <x-admin.page-header :title="$order->code" :description="'Dipesan '.$order->created_at->translatedFormat('d F Y, H:i')" :back="route('admin.orders.index')">
        <x-order-status :status="$order->status" class="px-3 py-1 text-sm" />
        @if ($order->isManual())
            <span class="badge bg-violet-50 px-3 py-1 text-sm text-violet-700 ring-violet-600/20">Manual</span>
        @endif
        <a href="{{ route('admin.orders.invoice', $order) }}" target="_blank" class="btn btn-secondary btn-sm"><x-icon name="printer" class="size-4" /> {{ $order->paid_at ? 'Invoice' : 'Tagihan' }}</a>
        <a href="{{ route('admin.orders.invoice.pdf', $order) }}" class="btn btn-secondary btn-sm"><x-icon name="download" class="size-4" /> PDF</a>
        <a href="{{ route('admin.orders.packing-slip', $order) }}" target="_blank" class="btn btn-secondary btn-sm"><x-icon name="printer" class="size-4" /> Packing slip</a>
    </x-admin.page-header>

    @if ($order->status === \App\Enums\OrderStatus::Cancelled && $order->paid_at)
        <div class="mb-6 flex gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            <x-icon name="warning" class="size-5 shrink-0" /> Pesanan ini sudah dibayar sebelum dibatalkan. Pastikan dana dikembalikan ke pelanggan.
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            @if ($billing)
                @include('admin.orders.partials.billing')
            @endif
            @if ($order->status === \App\Enums\OrderStatus::PendingPayment)
                @can('payments.record')
                    @include('admin.orders.partials.offline-payment')
                @endcan
            @endif

            <div class="card overflow-hidden">
                <div class="border-b border-stone-100 p-5"><h2 class="font-bold text-stone-900">Item pesanan</h2></div>
                <ul class="divide-y divide-stone-100">
                    @foreach ($order->items as $item)
                        <li class="flex items-start gap-3 p-4 sm:items-center sm:gap-4 sm:p-5">
                            @if ($item->product)
                                <x-product-image :product="$item->product" class="size-14 shrink-0 rounded-xl" />
                            @else
                                <div class="grid size-14 shrink-0 place-items-center rounded-xl bg-stone-100 text-stone-400"><x-icon name="cube" /></div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-stone-900">{{ $item->product_name }}</p>
                                @if ($item->variant_name)<p class="text-xs font-medium text-brand-700">{{ $item->variant_name }}</p>@endif
                                <p class="text-xs text-stone-500">{{ $item->sku ?? 'Tanpa SKU' }} · {{ $item->quantity }} × {{ \App\Support\Money::rupiah($item->price) }}
                                    @if ($item->isWholesale())
                                        <span class="badge bg-accent-50 text-accent-700">Grosir</span> <span class="text-stone-400 line-through">{{ \App\Support\Money::rupiah($item->regular_price) }}</span>
                                    @endif
                                    @if ($item->hasCustomPrice())
                                        <span class="badge bg-brand-50 text-brand-700" title="Harga diubah staf saat penjualan manual">Harga diubah</span> <span class="text-stone-400">web {{ \App\Support\Money::rupiah($item->list_price) }}</span>
                                    @endif
                                </p>
                                <p class="mt-1 font-semibold text-stone-900 sm:hidden">@rupiah($item->subtotal)</p>
                            </div>
                            <p class="hidden shrink-0 font-semibold whitespace-nowrap text-stone-900 sm:block">@rupiah($item->subtotal)</p>
                        </li>
                    @endforeach
                </ul>
                <dl class="space-y-2 border-t border-stone-100 bg-stone-50/50 p-5 text-sm">
                    <x-order-totals :order="$order" />
                </dl>
            </div>

            <div class="card overflow-hidden">
                <div class="border-b border-stone-100 p-5"><h2 class="font-bold text-stone-900">Riwayat pembayaran</h2></div>
                @if ($order->isOfflinePaid())
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-stone-100 bg-emerald-50/40 p-5 text-sm">
                        <div class="min-w-0">
                            <p class="font-semibold text-stone-800">{{ $order->paymentMethodLabel() }} <span class="font-normal text-stone-500">· dicatat staf</span></p>
                            <p class="text-xs text-stone-500">
                                {{ $order->paid_at->timezone(config('app.timezone'))->translatedFormat('d M Y, H:i') }}
                                @if ($order->payment_reference) · ref. {{ $order->payment_reference }} @endif
                                @if ($order->paymentRecorder) · oleh {{ $order->paymentRecorder->name }} @endif
                            </p>
                            @if ($order->payment_proof)
                                <a href="{{ route('admin.orders.payment-proof', $order) }}" target="_blank" rel="noopener" class="link-underline mt-1 inline-flex items-center gap-1 text-xs font-semibold text-brand-700"><x-icon name="photo" class="size-3.5" /> Lihat bukti bayar</a>
                            @endif
                        </div>
                        <div class="text-right">
                            <p class="font-semibold">@rupiah($order->total)</p>
                            <p class="text-xs text-emerald-700">Lunas</p>
                        </div>
                    </div>
                @endif
                @forelse ($order->payments as $payment)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-100 p-5 last:border-0 text-sm">
                        <div>
                            <p class="font-semibold text-stone-800">{{ ucfirst($payment->gateway) }} @if($payment->methodLabel())<span class="font-normal text-stone-500">· {{ $payment->methodLabel() }}</span>@endif</p>
                            <p class="text-xs text-stone-500">{{ $payment->reference ?? '—' }} · {{ $payment->created_at->translatedFormat('d M Y, H:i') }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold">@rupiah($payment->amount)</p>
                            <p class="text-xs text-stone-500">{{ $payment->status->label() }}@if ($payment->fee) · biaya pembeli @rupiah($payment->fee)@endif</p>
                        </div>
                    </div>
                @empty
                    @unless ($order->isOfflinePaid())
                        <p class="p-5 text-sm text-stone-500">Belum ada pembayaran.</p>
                    @endunless
                @endforelse
            </div>

            @if ($canTrack)
                <x-order-tracking-panel :order="$order" :url="route('admin.orders.tracking', $order)" :refresh-url="route('admin.orders.tracking', [$order, 'fresh' => 1])" />
            @endif
        </div>

        <div class="space-y-6">
            @include('admin.orders.partials.finance')

            @if ($order->warehouse_outbound_status || config('services.warehouse.outbound_enabled'))
                @include('admin.orders.partials.warehouse')
            @endif

            {{-- Update status --}}
            @php($next = collect($order->status->nextStatuses())->filter(fn ($status) => auth()->user()->can($status === \App\Enums\OrderStatus::Cancelled ? 'orders.cancel' : 'orders.manage'))->values()->all())
            @if ($next)
                <div class="card p-5" x-data="{ status: @js(old('status', $next[0]->value)) }">
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
                            <div class="label-row">
                                <label for="tracking_number" class="label">Nomor resi {{ $order->courierLabel() }}</label>
                                <x-tooltip label="Nomor resi" text="Nomor dari kurir saat paket diserahkan. Pelanggan bisa melacak paket dari halaman pesanannya, dan resi dicek otomatis bila fitur cek resi aktif." />
                            </div>
                            <input id="tracking_number" name="tracking_number" value="{{ old('tracking_number', $order->tracking_number) }}" class="input font-mono @error('tracking_number') input-error @enderror" autocomplete="off">
                            <x-input-error for="tracking_number" />
                        </div>
                        <div>
                            <label for="note" class="label" x-text="status === 'cancelled' ? 'Alasan pembatalan' : 'Catatan (opsional)'">Catatan (opsional)</label>
                            <x-form.textarea name="note" rows="2" maxlength="250"
                                             x-bind:required="status === 'cancelled'"
                                             x-bind:placeholder="status === 'cancelled' ? 'Mis. stok habis, permintaan pelanggan' : 'Tampil di riwayat pesanan pelanggan'" />
                        </div>
                        <button class="btn btn-primary w-full" :class="status === 'cancelled' && '!bg-rose-600 hover:!bg-rose-700'"
                                data-confirm-tone="danger" data-confirm-title="Batalkan pesanan {{ $order->code }}?" data-confirm-ok="Ya, batalkan pesanan"
                                :data-confirm="status === 'cancelled' ? @js($order->paid_at ? 'Stok dikembalikan otomatis. Pesanan ini sudah dibayar, jadi dana pelanggan perlu dikembalikan manual.' : 'Stok dikembalikan otomatis dan pelanggan tidak bisa membayar pesanan ini lagi.') : null">
                            Simpan status
                        </button>
                    </form>
                </div>
            @endif

            @if ($order->status->isTrackable())
                <div class="card p-5" x-data="{ edit: false }">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="font-bold text-stone-900">Nomor resi</h2>
                            <p class="mt-1 font-mono text-sm font-semibold text-stone-800">{{ $order->tracking_number ?? '—' }}</p>
                        </div>
                        @can('orders.manage')
                            <button type="button" class="btn btn-ghost btn-sm" @click="edit = !edit" x-text="edit ? 'Batal' : 'Ubah'">Ubah</button>
                        @endcan
                    </div>
                    <form x-show="edit" x-cloak method="POST" action="{{ route('admin.orders.tracking-number', $order) }}" class="mt-4 space-y-3">
                        @csrf
                        @method('PATCH')
                        <input name="tracking_number" value="{{ $order->tracking_number }}" required maxlength="64" class="input font-mono" aria-label="Nomor resi baru">
                        <button class="btn btn-primary w-full">Simpan resi</button>
                    </form>
                </div>
            @endif

            <div class="card p-5">
                <h2 class="font-bold text-stone-900">Pelanggan</h2>
                @if ($order->user)
                    @php($customerTag = auth()->user()->can('customers.view') ? 'a' : 'div')
                    <{{ $customerTag }} @if ($customerTag === 'a') href="{{ route('admin.customers.show', $order->user) }}" @endif
                        @class(['mt-3 flex items-center gap-3 rounded-xl p-2 -m-2', 'hover:bg-stone-50' => $customerTag === 'a'])>
                        <span class="grid size-10 place-items-center rounded-full bg-brand-100 text-sm font-bold text-brand-700">{{ $order->user->initials() }}</span>
                        <span class="min-w-0"><span class="block truncate font-semibold text-stone-800">{{ $order->user->name }}</span><span class="block truncate text-xs text-stone-500">{{ $order->user->email }}</span></span>
                    </{{ $customerTag }}>
                @else
                    <div class="mt-3 flex items-center gap-3">
                        <span class="grid size-10 place-items-center rounded-full bg-stone-100 text-stone-500"><x-icon name="user" class="size-5" /></span>
                        <span class="min-w-0"><span class="block truncate font-semibold text-stone-800">{{ $order->recipient_name }}</span><span class="block truncate text-xs text-stone-500">{{ $order->billing_email ?: 'Tanpa akun' }} · {{ $order->recipient_phone }}</span></span>
                    </div>
                @endif
                @if ($order->isManual())
                    <p class="mt-3 border-t border-stone-100 pt-3 text-xs text-stone-500">Penjualan manual oleh {{ $order->creator?->name ?? 'staf' }}</p>
                @endif
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
                    @if ($order->delivered_at)
                        <div><dt class="text-xs text-stone-500">Sampai tujuan</dt><dd class="text-stone-700">{{ $order->delivered_at->translatedFormat('d M Y, H:i') }}</dd></div>
                    @endif
                    @if ($order->cancellation_reason)
                        <div><dt class="text-xs text-stone-500">Alasan batal</dt><dd class="rounded-xl bg-rose-50 p-3 text-rose-800">{{ $order->cancellation_reason }}</dd></div>
                    @endif
                    @if ($order->notes)
                        <div><dt class="text-xs text-stone-500">{{ $order->isManual() ? 'Catatan' : 'Catatan pembeli' }}</dt><dd class="rounded-xl bg-amber-50 p-3 text-amber-800">{{ $order->notes }}</dd></div>
                    @endif
                </dl>
            </div>

            <div class="card p-5">
                <h2 class="mb-4 font-bold text-stone-900">Riwayat status</h2>
                <x-order-timeline :order="$order" admin />
            </div>
        </div>
    </div>
</x-layouts.admin>
