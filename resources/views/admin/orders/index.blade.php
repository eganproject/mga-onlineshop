<x-layouts.admin title="Pesanan">
    <x-admin.page-header title="Pesanan" description="Pantau dan proses pesanan pelanggan.">
        @can('orders.create')
            <a href="{{ route('admin.orders.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Penjualan manual</a>
        @endcan
    </x-admin.page-header>

    {{-- Tab status --}}
    <div class="-mx-4 mb-4 overflow-x-auto px-4 sm:mx-0 sm:px-0 lg:overflow-visible">
        <div class="inline-flex gap-0.5 rounded-xl bg-white p-1 ring-1 ring-stone-200 lg:flex-wrap">
            <a href="{{ route('admin.orders.index', request()->except('status', 'page')) }}"
               @class(['whitespace-nowrap rounded-lg px-3 py-1.5 text-sm font-medium transition', 'bg-stone-100 font-semibold text-stone-900' => ! $status, 'text-stone-500 hover:bg-stone-50 hover:text-stone-800' => $status])>
                Semua <span class="ml-1 text-stone-400 tabular-nums">{{ $counts->sum() }}</span>
            </a>
            @foreach (\App\Enums\OrderStatus::cases() as $case)
                <a href="{{ route('admin.orders.index', ['status' => $case->value] + request()->except('status', 'page')) }}"
                   @class(['whitespace-nowrap rounded-lg px-3 py-1.5 text-sm font-medium transition', 'bg-stone-100 font-semibold text-stone-900' => $status === $case, 'text-stone-500 hover:bg-stone-50 hover:text-stone-800' => $status !== $case])>
                    {{ $case->label() }} <span @class(['ml-1 tabular-nums', 'font-bold text-sky-600' => $case === \App\Enums\OrderStatus::Paid && ($counts[$case->value] ?? 0) > 0, 'text-stone-400' => ! ($case === \App\Enums\OrderStatus::Paid && ($counts[$case->value] ?? 0) > 0)])>{{ $counts[$case->value] ?? 0 }}</span>
                </a>
            @endforeach
        </div>
    </div>

    <div class="card overflow-hidden">
        <form method="GET" class="flex flex-col gap-3 border-b border-stone-100 p-4">
            <x-admin.keep-query />
            @if ($status)<input type="hidden" name="status" value="{{ $status->value }}">@endif
            <div class="flex gap-2">
                <div class="relative min-w-0 flex-1 lg:max-w-md">
                    <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-stone-400" />
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Kode, nama, HP, email, SKU, atau resi..." class="input pl-10" aria-label="Cari pesanan">
                </div>
                <button class="btn btn-dark shrink-0"><x-icon name="filter" class="size-4" /> Saring</button>
                @if (request()->hasAny(['q', 'from', 'to', 'courier', 'source', 'bayar', 'gudang']))
                    <a href="{{ route('admin.orders.index', array_filter(['status' => $status?->value])) }}" class="btn btn-ghost shrink-0" title="Hapus filter"><x-icon name="x" class="size-4" /><span class="sr-only">Hapus filter</span></a>
                @endif
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5">
                <x-form.date-range from="from" to="to" :from-value="request('from')" :to-value="request('to')" :max="today()"
                                   placeholder="Semua tanggal" aria-label="Rentang tanggal" />
                <select name="source" class="input" aria-label="Sumber pesanan">
                    <option value="">Semua sumber</option>
                    <option value="website" @selected(request('source') === 'website')>Website</option>
                    <option value="manual" @selected(request('source') === 'manual')>Manual</option>
                </select>
                <select name="courier" class="input" x-data x-searchable aria-label="Kurir">
                    <option value="">Semua kurir</option>
                    @foreach ($couriers as $courier)
                        <option value="{{ $courier }}" @selected(request('courier') === $courier)>{{ strtoupper($courier) }}</option>
                    @endforeach
                </select>
                <select name="bayar" class="input" x-data x-searchable aria-label="Cara bayar">
                    <option value="">Semua cara bayar</option>
                    @foreach ($paymentOptions as $value => $label)
                        <option value="{{ $value }}" @selected(request('bayar') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="gudang" class="input" aria-label="Status kirim ke gudang">
                    <option value="">Semua status gudang</option>
                    @foreach ($warehouseOptions as $value => $label)
                        <option value="{{ $value }}" @selected(request('gudang') === $value)>Gudang: {{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </form>

        @if ($orders->isEmpty())
            <x-empty-state icon="receipt" title="Tidak ada pesanan" description="Belum ada pesanan yang cocok dengan filter ini." />
        @else
            <form method="POST" action="{{ route('admin.orders.bulk') }}" x-data="{ selected: [] }">
                @csrf
                <div x-show="selected.length" x-cloak class="flex flex-wrap items-center gap-2 border-b border-stone-100 bg-brand-50/60 px-4 py-3 text-sm">
                    <span class="font-semibold text-stone-800"><span x-text="selected.length"></span> dipilih</span>
                    @can('orders.manage')
                        <button name="action" value="process" class="btn btn-secondary btn-sm"><x-icon name="cube" class="size-4" /> Tandai diproses</button>
                    @endcan
                    <button name="action" value="packing-slips" formtarget="_blank" class="btn btn-secondary btn-sm"><x-icon name="printer" class="size-4" /> Cetak packing slip</button>
                </div>
            <div class="relative overflow-x-auto">
                <table class="table-base">
                    <thead class="bg-stone-50/70"><tr>
                        <th class="w-10"><input type="checkbox" class="checkbox" aria-label="Pilih semua"
                            :checked="selected.length && selected.length === $root.querySelectorAll('input[name=\'orders[]\']').length"
                            @change="selected = $event.target.checked ? [...$root.querySelectorAll('input[name=\'orders[]\']')].map(i => i.value) : []"></th>
                        <th><x-admin.sort-link column="tanggal" desc>Pesanan</x-admin.sort-link></th><th class="hidden md:table-cell">Pelanggan</th><th class="hidden 2xl:table-cell">Item</th><th class="hidden sm:table-cell"><x-admin.sort-link column="total" desc>Total</x-admin.sort-link></th><th class="hidden lg:table-cell">Pengiriman</th><th class="hidden sm:table-cell">Status</th><th class="hidden sm:table-cell"></th>
                    </tr></thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($orders as $order)
                            <tr class="hover:bg-stone-50/50">
                                <td><input type="checkbox" name="orders[]" value="{{ $order->id }}" x-model="selected" class="checkbox" aria-label="Pilih {{ $order->code }}"></td>
                                <td class="whitespace-nowrap">
                                    <a href="{{ route('admin.orders.show', $order) }}" class="font-semibold text-stone-900 hover:text-brand-700">{{ $order->code }}</a>
                                    <p class="text-xs text-stone-500">{{ $order->created_at->translatedFormat('d M Y, H:i') }}@if ($order->isManual()) <span class="badge bg-violet-50 text-violet-700 ring-violet-600/20">Manual</span>@endif</p>
                                    <p class="mt-1 text-xs text-stone-600 md:hidden">{{ $order->customerName() }}</p>
                                    <p class="mt-0.5 text-sm font-semibold text-stone-900 sm:hidden">@rupiah($order->total)</p>
                                    <div class="mt-1 sm:hidden"><x-order-status :status="$order->status" /></div>
                                    <div class="mt-1.5 2xl:hidden">@include('admin.orders.partials.item-skus')</div>
                                </td>
                                <td class="hidden md:table-cell"><p class="line-clamp-2 font-medium text-stone-800">{{ $order->customerName() }}</p><p class="text-xs text-stone-500">{{ $order->customerEmail() ?? $order->recipient_phone }}</p></td>
                                <td class="hidden 2xl:table-cell">@include('admin.orders.partials.item-skus')</td>
                                <td class="hidden whitespace-nowrap font-semibold sm:table-cell">@rupiah($order->total)</td>
                                <td class="hidden whitespace-nowrap text-xs lg:table-cell">{{ $order->courierLabel() ?? '—' }}@if ($order->tracking_number)<p class="font-mono text-stone-500">{{ $order->tracking_number }}</p>@endif</td>
                                <td class="hidden sm:table-cell"><x-order-status :status="$order->status" /></td>
                                <td class="hidden text-right sm:table-cell"><a href="{{ route('admin.orders.show', $order) }}" class="btn btn-secondary btn-sm">Detail</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            </form>
            <x-admin.pagination :paginator="$orders" label="pesanan" />
        @endif
    </div>
</x-layouts.admin>
