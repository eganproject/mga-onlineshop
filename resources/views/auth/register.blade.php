<x-layouts.auth title="Daftar" heading="Buat akun baru" subheading="Gratis, cepat, dan bikin belanja jadi lebih mudah.">
    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf
        <div>
            <label for="name" class="label">Nama lengkap</label>
            <input id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" @class(['input', 'input-error' => $errors->has('name')])>
            <x-input-error for="name" />
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="email" class="label">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" @class(['input', 'input-error' => $errors->has('email')])>
                <x-input-error for="email" />
            </div>
            <x-form.phone name="phone" label="No. HP" optional />
        </div>
        <div>
            <label for="password" class="label">Password</label>
            <input id="password" type="password" name="password" required autocomplete="new-password" @class(['input', 'input-error' => $errors->has('password')])>
            <p class="mt-1.5 text-xs text-stone-400">Minimal 8 karakter.</p>
            <x-input-error for="password" />
        </div>
        <div>
            <label for="password_confirmation" class="label">Ulangi password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="input">
        </div>
        <button class="btn btn-ink rounded-full w-full py-3">Daftar sekarang</button>
    </form>
    <p class="mt-8 text-center text-sm text-stone-500">
        Sudah punya akun? <a href="{{ route('login') }}" class="font-semibold text-ink underline underline-offset-4">Masuk</a>
    </p>
</x-layouts.auth>
