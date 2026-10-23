<x-layouts.auth title="Verifikasi Email" heading="Cek emailmu" subheading="Kami sudah mengirim tautan verifikasi ke {{ auth()->user()->email }}. Klik tautan itu untuk mengaktifkan email akunmu.">
    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button class="btn btn-ink w-full rounded-full py-3">Kirim ulang tautan verifikasi</button>
    </form>
    <p class="mt-8 text-center text-sm text-stone-500">
        Salah email? <a href="{{ auth()->user()->isAdmin() ? route('admin.profile.edit') : route('account.profile.edit') }}" class="font-semibold text-ink underline underline-offset-4">Ubah di profil</a>
        atau <a href="{{ route('home') }}" class="font-semibold text-ink underline underline-offset-4">lanjut belanja</a>.
    </p>
</x-layouts.auth>
