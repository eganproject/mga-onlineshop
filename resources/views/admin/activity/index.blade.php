@php
    $groupTone = [
        'auth' => 'bg-stone-100 text-stone-600', 'orders' => 'bg-sky-50 text-sky-600', 'products' => 'bg-brand-50 text-brand-600',
        'categories' => 'bg-brand-50 text-brand-600', 'stock' => 'bg-amber-50 text-amber-600', 'vouchers' => 'bg-rose-50 text-rose-600', 'banners' => 'bg-rose-50 text-rose-600', 'reviews' => 'bg-violet-50 text-violet-600',
        'customers' => 'bg-emerald-50 text-emerald-600', 'settings' => 'bg-stone-900 text-white', 'staff' => 'bg-stone-900 text-white',
        'articles' => 'bg-sky-50 text-sky-600', 'article-categories' => 'bg-sky-50 text-sky-600', 'storefront' => 'bg-rose-50 text-rose-600',
        'imports' => 'bg-brand-50 text-brand-600', 'integrations' => 'bg-stone-900 text-white',
    ];
    $groupIcon = [
        'auth' => 'lock', 'orders' => 'receipt', 'products' => 'cube', 'categories' => 'tag', 'stock' => 'archive', 'vouchers' => 'ticket', 'banners' => 'megaphone',
        'reviews' => 'star', 'customers' => 'users', 'settings' => 'cog', 'staff' => 'shield', 'profile' => 'user',
        'articles' => 'newspaper', 'article-categories' => 'list-bullet', 'storefront' => 'photo', 'imports' => 'download', 'integrations' => 'link',
    ];
    $format = function ($value) use (&$format) {
        if (is_array($value)) {
            return array_is_list($value) && collect($value)->every(fn ($v) => is_scalar($v) || $v === null)
                ? implode(', ', array_map(fn ($v) => $v ?? '—', $value))
                : null;
        }

        return match (true) { $value === null || $value === '' => '—', is_bool($value) => $value ? 'Ya' : 'Tidak', default => (string) $value };
    };
@endphp
<x-layouts.admin title="Log Aktivitas">
    <x-admin.page-header title="Log Aktivitas" description="Catatan perubahan yang dilakukan staf di panel admin, terbaru di atas." />

    <div class="card overflow-hidden">
        <form method="GET" class="grid grid-cols-1 gap-3 border-b border-stone-100 p-4 sm:grid-cols-2 lg:grid-cols-5" x-data @change="$el.submit()">
            <div class="relative lg:col-span-1">
                <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-stone-400" />
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari aksi / data..." class="input pl-10">
            </div>
            <select name="staf" class="input" x-data x-searchable aria-label="Filter staf">
                <option value="">Semua staf</option>
                @foreach ($staff as $member)
                    <option value="{{ $member->id }}" @selected(request('staf') == $member->id)>{{ $member->name }}</option>
                @endforeach
            </select>
            <select name="grup" class="input" x-data x-searchable aria-label="Filter jenis">
                <option value="">Semua jenis</option>
                @foreach ($groups as $key => $label)
                    <option value="{{ $key }}" @selected(request('grup') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <x-form.date-range from="from" to="to" :from-value="request('from')" :to-value="request('to')" :max="today()" aria-label="Rentang tanggal" wrapper-class="sm:col-span-2" />
        </form>

        @if ($logs->isEmpty())
            <x-empty-state icon="clock" title="Belum ada aktivitas" description="Aktivitas staf akan tercatat di sini." />
        @else
            <ul class="divide-y divide-stone-100">
                @foreach ($logs as $log)
                    @php($group = $log->group())
                    <li class="flex gap-3 p-4" x-data="{ open: false }">
                        <span class="grid size-9 shrink-0 place-items-center rounded-xl {{ $groupTone[$group] ?? 'bg-stone-100 text-stone-600' }}">
                            <x-icon :name="$groupIcon[$group] ?? 'pencil'" class="size-4" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-col gap-0.5 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
                                <p class="text-sm text-stone-700">
                                    <span class="font-semibold text-stone-900">{{ $log->user?->name ?? $log->user_name ?? 'Sistem' }}</span>
                                    {{ \Illuminate\Support\Str::lcfirst($log->description) }}
                                    @if ($log->subject_label)
                                        <span class="font-semibold text-stone-900">{{ $log->subject_label }}</span>
                                    @endif
                                </p>
                                <time datetime="{{ $log->created_at->toIso8601String() }}" title="{{ $log->created_at->translatedFormat('d M Y, H:i:s') }}" class="shrink-0 text-xs text-stone-400">
                                    {{ $log->created_at->translatedFormat('d M Y, H:i') }}
                                </time>
                            </div>
                            <p class="mt-0.5 text-xs text-stone-400">
                                <span class="font-mono">{{ $log->action }}</span>@if ($log->ip_address) · IP {{ $log->ip_address }}@endif
                                @if ($log->properties)
                                    · <button type="button" @click="open = !open" class="font-semibold text-brand-600 hover:text-brand-700" x-text="open ? 'Sembunyikan detail' : 'Lihat detail'">Lihat detail</button>
                                @endif
                            </p>

                            @if ($log->properties)
                                <div x-show="open" x-cloak class="mt-3 overflow-x-auto rounded-xl bg-stone-50 p-3 text-xs">
                                    @if (isset($log->properties['perubahan']) && is_array($log->properties['perubahan']))
                                        <table class="w-full">
                                            <thead class="text-left text-stone-500"><tr><th class="pb-1 pr-3 font-semibold">Pengaturan</th><th class="pb-1 pr-3 font-semibold">Sebelum</th><th class="pb-1 font-semibold">Sesudah</th></tr></thead>
                                            <tbody>
                                                @foreach ($log->properties['perubahan'] as $label => $pair)
                                                    <tr class="align-top"><td class="py-0.5 pr-3 font-medium text-stone-700">{{ $label }}</td><td class="py-0.5 pr-3 text-stone-500 line-through decoration-stone-300">{{ $format($pair['dari'] ?? null) }}</td><td class="py-0.5 text-stone-800">{{ $format($pair['menjadi'] ?? null) }}</td></tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    @else
                                        <dl class="grid grid-cols-1 gap-x-4 gap-y-1 sm:grid-cols-[max-content_1fr]">
                                            @foreach ($log->properties as $key => $value)
                                                <dt class="font-medium text-stone-500">{{ \Illuminate\Support\Str::headline((string) $key) }}</dt>
                                                <dd class="break-words text-stone-800">
                                                    @if (($text = $format($value)) !== null)
                                                        {{ $text }}
                                                    @else
                                                        <code class="whitespace-pre-wrap font-mono text-[11px]">{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</code>
                                                    @endif
                                                </dd>
                                            @endforeach
                                        </dl>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            <x-admin.pagination :paginator="$logs" label="aktivitas" />
        @endif
    </div>
</x-layouts.admin>
