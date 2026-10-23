{{--
    Toast (pesan flash sesi + window.toast() dari JS) dan modal konfirmasi
    bersama (window.confirmDialog / atribut data-confirm). Dipasang sekali di layout dasar.
--}}
@php
    $messages = collect([
        ['success', session('success')],
        ['success', session('status')],
        ['warning', session('warning')],
        ['info', session('info')],
        ['error', session('error')],
    ])->filter(fn ($m) => is_string($m[1]) && $m[1] !== '')->values();

    // Error validasi: ingatkan walau field yang salah ada di bawah layar.
    if ($errors->any() && ! session('error')) {
        $messages->push(['error', $errors->count() > 1 ? 'Ada '.$errors->count().' isian yang perlu diperbaiki. Cek yang ditandai merah.' : $errors->first()]);
    }

    $tones = [
        'success' => ['check-circle', 'text-emerald-500', 'border-emerald-200'],
        'error' => ['x-circle', 'text-rose-500', 'border-rose-200'],
        'warning' => ['warning', 'text-amber-500', 'border-amber-200'],
        'info' => ['info', 'text-sky-500', 'border-sky-200'],
    ];
@endphp
<div x-data x-init="@js($messages).forEach(([type, message], i) => setTimeout(() => $store.toasts.push(message, type, type === 'error' ? 8000 : 5000), i * 120))"
     class="pointer-events-none fixed inset-x-0 top-4 z-[70] flex flex-col items-center gap-2 px-4 sm:items-end sm:pr-6"
     aria-live="polite">
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div x-show="toast.show" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-end="opacity-0"
             :role="toast.type === 'error' ? 'alert' : 'status'"
             class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl border bg-white p-4 shadow-lg shadow-stone-900/10"
             :class="{ @foreach ($tones as $type => [, , $border]) '{{ $border }}': toast.type === '{{ $type }}', @endforeach }">
            @foreach ($tones as $type => [$icon, $color])
                <template x-if="toast.type === '{{ $type }}'"><x-icon :name="$icon" class="size-5 shrink-0 {{ $color }}" /></template>
            @endforeach
            <p class="flex-1 text-sm text-stone-700" x-text="toast.message"></p>
            <button type="button" @click="$store.toasts.dismiss(toast.id)" class="text-stone-400 hover:text-stone-600" aria-label="Tutup">
                <x-icon name="x" class="size-4" />
            </button>
        </div>
    </template>
</div>

{{-- Modal konfirmasi --}}
<div x-data="{
         lastFocus: null,
         locked: false,
         sync(open) {
             if (open && ! this.locked) {
                 this.locked = true;
                 this.lastFocus = document.activeElement;
                 document.documentElement.classList.add('overflow-hidden');
                 this.$nextTick(() => (this.$store.confirm.tone === 'danger' ? this.$refs.cancel : this.$refs.ok).focus());
             } else if (! open && this.locked) {
                 this.locked = false;
                 document.documentElement.classList.remove('overflow-hidden');
                 this.lastFocus?.focus?.();
             }
         },
     }" x-show="$store.confirm.open" x-cloak
     x-effect="sync($store.confirm.open)"
     @keydown.escape.window="$store.confirm.open && $store.confirm.close(false)"
     class="fixed inset-0 z-[80] flex items-end justify-center p-4 sm:items-center"
     role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-message">
    <div x-show="$store.confirm.open" x-transition.opacity @click="$store.confirm.close(false)" class="absolute inset-0 bg-stone-900/50 backdrop-blur-sm"></div>
    <div x-show="$store.confirm.open"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         class="relative w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl">
        <div class="flex gap-4">
            <span class="grid size-11 shrink-0 place-items-center rounded-full"
                  :class="$store.confirm.tone === 'danger' ? 'bg-rose-50 text-rose-600' : 'bg-brand-50 text-brand-600'">
                <template x-if="$store.confirm.tone === 'danger'"><x-icon name="trash" class="size-5" /></template>
                <template x-if="$store.confirm.tone !== 'danger'"><x-icon name="question" class="size-5" /></template>
            </span>
            <div class="min-w-0 flex-1 pt-1">
                <h2 id="confirm-title" class="text-lg font-bold text-stone-900" x-text="$store.confirm.title"></h2>
                <p id="confirm-message" class="mt-1 text-sm leading-relaxed text-stone-600" x-text="$store.confirm.message"></p>
            </div>
        </div>
        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" class="btn btn-secondary" x-ref="cancel" @click="$store.confirm.close(false)" x-text="$store.confirm.cancelLabel">Batal</button>
            <button type="button" class="btn" x-ref="ok"
                    :class="$store.confirm.tone === 'danger' ? 'btn-danger' : 'btn-primary'"
                    @click="$store.confirm.close(true)" x-text="$store.confirm.okLabel">Ya</button>
        </div>
    </div>
</div>
