{{-- Hasil lacak resi. Dimuat lewat fetch ke dalam <x-order-tracking-panel>. --}}
@if (! $result)
    <div class="flex gap-3 rounded-2xl bg-stone-50 p-4 text-sm text-stone-600">
        <x-icon name="info" class="size-5 shrink-0 text-stone-400" />
        <p>Info pelacakan belum tersedia. Biasanya data dari kurir muncul beberapa jam setelah paket diserahkan. Coba lagi nanti.</p>
    </div>
@else
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <span @class([
                'badge',
                'bg-emerald-50 text-emerald-700 ring-emerald-600/20' => $result->delivered,
                'bg-violet-50 text-violet-700 ring-violet-600/20' => ! $result->delivered,
            ])>
                <span class="size-1.5 rounded-full bg-current"></span>
                {{ $result->delivered ? 'Paket sudah diterima' : 'Dalam pengiriman' }}
            </span>
            @if ($result->checkedAt)
                <span class="text-xs text-stone-400">Dicek {{ $result->checkedAt->timezone(config('app.timezone'))->translatedFormat('d M Y, H:i') }}</span>
            @endif
        </div>

        @if ($result->delivered && $result->receiver)
            <p class="text-sm text-stone-600">Diterima oleh <span class="font-semibold text-stone-900">{{ $result->receiver }}</span>@if ($result->deliveredAt) pada {{ $result->deliveredAt->timezone(config('app.timezone'))->translatedFormat('d M Y, H:i') }}@endif.</p>
        @endif

        @if ($result->events)
            <ol class="relative space-y-4 border-l-2 border-stone-100 pl-5">
                @foreach ($result->events as $event)
                    <li class="relative">
                        <span @class([
                            'absolute -left-[27px] top-1 size-3 rounded-full ring-4 ring-white',
                            'bg-brand-500' => $loop->first,
                            'bg-stone-300' => ! $loop->first,
                        ])></span>
                        <p @class(['text-sm', 'font-semibold text-stone-900' => $loop->first, 'text-stone-600' => ! $loop->first])>{{ $event->description }}</p>
                        <p class="text-xs text-stone-400">
                            {{ $event->at?->timezone(config('app.timezone'))->translatedFormat('d M Y, H:i') }}@if ($event->location) · {{ $event->location }}@endif
                        </p>
                    </li>
                @endforeach
            </ol>
        @else
            <p class="text-sm text-stone-500">Kurir belum mengirim riwayat perjalanan paket.</p>
        @endif
    </div>
@endif
