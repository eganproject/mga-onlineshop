<x-layouts.auth title="Lupa Password" heading="Lupa password?" subheading="Masukkan email akunmu, kami kirimkan link untuk membuat password baru.">
    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf
        <div>
            <label for="email" class="label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus @class(['input', 'input-error' => $errors->has('email')])>
            <x-input-error for="email" />
        </div>
        <button class="btn btn-primary w-full py-3">Kirim link reset</button>
    </form>
    <p class="mt-8 text-center text-sm text-stone-500">
        Ingat password? <a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:text-brand-700">Kembali masuk</a>
    </p>
</x-layouts.auth>
