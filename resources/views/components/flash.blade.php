@php
    $messages = collect([
        'success' => session('success'),
        'error' => session('error'),
        'status' => session('status'),
    ])->filter();
@endphp
@if ($messages->isNotEmpty())
    <div class="pointer-events-none fixed inset-x-0 top-4 z-[60] flex flex-col items-center gap-2 px-4 sm:items-end sm:pr-6">
        @foreach ($messages as $type => $message)
            <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show" x-cloak
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-end="opacity-0"
                 role="status"
                 @class([
                     'pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl border bg-white p-4 shadow-lg shadow-stone-900/5',
                     'border-emerald-200' => $type !== 'error',
                     'border-rose-200' => $type === 'error',
                 ])>
                <x-icon :name="$type === 'error' ? 'x-circle' : 'check-circle'"
                        @class(['size-5 shrink-0', 'text-emerald-500' => $type !== 'error', 'text-rose-500' => $type === 'error']) />
                <p class="flex-1 text-sm text-stone-700">{{ $message }}</p>
                <button type="button" @click="show = false" class="text-stone-400 hover:text-stone-600" aria-label="Tutup">
                    <x-icon name="x" class="size-4" />
                </button>
            </div>
        @endforeach
    </div>
@endif
