@php($whatsapp = preg_replace('/\D/', '', (string) config('shop.whatsapp')))
<x-layouts.error code="503" title="Toko sedang dalam perawatan" icon="cog"
                 message="Kami sedang memperbarui sistem supaya belanja makin nyaman. Silakan kembali beberapa menit lagi.">
    <div class="mt-8 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center">
        <a href="{{ url()->current() }}" class="btn btn-ink btn-pill">
            <x-icon name="refresh" class="size-4" /> Muat ulang
        </a>
        @if ($whatsapp)
            <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="btn btn-outline btn-pill">
                <x-icon name="chat" class="size-4" /> Hubungi via WhatsApp
            </a>
        @endif
    </div>
</x-layouts.error>
