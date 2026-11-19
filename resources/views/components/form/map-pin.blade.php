{{--
    Pin lokasi di peta (Leaflet + OpenStreetMap). Mengirim lintang & bujur lewat
    dua input tersembunyi. Di dalam x-data="regionPicker" peta ikut bergeser ke
    kecamatan yang dipilih.

    <x-form.map-pin label="Pin lokasi" :latitude="$address->latitude" :longitude="$address->longitude"
                    tooltip="..." hint="..." />
--}}
@props([
    'label' => 'Pin lokasi',
    'latName' => 'latitude',
    'lngName' => 'longitude',
    'latitude' => null,
    'longitude' => null,
    'hint' => null,
    'tooltip' => null,
    'tooltipTips' => null,
])
@php
    $lat = old($latName, $latitude);
    $lng = old($lngName, $longitude);
@endphp
<div {{ $attributes->class('min-w-0') }} x-data="mapPin({ lat: @js(is_numeric($lat) ? (float) $lat : null), lng: @js(is_numeric($lng) ? (float) $lng : null) })">
    <div class="label-row">
        <p class="label">{{ $label }} <span class="font-normal text-stone-400">(opsional)</span></p>
        @if ($tooltip)
            <x-tooltip :label="$label" :text="$tooltip" :tips="$tooltipTips" />
        @endif
    </div>
    <input type="hidden" name="{{ $latName }}" x-bind:value="lat ?? ''" value="{{ $lat }}">
    <input type="hidden" name="{{ $lngName }}" x-bind:value="lng ?? ''" value="{{ $lng }}">

    {{-- Mode "Perbesar peta": seluruh blok (cari, peta, tombol) menutupi layar; Esc/Selesai menutup. --}}
    <div x-bind:class="expanded && 'fixed inset-0 z-[65] flex flex-col bg-white p-3 sm:p-5'" x-on:keydown.escape.window="expanded && toggleExpanded(false)">
        {{-- Cari alamat: Enter tidak boleh mengirim form induknya. --}}
        <div class="mb-2 flex gap-2">
            <input type="search" x-model="query" x-on:keydown.enter.prevent="search()" class="input min-w-0 flex-1" maxlength="150"
                   placeholder="Ketik alamat atau nama tempat, mis. Jl. Tebet Raya 1 Jakarta" aria-label="Cari alamat di peta" autocomplete="off">
            <button type="button" class="btn btn-secondary shrink-0" x-on:click="search()" x-bind:disabled="searching || query.trim().length < 3">
                <x-icon name="search" class="size-4" />
                <span x-text="searching ? 'Mencari…' : 'Cari'">Cari</span>
            </button>
        </div>
        <ul x-show="results.length" x-cloak class="mb-2 divide-y divide-stone-100 overflow-hidden rounded-xl ring-1 ring-stone-200">
            <template x-for="(result, index) in results" x-bind:key="index">
                <li>
                    <button type="button" class="flex w-full items-start gap-2 px-3 py-2 text-left text-sm text-stone-700 hover:bg-brand-50" x-on:click="choose(result)">
                        <x-icon name="map-pin" class="mt-0.5 size-4 shrink-0 text-brand-600" />
                        <span class="min-w-0" x-text="result.label"></span>
                    </button>
                </li>
            </template>
        </ul>
        <p x-show="searched && !results.length" x-cloak class="mb-2 rounded-xl bg-stone-50 p-3 text-xs text-stone-600">Alamat tidak ditemukan. Coba nama jalan dan kota saja, lalu geser pin ke titik tepatnya.</p>

        <div x-ref="map" class="map-pin w-full overflow-hidden rounded-xl bg-stone-100 ring-1 ring-stone-200" x-bind:class="expanded ? 'min-h-0 flex-1' : 'h-80 sm:h-[28rem]'" role="application" aria-label="Peta untuk menaruh {{ mb_strtolower($label) }}"></div>

        <div class="mt-2 flex flex-wrap items-center gap-2">
            <button type="button" class="btn btn-secondary btn-sm" x-on:click="locate()" x-bind:disabled="locating">
                <x-icon name="map-pin" class="size-4" />
                <span x-text="locating ? 'Mencari lokasi…' : 'Pakai lokasi saya'">Pakai lokasi saya</span>
            </button>
            <button type="button" class="btn btn-sm" x-bind:class="expanded ? 'btn-primary' : 'btn-secondary'" x-on:click="toggleExpanded()" x-bind:aria-expanded="expanded">
                <x-icon name="search" class="size-4" x-show="!expanded" />
                <x-icon name="check" class="size-4" x-show="expanded" x-cloak />
                <span x-text="expanded ? 'Selesai' : 'Perbesar peta'">Perbesar peta</span>
            </button>
            <button type="button" class="btn btn-ghost btn-sm" x-show="hasPin" x-cloak x-on:click="clear()">Hapus pin</button>
            <span class="text-xs text-stone-500" x-text="hasPin ? `Pin: ${lat.toFixed(5)}, ${lng.toFixed(5)}` : 'Belum ada pin. Cari alamat atau ketuk peta untuk menaruh pin, geser untuk memindahkan.'"></span>
        </div>
    </div>
    <p x-show="notice" x-cloak x-text="notice" class="mt-2 rounded-xl bg-amber-50 p-3 text-xs text-amber-800"></p>
    @if ($hint)
        <p class="hint">{{ $hint }}</p>
    @endif
    <x-input-error :for="$latName" />
    <x-input-error :for="$lngName" />
</div>
