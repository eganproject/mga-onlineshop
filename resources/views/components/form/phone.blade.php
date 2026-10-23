{{--
    Input nomor HP: keyboard telepon di HP, hanya angka, +, spasi, dan -.
    <x-form.phone name="phone" label="No. HP" :value="$user->phone" />
--}}
@props(['name' => 'phone', 'placeholder' => '08xx-xxxx-xxxx'])
<div x-data class="contents">
    <x-form.input type="tel" :name="$name" icon="phone" x-phone autocomplete="tel" maxlength="20" :placeholder="$placeholder" {{ $attributes }} />
</div>
