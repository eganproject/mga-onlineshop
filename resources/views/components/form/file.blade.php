{{--
    Unggah satu file (mis. Excel/CSV) dengan seret-lepas, nama & ukuran file, serta cek format.
    <x-form.file name="file" label="File" :extensions="['xlsx', 'csv']" :max-kb="10240" required />
--}}
@use('App\Support\FormField')
@props([
    'name',
    'id' => null,
    'label' => null,
    'hint' => null,
    'optional' => false,
    'extensions' => [],
    'maxKb' => 10240,
    'bag' => 'default',
    'wrapperClass' => null,
])
@php
    $id ??= FormField::id($name);
    $invalid = FormField::hasError($name, $bag);
    $maxLabel = $maxKb >= 1024 ? rtrim(rtrim(number_format($maxKb / 1024, 1, ',', ''), '0'), ',').' MB' : $maxKb.' KB';
    $formats = collect($extensions)->map(fn ($e) => strtoupper($e))->implode(', ');
@endphp
<x-form.field :label="$label" :for="$id" :name="$name" :hint="$hint" :optional="$optional" :bag="$bag" :class="$wrapperClass">
    <div x-data="fileUpload({ extensions: @js(array_values($extensions)), maxKb: @js((int) $maxKb), maxLabel: @js($maxLabel) })">
        <label for="{{ $id }}"
               @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="drop($event)"
               @class([
                   'group flex w-full cursor-pointer items-center gap-3 rounded-2xl border-2 border-dashed bg-stone-50 px-4 py-4 transition hover:border-brand-300 hover:bg-brand-50/40',
                   'border-rose-300' => $invalid,
                   'border-stone-200' => ! $invalid,
               ])
               :class="dragging && '!border-brand-400 !bg-brand-50'">
            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-white text-stone-400 ring-1 ring-stone-200 group-hover:text-brand-600">
                <x-icon name="upload" class="size-5" />
            </span>
            <span class="min-w-0 flex-1 text-left">
                <span x-show="!fileName" class="block text-sm font-semibold text-stone-700 group-hover:text-brand-700">Klik atau seret file ke sini</span>
                <span x-show="fileName" x-cloak class="block truncate text-sm font-semibold text-stone-900" x-text="fileName"></span>
                <span class="block text-xs text-stone-500" x-text="fileName ? fileSize : @js(trim(($formats ? $formats.' · ' : '').'maks. '.$maxLabel))">{{ trim(($formats ? $formats.' · ' : '').'maks. '.$maxLabel) }}</span>
            </span>
            <button type="button" x-show="fileName" x-cloak @click.prevent="reset()" class="shrink-0 text-xs font-semibold text-rose-600 hover:underline">Ganti</button>
            <input type="file" id="{{ $id }}" name="{{ $name }}" x-ref="file" class="sr-only" @change="pick($event.target.files[0])"
                   @if ($extensions) accept="{{ collect($extensions)->map(fn ($e) => '.'.$e)->implode(',') }}" @endif
                   {{ $attributes }}>
        </label>
        <p x-show="error" x-cloak x-text="error" class="field-error"></p>
    </div>
</x-form.field>
