@props(['title', 'description' => null, 'back' => null])
<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        @if ($back)
            <a href="{{ $back }}" class="mb-2 inline-flex items-center gap-1 text-sm font-medium text-stone-500 hover:text-stone-800">
                <x-icon name="arrow-left" class="size-4" /> Kembali
            </a>
        @endif
        <h1 class="text-2xl font-extrabold tracking-tight text-stone-900">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 text-sm text-stone-500">{{ $description }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
