<x-layouts.error code="403" title="Akses ditolak" icon="lock" tone="accent"
                 message="Kamu tidak memiliki izin untuk membuka halaman ini. Kalau menurutmu ini keliru, coba masuk dengan akun yang benar atau hubungi admin.">
    <div class="mt-8 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center">
        <a href="{{ url('/') }}" class="btn btn-ink btn-pill">
            <x-icon name="home" class="size-4" /> Kembali ke beranda
        </a>
        <a href="{{ url('/masuk') }}" class="btn btn-outline btn-pill">
            <x-icon name="user" class="size-4" /> Masuk dengan akun lain
        </a>
    </div>
</x-layouts.error>
