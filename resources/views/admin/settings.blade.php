<x-layouts.admin title="Integrasi">
    <x-admin.page-header title="Integrasi" description="Status koneksi payment gateway, ongkos kirim, dan login Google. Kunci API diatur lewat file .env di server; kecamatan asal & kurir di Pengaturan Toko." />

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ($integrations as $integration)
            <div class="card min-w-0 p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-stone-900">{{ $integration['name'] }}</h2>
                        <p class="mt-1 text-sm text-stone-500">{{ $integration['description'] }}</p>
                    </div>
                    @if ($integration['active'] && $integration['configured'])
                        <span class="badge shrink-0 bg-emerald-50 text-emerald-700 ring-emerald-600/20">Aktif</span>
                    @elseif ($integration['active'])
                        <span class="badge shrink-0 bg-rose-50 text-rose-700 ring-rose-600/20">Kunci belum lengkap</span>
                    @else
                        <span class="badge shrink-0 bg-amber-50 text-amber-700 ring-amber-600/20">{{ $integration['inactive_label'] ?? 'Mode simulasi' }}</span>
                    @endif
                </div>

                <dl class="mt-5 grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-xl bg-stone-50 p-3"><dt class="text-xs text-stone-500">Driver</dt><dd class="font-mono font-semibold text-stone-800">{{ $integration['driver'] }}</dd></div>
                    <div class="rounded-xl bg-stone-50 p-3"><dt class="text-xs text-stone-500">{{ $integration['mode_label'] ?? ($integration['name'] === 'Midtrans' ? 'Mode' : 'Kurir') }}</dt><dd class="font-semibold text-stone-800">{{ $integration['mode'] }}</dd></div>
                </dl>

                <div class="mt-5">
                    <p class="text-xs font-semibold text-stone-500 uppercase">Variabel .env</p>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @foreach ($integration['env'] as $env)
                            <code class="rounded-lg bg-stone-900 px-2 py-1 text-xs text-stone-100">{{ $env }}</code>
                        @endforeach
                    </div>
                </div>

                @if ($integration['webhook'])
                    <div class="mt-5">
                        <p class="text-xs font-semibold text-stone-500 uppercase">URL notifikasi (webhook)</p>
                        <div class="mt-2 flex items-center gap-2" x-data="{ copied: false }">
                            <code class="flex-1 truncate rounded-xl bg-stone-50 px-3 py-2 text-xs text-stone-700 ring-1 ring-stone-200">{{ $integration['webhook'] }}</code>
                            <button type="button" class="btn btn-secondary btn-sm" @click="navigator.clipboard.writeText(@js($integration['webhook'])); copied = true; setTimeout(() => copied = false, 1500)">
                                <span x-text="copied ? 'Tersalin' : 'Salin'">Salin</span>
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-6 flex gap-3 rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800">
        <x-icon name="info" class="size-5 shrink-0" />
        <p>Saat driver masih <code class="font-mono">fake</code>, checkout tetap bisa dicoba: ongkir dihitung dari tarif contoh dan pembayaran bisa disimulasikan dari halaman pesanan pelanggan (hanya di luar mode production).</p>
    </div>
</x-layouts.admin>
