<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Cart\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    public function index(): View
    {
        return view('shop.cart', [
            'lines' => $this->cart->lines(),
            'adjustments' => $this->cart->adjustments(),
            'subtotal' => $this->cart->subtotal(),
            'weight' => $this->cart->totalWeight(),
        ]);
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->is_active, 404);

        $data = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'variant_id' => ['nullable', 'integer'],
        ]);

        $variant = null;

        if ($product->has_variants) {
            $variant = $product->activeVariants()->find($data['variant_id'] ?? 0);

            if (! $variant) {
                return back()->withInput()->with('error', "Pilih {$this->variantLabel($product)} terlebih dahulu.");
            }
        }

        if (! ($variant ?? $product)->inStock()) {
            return back()->with('error', 'Maaf, stok produk ini sedang habis.');
        }

        $this->cart->add($product, $variant, (int) ($data['quantity'] ?? 1));

        if ($request->boolean('buy_now')) {
            return redirect()->route('checkout.index');
        }

        $name = $variant ? "{$product->name} ({$variant->name})" : $product->name;

        return back()->with('success', "{$name} ditambahkan ke keranjang.");
    }

    public function update(Request $request, string $line): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:999']]);

        $cartLine = $this->cart->find($line);
        abort_unless($cartLine, 404);

        $final = $this->cart->update($line, $data['quantity']);

        if ($final < $data['quantity']) {
            return back()->with('error', "Stok {$cartLine->name()} hanya tersisa {$cartLine->availableStock()}.");
        }

        return back();
    }

    public function destroy(string $line): RedirectResponse
    {
        $cartLine = $this->cart->find($line);
        $this->cart->remove($line);

        return back()->with('success', ($cartLine?->name() ?? 'Produk').' dihapus dari keranjang.');
    }

    private function variantLabel(Product $product): string
    {
        return mb_strtolower($product->variant_label ?: 'varian');
    }
}
