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

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="label" class="label">Label alamat</label>
                <input id="label" name="label" value="{{ old('label', $address->label) }}" list="label-options" required class="input @error('label') input-error @enderror">
                <datalist id="label-options"><option value="Rumah"><option value="Kantor"><option value="Kos"><option value="Toko"></datalist>
                <x-input-error for="label" />
            </div>
            <div></div>
            <div>
                <label for="recipient_name" class="label">Nama penerima</label>
                <input id="recipient_name" name="recipient_name" value="{{ old('recipient_name', $address->recipient_name ?? auth()->user()->name) }}" required class="input @error('recipient_name') input-error @enderror">
                <x-input-error for="recipient_name" />
            </div>
            <div>
                <label for="phone" class="label">No. HP penerima</label>
                <input id="phone" type="tel" name="phone" value="{{ old('phone', $address->phone ?? auth()->user()->phone) }}" required placeholder="08xx" class="input @error('phone') input-error @enderror">
                <x-input-error for="phone" />
            </div>
        </div>

        @if ($editing && ! $address->canShip())
            <p class="flex gap-2 rounded-xl bg-amber-50 p-3 text-xs text-amber-800"><x-icon name="warning" class="size-4 shrink-0" /> Alamat ini dibuat dengan format lama ({{ collect([$address->district, $address->city, $address->province])->filter()->implode(', ') }}). Pilih ulang provinsi, kota, dan kecamatan agar ongkir bisa dihitung.</p>
        @endif

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="province_id" class="label">Provinsi</label>
                <select id="province_id" name="province_id" x-model="provinceId" @change="changeProvince()" required :disabled="loading.provinces"
                        class="input @error('province_id') input-error @enderror">
                    <option value="" x-text="loading.provinces ? 'Memuat...' : 'Pilih provinsi'">Pilih provinsi</option>
                    <template x-for="item in provinces" :key="item.id">
                        <option :value="item.id" x-text="item.name" :selected="item.id === provinceId"></option>
                    </template>
                </select>
                <x-input-error for="province_id" />
            </div>
            <div>
                <label for="city_id" class="label">Kota / Kabupaten</label>
                <select id="city_id" name="city_id" x-model="cityId" @change="changeCity()" required :disabled="!provinceId || loading.cities"
                        class="input disabled:bg-stone-50 disabled:text-stone-400 @error('city_id') input-error @enderror">
                    <option value="" x-text="loading.cities ? 'Memuat...' : (provinceId ? 'Pilih kota/kabupaten' : 'Pilih provinsi dulu')">Pilih provinsi dulu</option>
                    <template x-for="item in cities" :key="item.id">
                        <option :value="item.id" x-text="item.name" :selected="item.id === cityId"></option>
                    </template>
                </select>
                <x-input-error for="city_id" />
            </div>
            <div>
                <label for="district_id" class="label">Kecamatan</label>
                <select id="district_id" name="district_id" x-model="districtId" @change="changeDistrict()" required :disabled="!cityId || loading.districts"
                        class="input disabled:bg-stone-50 disabled:text-stone-400 @error('district_id') input-error @enderror">
                    <option value="" x-text="loading.districts ? 'Memuat...' : (cityId ? 'Pilih kecamatan' : 'Pilih kota dulu')">Pilih kota dulu</option>
                    <template x-for="item in districts" :key="item.id">
                        <option :value="item.id" x-text="item.name" :selected="item.id === districtId"></option>
                    </template>
                </select>
                <x-input-error for="district_id" />
            </div>
            <div>
                <label for="postal_code" class="label">Kode pos</label>
                <input id="postal_code" name="postal_code" x-model="postalCode" inputmode="numeric" maxlength="5" placeholder="5 digit" class="input @error('postal_code') input-error @enderror">
                <x-input-error for="postal_code" />
            </div>
        </div>
        <p x-show="error" x-text="error" x-cloak class="flex gap-2 rounded-xl bg-rose-50 p-3 text-xs text-rose-700"></p>
        <div>
            <label for="address_line" class="label">Alamat lengkap</label>
            <textarea id="address_line" name="address_line" rows="3" required placeholder="Nama jalan, nomor rumah, RT/RW, patokan" class="input @error('address_line') input-error @enderror">{{ old('address_line', $address->address_line) }}</textarea>
            <x-input-error for="address_line" />
        </div>
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
