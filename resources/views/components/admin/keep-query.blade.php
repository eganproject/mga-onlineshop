{{-- Bawa parameter URL (mis. urutan) saat form filter dikirim ulang. --}}
@props(['only' => ['sort']])
@foreach (request()->only($only) as $name => $value)
    @if (is_string($value) && $value !== '')
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endif
@endforeach
