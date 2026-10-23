<x-layouts.account title="Alamat" heading="Alamat pengiriman">
    <div class="mb-4 flex justify-end">
        <a href="{{ route('account.addresses.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Tambah alamat</a>
    </div>
    <div class="grid gap-4 md:grid-cols-2">
        @forelse ($addresses as $address)
            <div @class(['card flex flex-col p-5', 'ring-2 ring-brand-500' => $address->is_default])>
                <div class="flex items-center gap-2">
                    <p class="font-bold text-stone-900">{{ $address->label }}</p>
                    @if ($address->is_default)<span class="badge bg-brand-50 text-brand-700 ring-brand-600/20">Utama</span>@endif
                </div>
                <p class="mt-2 text-sm font-semibold text-stone-800">{{ $address->recipient_name }}</p>
                <p class="text-sm text-stone-600">{{ $address->phone }}</p>
                <p class="mt-1 flex-1 text-sm text-stone-500">{{ $address->fullAddress() }}</p>
                <div class="mt-4 flex flex-wrap items-center gap-1 border-t border-stone-100 pt-3">
                    <a href="{{ route('account.addresses.edit', $address) }}" class="btn btn-ghost btn-sm"><x-icon name="pencil" class="size-4" /> Edit</a>
                    @unless ($address->is_default)
                        <form method="POST" action="{{ route('account.addresses.default', $address) }}">
                            @csrf
                            @method('PATCH')
                            <button class="btn btn-ghost btn-sm"><x-icon name="check" class="size-4" /> Jadikan utama</button>
                        </form>
                    @endunless
                    <x-confirm-delete :action="route('account.addresses.destroy', $address)" message="Hapus alamat ini?" class="ml-auto" />
                </div>
            </div>
        @empty
            <div class="card md:col-span-2">
                <x-empty-state icon="map-pin" title="Belum ada alamat" description="Tambahkan alamat agar checkout lebih cepat.">
                    <a href="{{ route('account.addresses.create') }}" class="btn btn-primary">Tambah alamat</a>
                </x-empty-state>
            </div>
        @endforelse
    </div>
</x-layouts.account>
