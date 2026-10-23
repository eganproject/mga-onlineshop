<x-layouts.account title="Wishlist" heading="Wishlist">
    @if ($products->isEmpty())
        <div class="card">
            <x-empty-state icon="heart" title="Wishlist masih kosong" description="Tekan ikon hati di produk yang kamu suka supaya gampang ditemukan lagi.">
                <a href="{{ route('products.index') }}" class="btn btn-ink rounded-full">Jelajahi produk</a>
            </x-empty-state>
        </div>
    @else
        <p class="-mt-3 mb-6 text-sm text-stone-500">{{ $products->total() }} produk tersimpan.</p>
        <div class="grid grid-cols-2 gap-x-3 gap-y-8 sm:grid-cols-3 sm:gap-x-5 lg:grid-cols-4">
            @foreach ($products as $product)
                <div @class(['opacity-60' => ! $product->is_active])>
                    <x-product-card :product="$product" :quick-add="$product->is_active" />
                    @unless ($product->is_active)
                        <p class="mt-1 text-xs font-medium text-stone-500">Produk tidak lagi dijual.</p>
                    @endunless
                </div>
            @endforeach
        </div>
        <div class="mt-10">{{ $products->links() }}</div>
    @endif
</x-layouts.account>
