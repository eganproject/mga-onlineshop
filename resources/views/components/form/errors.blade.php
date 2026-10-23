{{-- Ringkasan error validasi di atas form. <x-form.errors /> --}}
@props(['bag' => 'default', 'title' => 'Periksa kembali isian berikut:'])
@php($messages = $errors->getBag($bag)->all())
@if ($messages)
    <div role="alert" {{ $attributes->merge(['class' => 'mb-6 flex gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700']) }}>
        <x-icon name="warning" class="mt-0.5 size-5 shrink-0" />
        <div>
            <p class="font-semibold">{{ $title }}</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5">
                @foreach (array_unique($messages) as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
