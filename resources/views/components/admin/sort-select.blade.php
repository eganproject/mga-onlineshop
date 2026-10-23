{{-- Pilihan urutan untuk daftar berbentuk kartu/list (tanpa judul kolom). Taruh di dalam form filter GET. --}}
@props(['options' => [], 'default' => null])
@php($current = (string) request()->attributes->get('admin_sort', $default))
<select name="sort" onchange="this.form.submit()" aria-label="Urutkan" {{ $attributes->merge(['class' => 'input']) }}>
    @foreach ($options as $value => $label)
        <option value="{{ $value }}" @selected($current === $value)>{{ $label }}</option>
    @endforeach
</select>
