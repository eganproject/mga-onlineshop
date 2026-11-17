@php
    $editing = $role->exists;
    $locked = $role->isOwner();
    $chosen = array_map(fn ($p) => $p->value, $role->permissionList());
    // Izin yang otomatis ikut: dipakai Alpine supaya saklarnya ikut menyala/mati.
    $deps = collect(\App\Enums\Permission::cases())
        ->filter(fn ($p) => $p->requires())
        ->mapWithKeys(fn ($p) => [$p->name => array_map(fn ($r) => $r->name, $p->requires())])
        ->all();
@endphp
<x-layouts.admin :title="$editing ? 'Ubah Jabatan' : 'Tambah Jabatan'">
    <x-admin.page-header :title="$editing ? 'Ubah jabatan '.$role->name : 'Tambah jabatan'" :back="route('admin.roles.index')"
                         :description="$source ? 'Hak akses disalin dari '.$source->name.'. Ubah seperlunya lalu simpan.' : null" />

    <form method="POST" action="{{ $editing ? route('admin.roles.update', $role) : route('admin.roles.store') }}"
          class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3"
          x-data="{
              deps: @js($deps),
              box(name) { return this.$root.querySelector(`[data-perm='${name}']`) },
              sync(e) {
                  const name = e.target.dataset.perm;
                  if (! name) return;
                  if (e.target.checked) {
                      (this.deps[name] || []).forEach(r => { const b = this.box(r); if (b) b.checked = true });
                  } else {
                      Object.entries(this.deps).forEach(([k, reqs]) => { if (reqs.includes(name)) { const b = this.box(k); if (b) b.checked = false } });
                  }
              },
              setAll(group, on) {
                  this.$root.querySelectorAll(group ? `[data-group='${group}'] [data-perm]` : '[data-perm]').forEach(b => b.checked = on);
                  Object.entries(this.deps).forEach(([k, reqs]) => {
                      if (this.box(k)?.checked) reqs.forEach(r => { const b = this.box(r); if (b) b.checked = true });
                  });
              },
          }"
          @change="sync($event)">
        @csrf
        @if ($editing) @method('PUT') @endif

        {{-- Data jabatan (di HP tampil paling atas, di desktop kolom kanan) --}}
        <div class="space-y-6 lg:sticky lg:top-20 lg:order-last">
            <div class="card space-y-5 p-6">
                <x-form.input name="name" label="Nama jabatan" :value="$role->name" required maxlength="60" autocomplete="off"
                              tooltip="Nama yang tampil di daftar staf dan menu akun, mis. “Admin Gudang” atau “Marketing”." />
                <x-form.textarea name="description" label="Keterangan" :value="$role->description" optional maxlength="255" rows="4"
                                 tooltip="Ringkasan tugas jabatan ini. Tampil saat memilih jabatan di form staf." />
                <div>
                    <div class="label-row">
                        <p class="label">Warna label</p>
                        <x-tooltip label="Warna label" text="Warna badge jabatan di daftar staf, supaya mudah dibedakan." />
                    </div>
                    @php($color = old('color', $role->color ?? 'stone'))
                    <div class="flex flex-wrap gap-2">
                        @foreach (\App\Models\Role::COLORS as $value => [$name, $classes])
                            <label class="cursor-pointer">
                                <input type="radio" name="color" value="{{ $value }}" class="peer sr-only" @checked($color === $value)>
                                <span class="badge {{ $classes }} peer-checked:outline-2 peer-checked:outline-offset-2 peer-checked:outline-brand-500 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand-500">{{ $name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <x-input-error for="color" />
                </div>
            </div>
            <x-admin.save-bar>
                <button class="btn btn-primary w-full py-3">{{ $editing ? 'Simpan hak akses' : 'Buat jabatan' }}</button>
            </x-admin.save-bar>
        </div>

        {{-- Hak akses --}}
        <div class="space-y-6 lg:col-span-2">
            @if ($locked)
                <div class="flex gap-3 rounded-2xl border border-stone-200 bg-stone-50 p-4 text-sm text-stone-600">
                    <x-icon name="lock" class="mt-0.5 size-5 shrink-0 text-stone-500" />
                    <p>Superadmin selalu punya semua hak akses, termasuk mengatur jabatan di halaman ini, supaya panel admin tidak pernah terkunci. Yang bisa diubah hanya nama, keterangan dan warnanya.</p>
                </div>
            @else
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-stone-600">Nyalakan menu dan aksi yang boleh dilakukan jabatan ini. Menu lain disembunyikan dan ditolak bila dibuka lewat link.</p>
                    <div class="flex gap-2">
                        <button type="button" class="btn btn-secondary btn-sm" @click="setAll(null, true)">Pilih semua</button>
                        <button type="button" class="btn btn-ghost btn-sm" @click="setAll(null, false)">Kosongkan</button>
                    </div>
                </div>
                <x-input-error for="permissions" />
            @endif

            @foreach ($groups as $group => $permissions)
                <div class="card @container p-5 sm:p-6" data-group="{{ \Illuminate\Support\Str::slug($group) }}">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h2 class="font-bold text-stone-900">{{ $group }}</h2>
                        @unless ($locked)
                            <button type="button" class="text-xs font-semibold text-brand-700 hover:underline" @click="setAll('{{ \Illuminate\Support\Str::slug($group) }}', true)">Pilih semua</button>
                        @endunless
                    </div>
                    <div class="grid grid-cols-1 gap-3 @xl:grid-cols-2">
                        @foreach ($permissions as $permission)
                            @php($requires = $permission->requires())
                            <x-form.switch :name="'permissions['.$permission->name.']'" :label="$permission->label()" card
                                           :checked="in_array($permission->value, $chosen, true)"
                                           data-perm="{{ $permission->name }}" :disabled="$locked">
                                @if ($permission->isSensitive())
                                    <x-slot:label-slot> <span class="badge ml-1 bg-rose-50 text-rose-700 ring-rose-600/20">Sensitif</span></x-slot:label-slot>
                                @endif
                                {{ $permission->hint() }}
                                @if ($requires)
                                    <span class="block text-stone-400">Otomatis ikut: {{ collect($requires)->map->label()->implode(', ') }}.</span>
                                @endif
                            </x-form.switch>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </form>
</x-layouts.admin>
