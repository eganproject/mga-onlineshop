<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Cart\CartService;
use App\Services\Catalog\Wholesale;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Keranjang. Tiap aksi bisa dipanggil lewat form biasa (redirect + pesan flash)
 * atau fetch dengan header Accept: application/json (tanpa reload, lihat
 * resources/js/components/cart.js).
 */
class CartController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    public function index(): View
    {
        return view('shop.cart', $this->cartData());
    }

    public function store(Request $request, Product $product): RedirectResponse|JsonResponse
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
                return $this->respond($request, 'error', "Pilih {$this->variantLabel($product)} terlebih dahulu.", status: 422, withInput: true);
            }
        }

        if (! ($variant ?? $product)->inStock()) {
            return $this->respond($request, 'error', 'Maaf, stok produk ini sedang habis.', status: 422);
        }

        $requested = max(1, (int) ($data['quantity'] ?? 1));
        $before = $this->cart->quantity(CartService::key($product->id, $variant?->id));
        $quantity = $this->cart->add($product, $variant, $requested);
        $added = max(0, $quantity - $before);

        if ($request->boolean('buy_now')) {
            return $request->expectsJson()
                ? response()->json(['redirect' => route('checkout.index')])
                : redirect()->route('checkout.index');
        }

        $name = $variant ? "{$product->name} ({$variant->name})" : $product->name;
        // Pesan menyebut jumlah yang BARU ditambahkan; total di keranjang hanya sebagai keterangan.
        $total = $before > 0 ? " Total di keranjang sekarang {$quantity} pcs." : '';

        [$type, $message] = match (true) {
            $added === 0 => ['warning', "Stok {$name} hanya {$quantity} pcs dan semuanya sudah ada di keranjang."],
            $added < $requested => ['warning', "Hanya {$added} pcs {$name} yang bisa ditambahkan karena stok terbatas.{$total}"],
            $added > $requested => ['success', "{$added} pcs {$name} ditambahkan ke keranjang (minimal pembelian {$product->minOrderQty()} pcs).{$total}"],
            default => ['success', "{$added} pcs {$name} ditambahkan ke keranjang.{$total}"],
        };

        $tier = Wholesale::tierFor(Wholesale::tiers($product, $variant), $this->cart->productQuantity($product->id));
        if ($added > 0 && $tier) {
            $message .= ' Harga grosir '.Money::rupiah($tier['price']).'/pcs berlaku.';
        }

        return $this->respond($request, $type, $message, ['added' => $added, 'quantity' => $quantity]);
    }

    public function update(Request $request, string $line): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:999']]);

        $cartLine = $this->cart->find($line);
        abort_unless($cartLine, 404);

        $final = $this->cart->update($line, $data['quantity']);

        if ($final < $data['quantity']) {
            return $this->respond($request, 'error', "Stok {$cartLine->name()} hanya tersisa {$cartLine->availableStock()} pcs.", withCart: true);
        }

        if ($final === 0) {
            return $this->respond($request, 'success', "{$cartLine->name()} dihapus dari keranjang.", withCart: true);
        }

        return $this->respond($request, null, null, withCart: true);
    }

    public function destroy(Request $request, string $line): RedirectResponse|JsonResponse
    {
        $cartLine = $this->cart->find($line);
        $this->cart->remove($line);

        return $this->respond($request, 'success', ($cartLine?->name() ?? 'Produk').' dihapus dari keranjang.', withCart: true);
    }

    /**
     * Balasan untuk form biasa (kembali + flash) atau fetch (JSON berisi pesan,
     * jumlah keranjang terbaru, dan, untuk halaman keranjang, isi keranjang baru).
     */
    private function respond(Request $request, ?string $type, ?string $message, array $extra = [], int $status = 200, bool $withInput = false, bool $withCart = false): RedirectResponse|JsonResponse
    {
        if (! $request->expectsJson()) {
            $back = back();

            if ($withInput) {
                $back->withInput();
            }

            return $type ? $back->with($type, $message) : $back;
        }

        $payload = ['type' => $type, 'message' => $message, ...$extra, 'cart_count' => $this->cart->count()];

        if ($withCart) {
            $payload['html'] = view('shop.partials.cart-content', $this->cartData())->render();
            // Isi keranjang dibaca ulang di atas, jadi jumlahnya bisa berubah (stok habis dsb.).
            $payload['cart_count'] = $this->cart->count();
        }

        return response()->json($payload, $status);
    }

    private function cartData(): array
    {
        return [
            'lines' => $this->cart->lines(),
            'adjustments' => $this->cart->adjustments(),
            'subtotal' => $this->cart->subtotal(),
            'weight' => $this->cart->totalWeight(),
            'wholesaleSavings' => $this->cart->wholesaleSavings(),
            'belowMinimum' => $this->cart->belowMinimum(),
        ];
    }

    private function variantLabel(Product $product): string
    {
        return mb_strtolower($product->variant_label ?: 'varian');
    }
}
