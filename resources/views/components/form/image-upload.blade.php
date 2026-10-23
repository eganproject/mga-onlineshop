{{--
    Unggah satu gambar dengan pratinjau, seret-lepas, dan cek ukuran file.
    <x-form.image-upload name="image" label="Gambar" :current="$banner->imageUrl()"
        remove-name="remove_image" aspect="aspect-[21/9]" :max-kb="4096" />
    remove-name: nama checkbox "hapus gambar" (opsional, untuk gambar yang boleh kosong).
--}}
@use('App\Support\FormField')
@props([
    'name',
    'id' => null,
    'label' => null,
    'hint' => null,
    'optional' => false,
    'current' => null,
    'removeName' => null,
    'aspect' => 'aspect-video',
    'accept' => 'image/jpeg,image/png,image/webp',
    'maxKb' => 2048,
    'bag' => 'default',
    'wrapperClass' => null,
])
@php
    $id ??= FormField::id($name);
    $invalid = FormField::hasError($name, $bag);
    $maxLabel = $maxKb >= 1024 ? rtrim(rtrim(number_format($maxKb / 1024, 1, ',', ''), '0'), ',').' MB' : $maxKb.' KB';
@endphp
<x-form.field :label="$label" :for="$id" :name="$name" :hint="$hint" :optional="$optional" :bag="$bag" :class="$wrapperClass">
    <div x-data="imageUpload({ current: @js($current), maxKb: @js((int) $maxKb), maxLabel: @js($maxLabel) })">
        <label for="{{ $id }}"
               @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="drop($event)"
               @class([
                   'group relative flex w-full cursor-pointer flex-col items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed bg-stone-50 text-center transition hover:border-brand-300 hover:bg-brand-50/40',
                   $aspect,
                   'border-rose-300' => $invalid,
                   'border-stone-200' => ! $invalid,
               ])
               :class="dragging && '!border-brand-400 !bg-brand-50'">
            <img x-show="preview" x-cloak :src="preview" alt="" class="absolute inset-0 size-full object-cover" :class="removed && 'opacity-30 grayscale'">
            <span x-show="!preview" class="flex flex-col items-center gap-1.5 p-4 text-stone-400 group-hover:text-brand-600">
                <x-icon name="upload" class="size-7" />
                <span class="text-sm font-semibold">Klik atau seret gambar ke sini</span>
                <span class="text-xs">JPG, PNG, atau WebP · maks. {{ $maxLabel }}</span>
            </span>
            <span x-show="preview" x-cloak class="absolute inset-x-0 bottom-0 flex items-center justify-between gap-2 bg-gradient-to-t from-stone-900/75 px-3 pt-6 pb-2 text-left text-xs text-white">
                <span class="truncate" x-text="fileName ? `${fileName} · ${fileSize}` : 'Gambar saat ini'"></span>
                <span class="shrink-0 rounded-lg bg-white/90 px-2 py-1 font-semibold text-stone-800">Ganti</span>
            </span>
            <input type="file" id="{{ $id }}" name="{{ $name }}" accept="{{ $accept }}" x-ref="file" class="sr-only" @change="pick($event.target.files[0])"
                   {{ $attributes }}>
        </label>
        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
            <button type="button" x-show="fileName" x-cloak @click="reset()" class="font-semibold text-rose-600 hover:underline">Batalkan gambar baru</button>
            @if ($current && $removeName)
                <label x-show="!fileName" class="flex items-center gap-2 text-stone-600">
                    <input type="checkbox" name="{{ $removeName }}" value="1" x-model="removed" class="checkbox"> Hapus gambar
                </label>
            @endif
        </div>
        <p x-show="error" x-cloak x-text="error" class="field-error"></p>
    </div>
</x-form.field>
