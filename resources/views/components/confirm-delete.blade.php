@props(['action', 'message' => 'Yakin ingin menghapus data ini?', 'label' => 'Hapus'])
<form method="POST" action="{{ $action }}" onsubmit="return confirm(@js($message))" {{ $attributes }}>
    @csrf
    @method('DELETE')
    {{ $slot->isEmpty() ? '' : $slot }}
    @if ($slot->isEmpty())
        <button class="btn btn-ghost btn-sm text-rose-600 hover:bg-rose-50 hover:text-rose-700" title="{{ $label }}">
            <x-icon name="trash" class="size-4" /> <span class="sr-only sm:not-sr-only">{{ $label }}</span>
        </button>
    @endif
</form>
