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
            'subtotal' => $this->cart->subtotal(),
        ]);
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->is_active, 404);

        if (! $product->inStock()) {
            return back()->with('error', 'Maaf, stok produk ini sedang habis.');
        }

        $data = $request->validate(['quantity' => ['nullable', 'integer', 'min:1', 'max:999']]);
        $this->cart->add($product, (int) ($data['quantity'] ?? 1));

        if ($request->boolean('buy_now')) {
            return redirect()->route('checkout.index');
        }

        return back()->with('success', "{$product->name} ditambahkan ke keranjang.");
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:999']]);
        $final = $this->cart->update($product, $data['quantity']);

        if ($final < $data['quantity']) {
            return back()->with('error', "Stok {$product->name} hanya tersisa {$product->stock}.");
        }

        return back();
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->cart->remove($product);

        return back()->with('success', "{$product->name} dihapus dari keranjang.");
    }
}
