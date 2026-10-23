@props(['order', 'url', 'refreshUrl' => null])
{{-- Panel lacak resi: hasilnya dimuat belakangan agar halaman tetap cepat walau API kurir lambat. --}}
<div {{ $attributes->merge(['class' => 'card p-5']) }}
     x-data="{
        html: '', loading: true,
        async load(url) {
            this.loading = true;
            try {
                const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                this.html = res.ok ? await res.text() : '<p class=\'text-sm text-rose-600\'>Gagal memuat info pelacakan. Coba lagi nanti.</p>';
            } catch (e) {
                this.html = '<p class=\'text-sm text-rose-600\'>Gagal memuat info pelacakan. Periksa koneksi internet.</p>';
            }
            this.loading = false;
        },
     }"
     x-init="load(@js($url))">
    <div class="mb-4 flex items-start justify-between gap-3">
        <div>
            <h2 class="font-bold text-stone-900">Lacak paket</h2>
            <p class="mt-0.5 text-xs text-stone-500">{{ $order->courierLabel() }} · <span class="font-mono font-semibold text-stone-700">{{ $order->tracking_number }}</span></p>
        </div>
        <div class="flex items-center gap-1">
            <button type="button" class="btn btn-ghost btn-sm" x-data="{ copied: false }"
                    @click="navigator.clipboard?.writeText(@js($order->tracking_number)); copied = true; setTimeout(() => copied = false, 1500)">
                <span x-text="copied ? 'Tersalin' : 'Salin resi'">Salin resi</span>
            </button>
            @if ($refreshUrl)
                <button type="button" class="btn btn-ghost btn-sm" @click="load(@js($refreshUrl))" :disabled="loading" title="Cek ulang ke kurir">
                    <x-icon name="refresh" class="size-4" x-bind:class="loading && 'animate-spin'" />
                </button>
            @endif
        </div>
    </div>
    <div x-show="loading" class="space-y-3" aria-live="polite">
        <div class="h-4 w-1/2 animate-pulse rounded bg-stone-100"></div>
        <div class="h-4 w-3/4 animate-pulse rounded bg-stone-100"></div>
        <div class="h-4 w-2/3 animate-pulse rounded bg-stone-100"></div>
    </div>
    <div x-show="!loading" x-cloak x-html="html"></div>
</div>
