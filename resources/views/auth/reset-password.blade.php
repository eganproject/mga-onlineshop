<x-layouts.auth title="Reset Password" heading="Buat password baru" subheading="Gunakan password yang kuat dan mudah kamu ingat.">
    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <div>
            <label for="email" class="label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autocomplete="username" @class(['input', 'input-error' => $errors->has('email')])>
            <x-input-error for="email" />
        </div>
        <div>
            <label for="password" class="label">Password baru</label>
            <input id="password" type="password" name="password" required autofocus autocomplete="new-password" @class(['input', 'input-error' => $errors->has('password')])>
            <x-input-error for="password" />
        </div>
        <div>
            <label for="password_confirmation" class="label">Ulangi password baru</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="input">
        </div>
        <button class="btn btn-primary w-full py-3">Simpan password</button>
    </form>
</x-layouts.auth>
