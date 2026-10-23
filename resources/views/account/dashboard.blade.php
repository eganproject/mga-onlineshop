<x-layouts.account title="Akun Saya">
    <div class="flex flex-col gap-4 bg-ink p-6 text-white sm:flex-row sm:items-center sm:justify-between sm:p-8">
        <div>
            <p class="eyebrow text-white/60">Selamat datang kembali</p>
            <p class="mt-2 font-display text-2xl font-extrabold tracking-tight uppercase sm:text-3xl">{{ auth()->user()->name }}</p>
        </div>
        <a href="{{ route('products.index') }}" class="btn btn-light btn-pill shrink-0">Lanjut belanja <x-icon name="arrow-right" class="size-4" /></a>
    </div>

    <div class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-4">
        @foreach ([['Semua pesanan', $stats['total'], 'receipt', null], ['Belum dibayar', $stats['pending'], 'clock', 'pending_payment'], ['Sedang berjalan', $stats['active'], 'truck', null], ['Selesai', $stats['completed'], 'check-circle', 'completed']] as [$label, $value, $icon, $status])
            <a href="{{ route('account.orders.index', array_filter(['status' => $status])) }}" class="card p-4 transition hover:border-ink">
                <x-icon :name="$icon" class="size-6 text-ink" />
                <p class="mt-3 text-2xl font-extrabold text-stone-900">{{ $value }}</p>
                <p class="text-xs text-stone-500">{{ $label }}</p>
            </a>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="card overflow-hidden xl:col-span-2">
            <div class="flex items-center justify-between p-5">
                <h2 class="font-bold text-stone-900">Pesanan terakhir</h2>
                <a href="{{ route('account.orders.index') }}" class="-my-2 py-3 text-xs font-semibold text-ink lg:my-0 lg:py-0">Lihat semua</a>
            </div>
            @forelse ($recentOrders as $order)
                <a href="{{ route('account.orders.show', $order) }}" class="flex items-center justify-between gap-3 border-t border-stone-100 px-5 py-4 hover:bg-stone-50/60">
                    <div>
                        <p class="text-sm font-semibold text-stone-900">{{ $order->code }}</p>
                        <p class="text-xs text-stone-500">{{ $order->created_at->translatedFormat('d M Y') }} · {{ $order->items_count }} produk</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-bold">@rupiah($order->total)</p>
                        <x-order-status :status="$order->status" class="mt-1" />
                    </div>
                </a>
            @empty
                <x-empty-state icon="bag" title="Belum ada pesanan" description="Pesanan pertamamu akan muncul di sini.">
                    <a href="{{ route('products.index') }}" class="btn btn-ink rounded-full">Mulai belanja</a>
                </x-empty-state>
            @endforelse
        </div>
        <div class="card p-5">
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-stone-900">Alamat utama</h2>
                <a href="{{ route('account.addresses.index') }}" class="-my-2 py-3 text-xs font-semibold text-ink lg:my-0 lg:py-0">Kelola</a>
            </div>
            @if ($defaultAddress)
                <div class="mt-4 text-sm">
                    <p class="font-semibold text-stone-800">{{ $defaultAddress->recipient_name }}</p>
                    <p class="text-stone-600">{{ $defaultAddress->phone }}</p>
                    <p class="mt-1 text-stone-500">{{ $defaultAddress->fullAddress() }}</p>
                </div>
            @else
                <p class="mt-4 text-sm text-stone-500">Belum ada alamat.</p>
                <a href="{{ route('account.addresses.create') }}" class="btn btn-secondary btn-sm mt-3">Tambah alamat</a>
            @endif
        </div>
    </div>
</x-layouts.account>
