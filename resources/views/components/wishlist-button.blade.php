@props(['product', 'variant' => 'icon'])
{{--
    Tombol simpan ke wishlist. variant="icon" (bulat, untuk kartu produk) atau
    "button" (tombol penuh di halaman produk). Tamu diarahkan ke halaman masuk.
--}}
@php
    $user = auth()->user();
    $on = $user?->isCustomer() && app(\App\Services\Customer\WishlistService::class)->has($product);
    $iconClasses = $variant === 'icon' ? 'size-[18px]' : 'size-5';
    $buttonClasses = $variant === 'icon'
        ? 'grid size-9 place-items-center rounded-full bg-white/90 text-ink shadow-sm transition hover:scale-105 hover:bg-white'
        : 'btn btn-outline btn-pill w-full py-4 text-[15px]';
@endphp
@if ($user?->isAdmin())
    {{-- Admin tidak punya wishlist. --}}
@elseif ($user)
    <form method="POST" action="{{ route('wishlist.toggle', $product) }}" x-data="wishlistButton(@js($on))" @submit.prevent="toggle($el)" {{ $attributes }}>
        @csrf
        <button type="submit" class="{{ $buttonClasses }}" :aria-pressed="on.toString()" aria-pressed="{{ $on ? 'true' : 'false' }}"
                :aria-label="on ? 'Hapus dari wishlist' : 'Simpan ke wishlist'" aria-label="{{ $on ? 'Hapus dari wishlist' : 'Simpan ke wishlist' }}">
            <x-icon name="heart" class="{{ $iconClasses }} transition {{ $on ? 'fill-brand-600 text-brand-600' : '' }}" x-bind:class="{ 'fill-brand-600 text-brand-600': on }" />
            @if ($variant === 'button')
                <span x-text="on ? 'Tersimpan di wishlist' : 'Simpan ke wishlist'">{{ $on ? 'Tersimpan di wishlist' : 'Simpan ke wishlist' }}</span>
            @endif
        </button>
    </form>
@else
    <a href="{{ route('login') }}" aria-label="Masuk untuk menyimpan ke wishlist" {{ $attributes->class([$buttonClasses]) }}>
        <x-icon name="heart" class="{{ $iconClasses }}" />
        @if ($variant === 'button')<span>Simpan ke wishlist</span>@endif
    </a>
@endif
