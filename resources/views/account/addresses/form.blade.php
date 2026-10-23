@php($editing = $address->exists)
<x-layouts.account :title="$editing ? 'Edit Alamat' : 'Tambah Alamat'" :heading="$editing ? 'Edit alamat' : 'Tambah alamat'">
    <form method="POST" action="{{ $editing ? route('account.addresses.update', $address) : route('account.addresses.store') }}"
          class="card space-y-5 p-6"
          x-data="{
              province: @js(old('province', $address->province)),
              city: @js(old('city', $address->city)),
              district: @js(old('district', $address->district)),
              postal: @js(old('postal_code', $address->postal_code)),
              destination: @js(old('destination_id', $address->destination_id)),
          }"
          @destination-selected="province = $event.detail.province; city = $event.detail.city; district = $event.detail.district; postal = $event.detail.postal_code || postal; destination = $event.detail.id">
        @csrf
        @if ($editing) @method('PUT') @endif
        <input type="hidden" name="redirect_to" value="{{ request('redirect_to') }}">
        <input type="hidden" name="destination_id" :value="destination">

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

        <div x-data="destinationSearch(@js(route('account.addresses.destinations')), '')" class="relative">
            <label for="destination-search" class="label">Cari kecamatan / kota</label>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-stone-400" />
                <input id="destination-search" type="search" x-model="query" @input.debounce.400ms="search()" @focus="results.length && (open = true)" @click.outside="open = false"
                       autocomplete="off" placeholder="Ketik minimal 3 huruf, contoh: Coblong" class="input pl-10">
                <span x-show="loading" x-cloak class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-stone-400">Mencari...</span>
            </div>
            <p class="mt-1.5 text-xs text-stone-400">Pilih dari daftar agar ongkos kirim bisa dihitung. Provinsi, kota, dan kecamatan terisi otomatis.</p>
            <p x-show="error" x-text="error" x-cloak class="mt-1.5 text-xs text-rose-600"></p>
            <ul x-show="open && results.length" x-cloak class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-2xl border border-stone-200 bg-white p-1 shadow-xl">
                <template x-for="item in results" :key="item.id">
                    <li><button type="button" @click="choose(item)" class="w-full rounded-xl px-3 py-2 text-left text-sm text-stone-700 hover:bg-brand-50" x-text="item.label"></button></li>
                </template>
            </ul>
            <p x-show="open && !results.length && !loading && query.length >= 3" x-cloak class="mt-1.5 text-xs text-stone-500">Lokasi tidak ditemukan.</p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="province" class="label">Provinsi</label>
                <input id="province" name="province" x-model="province" required class="input @error('province') input-error @enderror">
                <x-input-error for="province" />
            </div>
            <div>
                <label for="city" class="label">Kota / Kabupaten</label>
                <input id="city" name="city" x-model="city" required class="input @error('city') input-error @enderror">
                <x-input-error for="city" />
            </div>
            <div>
                <label for="district" class="label">Kecamatan</label>
                <input id="district" name="district" x-model="district" class="input @error('district') input-error @enderror">
                <x-input-error for="district" />
            </div>
            <div>
                <label for="postal_code" class="label">Kode pos</label>
                <input id="postal_code" name="postal_code" x-model="postal" inputmode="numeric" class="input @error('postal_code') input-error @enderror">
                <x-input-error for="postal_code" />
            </div>
        </div>
        <div>
            <label for="address_line" class="label">Alamat lengkap</label>
            <textarea id="address_line" name="address_line" rows="3" required placeholder="Nama jalan, nomor rumah, RT/RW, patokan" class="input @error('address_line') input-error @enderror">{{ old('address_line', $address->address_line) }}</textarea>
            <x-input-error for="address_line" />
        </div>
        <p x-show="!destination" x-cloak class="flex gap-2 rounded-xl bg-amber-50 p-3 text-xs text-amber-800"><x-icon name="warning" class="size-4 shrink-0" /> Lokasi belum dipilih dari daftar. Alamat tetap bisa disimpan, tapi ongkir belum bisa dihitung saat checkout.</p>
        <label class="flex items-center gap-2 text-sm text-stone-600">
            <input type="hidden" name="is_default" value="0">
            <input type="checkbox" name="is_default" value="1" class="checkbox" @checked(old('is_default', $address->is_default))> Jadikan alamat utama
        </label>
        <div class="flex gap-2 border-t border-stone-100 pt-5">
            <button class="btn btn-primary">{{ $editing ? 'Simpan perubahan' : 'Simpan alamat' }}</button>
            <a href="{{ request('redirect_to') === 'checkout' ? route('checkout.index') : route('account.addresses.index') }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>
</x-layouts.account>
