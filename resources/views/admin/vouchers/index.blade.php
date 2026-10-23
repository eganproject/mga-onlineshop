@php use App\Http\Controllers\Admin\VoucherController; use App\Models\Voucher; @endphp
<x-layouts.admin title="Voucher">
    <x-admin.page-header title="Voucher" description="Kode promo untuk potongan harga atau gratis ongkir saat checkout.">
        <a href="{{ route('admin.vouchers.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Buat voucher</a>
    </x-admin.page-header>

    <div class="card overflow-hidden">
        <form method="GET" class="flex flex-col gap-3 border-b border-stone-100 p-4 sm:flex-row sm:items-center">
            <div class="relative max-w-xs flex-1">
                <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-stone-400" />
                <input type="search" name="q" value="{{ $search }}" placeholder="Cari kode / nama..." class="input pl-10">
            </div>
            <div class="no-scrollbar -mx-1 flex gap-1.5 overflow-x-auto px-1">
                <a href="{{ route('admin.vouchers.index', array_filter(['q' => $search])) }}" @class(['shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold', 'bg-stone-900 text-white' => ! $state, 'bg-stone-100 text-stone-600 hover:bg-stone-200' => $state])>Semua</a>
                @foreach (VoucherController::STATES as $key => $label)
                    <a href="{{ route('admin.vouchers.index', array_filter(['q' => $search, 'status' => $key])) }}" @class(['shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold', 'bg-stone-900 text-white' => $state === $key, 'bg-stone-100 text-stone-600 hover:bg-stone-200' => $state !== $key])>{{ $label }}</a>
                @endforeach
            </div>
        </form>

        @if ($vouchers->isEmpty())
            <x-empty-state icon="ticket" :title="$search || $state ? 'Voucher tidak ditemukan' : 'Belum ada voucher'" description="Buat voucher pertama, misalnya diskon 10% untuk pembeli baru atau gratis ongkir.">
                <a href="{{ route('admin.vouchers.create') }}" class="btn btn-primary">Buat voucher</a>
            </x-empty-state>
        @else
            <div class="relative overflow-x-auto">
                <table class="table-base">
                    <thead class="bg-stone-50/70"><tr><th><x-admin.sort-link column="kode">Voucher</x-admin.sort-link></th><th>Manfaat</th><th><x-admin.sort-link column="pemakaian" desc>Pemakaian</x-admin.sort-link></th><th><x-admin.sort-link column="berakhir">Periode</x-admin.sort-link></th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($vouchers as $voucher)
                            @php [$stateLabel, $stateClass] = Voucher::stateLabel($voucher->state($voucher->redemptions_count)); @endphp
                            <tr class="hover:bg-stone-50/50">
                                <td>
                                    <div class="min-w-44">
                                        <p class="font-mono font-bold tracking-wide text-stone-900">{{ $voucher->code }}</p>
                                        <p class="text-xs text-stone-500">{{ $voucher->name }}@unless ($voucher->is_public) · <span title="Tidak ditampilkan di checkout; pelanggan harus tahu kodenya">Tersembunyi</span>@endunless</p>
                                    </div>
                                </td>
                                <td>
                                    <div class="min-w-40">
                                        <span class="badge {{ $voucher->type->badgeClasses() }}">{{ $voucher->type->label() }}</span>
                                        <p class="mt-1 text-sm font-semibold text-stone-800">{{ $voucher->benefitLabel() }}</p>
                                        <p class="text-xs text-stone-500">{{ $voucher->min_spend > 0 ? 'Min. belanja '.\App\Support\Money::rupiah($voucher->min_spend) : 'Tanpa minimum' }}</p>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">
                                    <p class="font-semibold tabular-nums text-stone-900">{{ number_format($voucher->redemptions_count, 0, ',', '.') }}{{ $voucher->quota ? ' / '.number_format($voucher->quota, 0, ',', '.') : '' }}</p>
                                    @if ($voucher->quota)
                                        <div class="mt-1 h-1.5 w-24 rounded-full bg-stone-100"><div class="h-1.5 rounded-full bg-brand-500" style="width: {{ min(100, round($voucher->redemptions_count / $voucher->quota * 100)) }}%"></div></div>
                                    @endif
                                    @if ($total = (int) $voucher->discount_sum + (int) $voucher->shipping_discount_sum)
                                        <p class="mt-1 text-xs text-stone-500">Diskon −@rupiah($total)</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-xs text-stone-600">
                                    {{ $voucher->starts_at?->translatedFormat('j M Y H:i') ?? 'Sekarang' }}<br>
                                    <span class="text-stone-400">s.d.</span> {{ $voucher->ends_at?->translatedFormat('j M Y H:i') ?? 'Tanpa batas' }}
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.vouchers.toggle', $voucher) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button title="Klik untuk {{ $voucher->is_active ? 'nonaktifkan' : 'aktifkan' }}"><span class="badge {{ $stateClass }}">{{ $stateLabel }}</span></button>
                                    </form>
                                </td>
                                <td>
                                    <div class="flex justify-end gap-1">
                                        <a href="{{ route('admin.vouchers.edit', $voucher) }}" class="btn btn-ghost btn-sm"><x-icon name="pencil" class="size-4" /> <span class="sr-only sm:not-sr-only">Edit</span></a>
                                        <x-confirm-delete :action="route('admin.vouchers.destroy', $voucher)" title="Hapus voucher {{ $voucher->code }}?" message="Voucher tidak bisa dipakai lagi. Pesanan yang sudah memakainya tidak berubah." />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-admin.pagination :paginator="$vouchers" label="voucher" />
        @endif
    </div>
</x-layouts.admin>
