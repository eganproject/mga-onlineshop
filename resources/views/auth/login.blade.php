<x-layouts.auth title="Masuk" heading="Selamat datang kembali 👋" subheading="Masuk untuk melanjutkan belanja dan melihat pesananmu.">
    <x-firebase-login />
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
                <a href="{{ route('password.request') }}" class="-my-2 mb-0 py-2.5 text-xs font-semibold text-ink underline underline-offset-4 lg:my-0 lg:mb-1.5 lg:py-0">Lupa password?</a>
            </div>
            <div class="relative">
                <input id="password" :type="show ? 'text' : 'password'" type="password" name="password" required autocomplete="current-password"
                       placeholder="••••••••" class="input pr-11">
                <button type="button" @click="show = !show" class="absolute right-0.5 top-1/2 grid size-10 -translate-y-1/2 place-items-center text-stone-400 hover:text-stone-600" aria-label="Tampilkan password">
                    <x-icon name="eye" class="size-5" />
                </button>
            </div>
            <x-input-error for="password" />
        </div>
        <label class="flex items-center gap-2 text-sm text-stone-600">
            <input type="checkbox" name="remember" class="checkbox"> Ingat saya
        </label>
        <button class="btn btn-ink rounded-full w-full py-3">Masuk</button>
    </form>
    <p class="mt-8 text-center text-sm text-stone-500">
        Belum punya akun? <a href="{{ route('register') }}" class="inline-block py-2.5 font-semibold text-ink underline underline-offset-4 lg:py-0">Daftar gratis</a>
    </p>
</x-layouts.auth>
