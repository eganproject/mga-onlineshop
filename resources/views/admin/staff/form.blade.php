@php
    $editing = $member->exists;
    $currentRole = old('staff_role', $member->staffRole()?->value ?? \App\Enums\StaffRole::Warehouse->value);
@endphp
<x-layouts.admin :title="$editing ? 'Ubah Staf' : 'Tambah Staf'">
    <x-admin.page-header :title="$editing ? 'Ubah akun staf' : 'Tambah akun staf'" :back="route('admin.staff.index')" />

    <form method="POST" action="{{ $editing ? route('admin.staff.update', $member) : route('admin.staff.store') }}" class="grid items-start gap-6 lg:grid-cols-3">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="card space-y-5 p-6 lg:col-span-2">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="name" label="Nama" :value="$member->name" required maxlength="100" autocomplete="off" wrapper-class="sm:col-span-2" />
                <x-form.input type="email" name="email" label="Email (untuk login)" icon="envelope" :value="$member->email" required autocomplete="off" />
                <x-form.phone name="phone" label="No. HP" :value="$member->phone" optional />
                <x-form.input type="password" name="password" :label="$editing ? 'Password baru' : 'Password'" icon="lock" :required="! $editing" autocomplete="new-password"
                              :optional="$editing ? 'kosongkan bila tidak diganti' : false" hint="Minimal 8 karakter." />
                <x-form.input type="password" name="password_confirmation" label="Ulangi password" icon="lock" :required="! $editing" autocomplete="new-password" />
            </div>
        </div>

        <div class="space-y-6">
            <div class="card p-6">
                <p class="label">Jabatan</p>
                <div class="grid gap-2">
                    @foreach ($roles as $role)
                        <label class="flex cursor-pointer gap-3 rounded-xl border border-stone-200 p-3 transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                            <input type="radio" name="staff_role" value="{{ $role->value }}" class="mt-0.5 text-brand-600 focus:ring-brand-500/30" @checked($currentRole === $role->value)>
                            <span>
                                <span class="block text-sm font-semibold text-stone-800">{{ $role->label() }}</span>
                                <span class="text-xs text-stone-500">{{ $role->description() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <x-input-error for="staff_role" />
            </div>
            <button class="btn btn-primary w-full">{{ $editing ? 'Simpan perubahan' : 'Buat akun staf' }}</button>
        </div>
    </form>
</x-layouts.admin>
