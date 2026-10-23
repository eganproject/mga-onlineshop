@php
    $editing = $member->exists;
    $currentRole = old('staff_role', $member->staffRole()?->value ?? \App\Enums\StaffRole::Warehouse->value);
@endphp
<x-layouts.admin :title="$editing ? 'Ubah Staf' : 'Tambah Staf'">
    <x-admin.page-header :title="$editing ? 'Ubah akun staf' : 'Tambah akun staf'" :back="route('admin.staff.index')" />

    <form method="POST" action="{{ $editing ? route('admin.staff.update', $member) : route('admin.staff.store') }}" class="grid gap-6 lg:grid-cols-3">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="card space-y-5 p-6 lg:col-span-2">
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="name" class="label">Nama</label>
                    <input id="name" name="name" value="{{ old('name', $member->name) }}" required maxlength="100" class="input @error('name') input-error @enderror">
                    <x-input-error for="name" />
                </div>
                <div>
                    <label for="email" class="label">Email (untuk login)</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $member->email) }}" required class="input @error('email') input-error @enderror" autocomplete="off">
                    <x-input-error for="email" />
                </div>
                <div>
                    <label for="phone" class="label">No. HP <span class="font-normal text-stone-400">(opsional)</span></label>
                    <input id="phone" name="phone" type="tel" value="{{ old('phone', $member->phone) }}" class="input @error('phone') input-error @enderror">
                    <x-input-error for="phone" />
                </div>
                <div>
                    <label for="password" class="label">{{ $editing ? 'Password baru' : 'Password' }} @if ($editing)<span class="font-normal text-stone-400">(kosongkan bila tidak diganti)</span>@endif</label>
                    <input id="password" name="password" type="password" @required(! $editing) class="input @error('password') input-error @enderror" autocomplete="new-password">
                    <x-input-error for="password" />
                </div>
                <div>
                    <label for="password_confirmation" class="label">Ulangi password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" @required(! $editing) class="input" autocomplete="new-password">
                </div>
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
