@props(['title' => null, 'heading' => null])
@php
    $links = [
        ['Ringkasan', 'account.dashboard', 'dashboard', 'account.dashboard'],
        ['Pesanan saya', 'account.orders.index', 'receipt', 'account.orders.*'],
        ['Alamat', 'account.addresses.index', 'map-pin', 'account.addresses.*'],
        ['Profil & keamanan', 'account.profile.edit', 'user', 'account.profile.*'],
    ];
@endphp
<x-layouts.shop :title="$title">
    @isset($scripts)
        <x-slot:scripts>{{ $scripts }}</x-slot:scripts>
    @endisset
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="grid gap-6 lg:grid-cols-[250px_1fr]">
            <aside class="min-w-0">
                <div class="card p-4">
                    <div class="flex items-center gap-3 p-2">
                        <span class="grid size-11 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-brand-600 text-sm font-bold text-white">{{ auth()->user()->initials() }}</span>
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-stone-900">{{ auth()->user()->name }}</p>
                            <p class="truncate text-xs text-stone-500">{{ auth()->user()->email }}</p>
                        </div>
                    </div>
                    <nav class="-mx-1 mt-3 flex gap-1 overflow-x-auto lg:mx-0 lg:flex-col">
                        @foreach ($links as [$label, $route, $icon, $pattern])
                            @php($active = request()->routeIs($pattern))
                            <a href="{{ route($route) }}" @class(['flex shrink-0 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition', 'bg-brand-50 text-brand-700' => $active, 'text-stone-600 hover:bg-stone-50' => ! $active])>
                                <x-icon :name="$icon" class="size-5" /> {{ $label }}
                            </a>
                        @endforeach
                        <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                            @csrf
                            <button class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-rose-600 hover:bg-rose-50"><x-icon name="logout" class="size-5" /> Keluar</button>
                        </form>
                    </nav>
                </div>
            </aside>
            <section class="min-w-0">
                @if ($heading)
                    <h1 class="mb-5 text-2xl font-extrabold tracking-tight text-stone-900">{{ $heading }}</h1>
                @endif
                {{ $slot }}
            </section>
        </div>
    </div>
</x-layouts.shop>
