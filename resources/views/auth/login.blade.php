<x-layouts.auth title="Masuk" heading="Selamat datang kembali 👋" subheading="Masuk untuk melanjutkan belanja dan melihat pesananmu.">
    <form method="POST" action="{{ route('login') }}" class="space-y-5" x-data="{ show: false }">
        @csrf
        <div>
            <label for="email" class="label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   placeholder="nama@email.com" @class(['input', 'input-error' => $errors->has('email')])>
            <x-input-error for="email" />
        </div>
        <div>
            <div class="flex items-center justify-between">
                <label for="password" class="label">Password</label>
                <a href="{{ route('password.request') }}" class="mb-1.5 text-xs font-semibold text-brand-600 hover:text-brand-700">Lupa password?</a>
            </div>
            <div class="relative">
                <input id="password" :type="show ? 'text' : 'password'" type="password" name="password" required autocomplete="current-password"
                       placeholder="••••••••" class="input pr-11">
                <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-stone-400 hover:text-stone-600" aria-label="Tampilkan password">
                    <x-icon name="eye" class="size-5" />
                </button>
            </div>
            <x-input-error for="password" />
        </div>
        <label class="flex items-center gap-2 text-sm text-stone-600">
            <input type="checkbox" name="remember" class="checkbox"> Ingat saya
        </label>
        <button class="btn btn-primary w-full py-3">Masuk</button>
    </form>
    <p class="mt-8 text-center text-sm text-stone-500">
        Belum punya akun? <a href="{{ route('register') }}" class="font-semibold text-brand-600 hover:text-brand-700">Daftar gratis</a>
    </p>
</x-layouts.auth>
