@props(['icon' => 'cube', 'title', 'description' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-14 text-center']) }}>
    <div class="mb-4 grid size-14 place-items-center rounded-full bg-sand-100 text-ink">
        <x-icon :name="$icon" class="size-7" />
    </div>
    <h3 class="text-base font-semibold text-stone-900">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-stone-500">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-5">{{ $slot }}</div>
    @endif
</div>
