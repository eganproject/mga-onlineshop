{{-- Isian kunci rahasia di Admin › Integrasi: nilai lama tidak pernah dikirim ke browser. --}}
@php
    $field = $fields[$integration][$name];
    $masked = $secrets[$integration][$name] ?? null;
    $id = $integration.'_'.$name;
    $inputName = $integration.'['.$name.']';
@endphp
<div x-data="{ show: false, clear: false }">
    <x-form.field :label="$label" :for="$id" :name="$inputName" :tooltip="$tooltip ?? null" :tooltip-tips="$tooltipTips ?? null">
        <div class="relative">
            <input x-bind:type="show ? 'text' : 'password'" type="password" id="{{ $id }}" name="{{ $inputName }}"
                   autocomplete="new-password" spellcheck="false" x-bind:disabled="clear"
                   placeholder="{{ $masked ? 'Kosongkan untuk tetap memakai kunci saat ini' : $placeholder }}"
                   @class(['input pr-12 font-mono', 'input-error' => $errors->has($integration.'.'.$name)])>
            <button type="button" x-on:click="show = !show" class="absolute inset-y-0 right-0 grid w-11 place-items-center text-stone-400 hover:text-stone-600"
                    x-bind:aria-label="show ? 'Sembunyikan' : 'Tampilkan'">
                <x-icon name="eye" class="size-4" />
            </button>
        </div>
        <x-slot:after>
            <p class="hint">
                @if ($masked)
                    Saat ini: <code class="font-mono text-stone-600">{{ $masked }}</code>
                    ({{ $field['stored'] ? 'tersimpan di panel admin' : 'dari .env' }})
                @else
                    Belum diisi.
                @endif
            </p>
        </x-slot:after>
    </x-form.field>
    @if ($field['stored'])
        <label class="mt-2 inline-flex items-center gap-2 text-xs font-semibold text-stone-600">
            <input type="checkbox" name="{{ $integration }}[clear_{{ $name }}]" value="1" class="checkbox" x-model="clear">
            Hapus kunci dari panel admin{{ filled($field['default']) ? ' (kembali ke .env)' : '' }}
        </label>
    @endif
</div>
