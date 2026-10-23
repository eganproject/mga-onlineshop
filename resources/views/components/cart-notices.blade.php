@props(['notices' => []])

@if (! empty($notices))
    <div {{ $attributes->class('flex gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900') }} role="status">
        <x-icon name="warning" class="size-5 shrink-0" />
        <div>
            <p class="font-semibold">Keranjangmu kami perbarui</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-4 text-amber-800">
                @foreach ($notices as $notice)
                    <li>{{ $notice }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
