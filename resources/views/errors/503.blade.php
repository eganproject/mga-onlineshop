<x-layouts.base :noindex="true" title="Sedang perawatan">
    <div class="flex min-h-screen flex-col items-center justify-center px-6 text-center">
        <x-wordmark />
        <p class="mt-10 text-7xl font-extrabold tracking-tight text-ink font-display">503</p>
        <h1 class="mt-3 text-2xl font-bold text-stone-900">Toko sedang dalam perawatan</h1>
        <p class="mt-2 max-w-md text-stone-500">Kami sedang memperbarui sistem. Silakan kembali beberapa menit lagi.</p>
        @if ($whatsapp = config('shop.whatsapp'))
            <a href="https://wa.me/{{ preg_replace('/\D/', '', $whatsapp) }}" class="btn btn-ink btn-pill mt-8">Hubungi via WhatsApp</a>
        @endif
    </div>
</x-layouts.base>
