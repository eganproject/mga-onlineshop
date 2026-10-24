@php($editing = $address->exists)
<x-layouts.account :title="$editing ? 'Edit Alamat' : 'Tambah Alamat'" :heading="$editing ? 'Edit alamat' : 'Tambah alamat'">
    <form method="POST" action="{{ $editing ? route('account.addresses.update', $address) : route('account.addresses.store') }}"
          class="card space-y-5 p-6"
          x-data="regionPicker({
              urls: {
                  provinces: @js(route('account.regions.provinces')),
                  cities: @js(route('account.regions.cities', '__ID__')),
                  districts: @js(route('account.regions.districts', '__ID__')),
              },
              provinceId: @js(old('province_id', $address->province_id)),
              cityId: @js(old('city_id', $address->city_id)),
              districtId: @js(old('district_id', $address->district_id)),
              postalCode: @js(old('postal_code', $address->postal_code)),
          })">
        @csrf
        @if ($editing) @method('PUT') @endif
        <input type="hidden" name="redirect_to" value="{{ request('redirect_to') }}">

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <x-form.input name="label" label="Label alamat" :value="$address->label" list="label-options" required placeholder="Rumah, Kantor, ...">
                <x-slot:after><datalist id="label-options"><option value="Rumah"><option value="Kantor"><option value="Kos"><option value="Toko"></datalist></x-slot:after>
            </x-form.input>
            <div class="hidden sm:block"></div>
            <x-form.input name="recipient_name" label="Nama penerima" :value="$address->recipient_name ?? auth()->user()->name" required autocomplete="name" />
            <x-form.phone name="phone" label="No. HP penerima" :value="$address->phone ?? auth()->user()->phone" required />
        </div>

        @if ($editing && ! $address->canShip())
            <p class="flex gap-2 rounded-xl bg-amber-50 p-3 text-xs text-amber-800"><x-icon name="warning" class="size-4 shrink-0" /> Alamat ini dibuat dengan format lama ({{ collect([$address->district, $address->city, $address->province])->filter()->implode(', ') }}). Pilih ulang provinsi, kota, dan kecamatan agar ongkir bisa dihitung.</p>
        @endif

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            @foreach ([
                ['province', 'province_id', 'Provinsi'],
                ['city', 'city_id', 'Kota / Kabupaten'],
                ['district', 'district_id', 'Kecamatan'],
            ] as [$ref, $field, $text])
                <x-form.field :label="$text" :for="$field" :name="$field">
                    <select id="{{ $field }}" name="{{ $field }}" x-ref="{{ $ref }}" required
                            @class(['input', 'input-error' => $errors->has($field)])></select>
                </x-form.field>
            @endforeach
            <x-form.input name="postal_code" label="Kode pos" x-model="postalCode" inputmode="numeric" maxlength="5" placeholder="5 digit" />
        </div>
        <p x-show="error" x-text="error" x-cloak class="flex gap-2 rounded-xl bg-rose-50 p-3 text-xs text-rose-700"></p>
        <x-form.textarea name="address_line" label="Alamat lengkap" :value="$address->address_line" rows="3" maxlength="500" required
                         placeholder="Nama jalan, nomor rumah, RT/RW, patokan" />
        <label class="flex items-center gap-2 text-sm text-stone-600">
            <input type="hidden" name="is_default" value="0">
            <input type="checkbox" name="is_default" value="1" class="checkbox" @checked(old('is_default', $address->is_default))> Jadikan alamat utama
        </label>
        <div class="flex gap-2 border-t border-stone-100 pt-5">
            <button class="btn btn-ink rounded-full">{{ $editing ? 'Simpan perubahan' : 'Simpan alamat' }}</button>
            <a href="{{ request('redirect_to') === 'checkout' ? route('checkout.index') : route('account.addresses.index') }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>
</x-layouts.account>
