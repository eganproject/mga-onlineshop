<form method="POST" action="{{ $updateRoute }}" class="card space-y-5 p-6">
    @csrf
    @method('PATCH')
    <div>
        <h2 class="font-bold text-stone-900">Data diri</h2>
        <p class="text-sm text-stone-500">Nama, email, dan nomor HP.</p>
    </div>
    <div>
        <label for="name" class="label">Nama lengkap</label>
        <input id="name" name="name" value="{{ old('name', $user->name) }}" required class="input @error('name') input-error @enderror">
        <x-input-error for="name" />
    </div>
    <div>
        <label for="email" class="label">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required class="input @error('email') input-error @enderror">
        <x-input-error for="email" />
    </div>
    <x-form.phone name="phone" label="No. HP" :value="$user->phone" />
    <button class="btn btn-ink rounded-full">Simpan perubahan</button>
</form>

<form method="POST" action="{{ $passwordRoute }}" class="card space-y-5 p-6">
    @csrf
    @method('PUT')
    <div>
        <h2 class="font-bold text-stone-900">Ubah password</h2>
        <p class="text-sm text-stone-500">Gunakan minimal 8 karakter.</p>
    </div>
    <div>
        <label for="current_password" class="label">Password saat ini</label>
        <input id="current_password" type="password" name="current_password" autocomplete="current-password" class="input @error('current_password', 'password') input-error @enderror">
        <x-input-error for="current_password" bag="password" />
    </div>
    <div>
        <label for="password" class="label">Password baru</label>
        <input id="password" type="password" name="password" autocomplete="new-password" class="input @error('password', 'password') input-error @enderror">
        <x-input-error for="password" bag="password" />
    </div>
    <div>
        <label for="password_confirmation" class="label">Ulangi password baru</label>
        <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" class="input">
    </div>
    <button class="btn btn-dark">Ubah password</button>
</form>
