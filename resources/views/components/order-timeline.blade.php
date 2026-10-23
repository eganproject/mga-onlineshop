@props(['order', 'admin' => false])
{{-- Riwayat status pesanan, terbaru di atas. --}}
<ol class="relative space-y-4 border-l-2 border-stone-100 pl-5">
    @foreach ($order->statusHistories->reverse() as $history)
        <li class="relative">
            <span @class([
                'absolute -left-[27px] top-1 size-3 rounded-full ring-4 ring-white',
                'bg-stone-300' => $history->isNoteOnly(),
                'bg-rose-500' => ! $history->isNoteOnly() && $history->status === \App\Enums\OrderStatus::Cancelled,
                'bg-brand-500' => ! $history->isNoteOnly() && $history->status !== \App\Enums\OrderStatus::Cancelled,
            ])></span>
            <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                <p class="text-sm font-semibold text-stone-900">{{ $history->isNoteOnly() ? 'Catatan' : $history->status->label() }}</p>
                <p class="text-xs text-stone-400">{{ $history->created_at?->translatedFormat('d M Y, H:i') }}</p>
            </div>
            <p class="text-sm text-stone-600">{{ $history->note ?? $history->status->description() }}</p>
            @if ($admin)
                <p class="text-xs text-stone-400">oleh {{ $history->actorLabel() }}</p>
            @endif
        </li>
    @endforeach
</ol>
