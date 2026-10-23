@props(['title'])
{{-- Halaman siap cetak (A4/A5). Tombol cetak disembunyikan saat dicetak. --}}
<x-layouts.base :title="$title" body-class="bg-stone-100 text-stone-900 print:bg-white">
    <x-slot:head>
        <style>
            @page { size: A4; margin: 12mm; }
            @media print { .print-sheet { box-shadow: none !important; margin: 0 !important; border-radius: 0 !important; } }
        </style>
    </x-slot:head>
    <div class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-stone-200 bg-white/90 px-4 py-3 backdrop-blur print:hidden">
        <p class="text-sm font-semibold text-stone-700">{{ $title }}</p>
        <div class="flex gap-2">
            <button type="button" onclick="window.close()" class="btn btn-ghost btn-sm">Tutup</button>
            <button type="button" onclick="window.print()" class="btn btn-primary btn-sm"><x-icon name="printer" class="size-4" /> Cetak</button>
        </div>
    </div>
    <main class="mx-auto max-w-3xl space-y-6 p-4 sm:p-8 print:max-w-none print:space-y-0 print:p-0">
        {{ $slot }}
    </main>
</x-layouts.base>
