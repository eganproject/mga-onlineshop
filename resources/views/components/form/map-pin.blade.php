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

    <div x-ref="map" class="map-pin h-64 w-full overflow-hidden rounded-xl bg-stone-100 ring-1 ring-stone-200 sm:h-72" role="application" aria-label="Peta untuk menaruh {{ mb_strtolower($label) }}"></div>

    <div class="mt-2 flex flex-wrap items-center gap-2">
        <button type="button" class="btn btn-secondary btn-sm" x-on:click="locate()" x-bind:disabled="locating">
            <x-icon name="map-pin" class="size-4" />
            <span x-text="locating ? 'Mencari lokasi…' : 'Pakai lokasi saya'">Pakai lokasi saya</span>
        </button>
        <button type="button" class="btn btn-ghost btn-sm" x-show="hasPin" x-cloak x-on:click="clear()">Hapus pin</button>
        <span class="text-xs text-stone-500" x-text="hasPin ? `Pin: ${lat.toFixed(5)}, ${lng.toFixed(5)}` : 'Belum ada pin. Ketuk peta untuk menaruh pin, geser untuk memindahkan.'"></span>
    </div>
    <p x-show="notice" x-cloak x-text="notice" class="mt-2 rounded-xl bg-amber-50 p-3 text-xs text-amber-800"></p>
    @if ($hint)
        <p class="hint">{{ $hint }}</p>
    @endif
    <x-input-error :for="$latName" />
    <x-input-error :for="$lngName" />
</div>
