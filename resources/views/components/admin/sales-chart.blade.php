@php($uid = 'chart-'.\Illuminate\Support\Str::random(6))
<div x-data="{ metric: 'revenue', active: null }" {{ $attributes->class('min-w-0') }}>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>{{ $title ?? '' }}</div>
        <div class="inline-flex rounded-xl bg-stone-100 p-1 text-xs font-semibold" role="tablist" aria-label="Pilih metrik grafik">
            @foreach ($metrics as $key => $metric)
                <button type="button" role="tab" @click="metric = '{{ $key }}'" :aria-selected="metric === '{{ $key }}'"
                        :class="metric === '{{ $key }}' ? 'bg-white text-stone-900 shadow-sm' : 'text-stone-500 hover:text-stone-800'"
                        class="rounded-lg px-3 py-1.5 transition">{{ $metric['label'] }}</button>
            @endforeach
        </div>
    </div>

    @foreach ($metrics as $key => $metric)
        <div x-show="metric === '{{ $key }}'" @if (! $loop->first) x-cloak @endif class="flex gap-3">
            {{-- Sumbu Y --}}
            <div class="relative {{ $height }} w-14 shrink-0 text-right text-[11px] tabular-nums text-stone-400" aria-hidden="true">
                @foreach ($metric['ticks'] as $tick)
                    <span class="absolute right-0 -translate-y-1/2 whitespace-nowrap" style="top: {{ $tick['top'] }}%">{{ $tick['label'] }}</span>
                @endforeach
            </div>

            <div class="min-w-0 flex-1">
                <div class="relative {{ $height }}" @mouseleave="active = null">
                    {{-- Garis bantu --}}
                    @foreach ($metric['ticks'] as $tick)
                        <div @class(['absolute inset-x-0 border-t', 'border-stone-200' => $tick['top'] === 100, 'border-dashed border-stone-100' => $tick['top'] !== 100]) style="top: {{ $tick['top'] }}%"></div>
                    @endforeach

                    <svg viewBox="0 0 {{ \App\View\Components\Admin\SalesChart::WIDTH }} {{ \App\View\Components\Admin\SalesChart::HEIGHT }}" preserveAspectRatio="none" class="absolute inset-0 size-full overflow-visible" role="img" aria-label="Grafik {{ strtolower($metric['label']) }}">
                        <defs>
                            <linearGradient id="{{ $uid }}-{{ $key }}" x1="0" x2="0" y1="0" y2="1">
                                <stop offset="0%" stop-color="var(--color-brand-500)" stop-opacity="0.22" />
                                <stop offset="100%" stop-color="var(--color-brand-500)" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        @if ($metric['area'])
                            <path d="{{ $metric['area'] }}" fill="url(#{{ $uid }}-{{ $key }})" />
                            <path d="{{ $metric['path'] }}" fill="none" stroke="var(--color-brand-600)" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                        @endif
                    </svg>

                    {{-- Crosshair, titik & tooltip --}}
                    @foreach ($metric['points'] as $i => $point)
                        <div x-show="active === {{ $i }}" x-cloak class="pointer-events-none absolute inset-y-0 w-px bg-stone-300" style="left: {{ $point['left'] }}%"></div>
                        <div x-show="active === {{ $i }}" x-cloak class="pointer-events-none absolute z-10 size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-brand-600 ring-2 ring-white" style="left: {{ $point['left'] }}%; top: {{ $point['top'] }}%"></div>
                        <div x-show="active === {{ $i }}" x-cloak
                             @class([
                                 'pointer-events-none absolute top-0 z-20 -translate-y-2 whitespace-nowrap rounded-xl bg-stone-900 px-3 py-2 text-xs text-white shadow-lg',
                                 '-translate-x-1/2' => $point['left'] > 15 && $point['left'] < 85,
                                 '-translate-x-full' => $point['left'] >= 85,
                             ]) style="left: {{ $point['left'] }}%">
                            <p class="text-[11px] text-stone-300">{{ $series[$i]['label'] }}</p>
                            <p class="font-bold tabular-nums">{{ $point['value'] }}</p>
                        </div>
                    @endforeach

                    {{-- Area hover per titik (lebih lebar dari titiknya) --}}
                    @php($hitWidth = count($metric['points']) > 1 ? 100 / (count($metric['points']) - 1) : 100)
                    <div class="absolute inset-0 overflow-hidden">
                        @foreach ($metric['points'] as $i => $point)
                            <div class="absolute inset-y-0" style="left: {{ max(0, $point['left'] - $hitWidth / 2) }}%; width: {{ $hitWidth }}%"
                                 @mouseenter="active = {{ $i }}" @touchstart.passive="active = {{ $i }}"></div>
                        @endforeach
                    </div>
                </div>

                {{-- Sumbu X --}}
                <div class="relative mt-2 h-4 text-[11px] text-stone-400" aria-hidden="true">
                    @foreach (array_unique(array_merge($xLabels, $xLabelsMobile)) as $i)
                        @php($left = $metric['points'][$i]['left'] ?? 0)
                        <span @class(['absolute whitespace-nowrap', 'hidden sm:block' => ! in_array($i, $xLabelsMobile, true), '-translate-x-1/2' => $left > 5 && $left < 95, '-translate-x-full' => $left >= 95]) style="left: {{ $left }}%">{{ $series[$i]['short'] }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
</div>
