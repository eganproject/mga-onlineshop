<?php

namespace App\Http\Controllers\Shop;

use App\Data\ShippingRate;
use App\Exceptions\ShippingUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Services\Cart\CartService;
use App\Services\Orders\CheckoutService;
use App\Services\Shipping\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly ShippingService $shipping,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Keranjang belanja masih kosong.');
        }

        $addresses = $request->user()->addresses()->orderByDesc('is_default')->latest()->get();
        $address = $addresses->firstWhere('id', (int) $request->query('alamat', old('address_id'))) ?? $addresses->first();

        return view('shop.checkout', [
            'lines' => $this->cart->lines(),
            'adjustments' => $this->cart->adjustments(),
            'subtotal' => $this->cart->subtotal(),
            'weight' => $this->cart->totalWeight(),
            'addresses' => $addresses,
            'address' => $address,
            'quote' => $address ? $this->quote($address) : ['rates' => [], 'error' => null],
        ]);
    }

    /** Ongkir untuk alamat terpilih (JSON), dipanggil saat pelanggan mengganti alamat. */
    public function rates(Request $request): JsonResponse
    {
        $data = $request->validate(['address_id' => ['required', 'integer']]);
        $address = $request->user()->addresses()->findOrFail($data['address_id']);

        if ($this->cart->isEmpty()) {
            return response()->json(['rates' => [], 'error' => 'Keranjang belanja masih kosong.'], 422);
        }

        return response()->json($this->quote($address));
    }

    public function store(Request $request, CheckoutService $checkout): RedirectResponse
    {
        $data = $request->validate([
            'address_id' => ['required', 'integer'],
            'shipping' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'address_id.required' => 'Pilih alamat pengiriman terlebih dahulu.',
            'shipping.required' => 'Pilih layanan pengiriman terlebih dahulu.',
        ]);

        /** @var Address $address */
        $address = $request->user()->addresses()->findOrFail($data['address_id']);

        // Ongkir dihitung ulang di server, tidak pernah dipercaya dari form.
        try {
            $rate = $this->shipping->findRate($address, $this->cart->totalWeight(), $data['shipping']);
        } catch (ShippingUnavailableException $e) {
            throw ValidationException::withMessages(['shipping' => $e->getMessage()]);
        }

        if (! $rate) {
            throw ValidationException::withMessages(['shipping' => 'Layanan pengiriman tidak tersedia. Silakan pilih ulang.']);
        }

        $order = $checkout->placeOrder($request->user(), $address, $rate, $data['notes'] ?? null);

        return redirect()->route('account.orders.show', $order)
            ->with('success', 'Pesanan berhasil dibuat. Silakan selesaikan pembayaran.');
    }

    /** @return array{rates: list<ShippingRate>, error: ?string} */
    private function quote(Address $address): array
    {
        try {
            return ['rates' => $this->shipping->rates($address, $this->cart->totalWeight()), 'error' => null];
        } catch (ShippingUnavailableException $e) {
            return ['rates' => [], 'error' => $e->getMessage()];
        }
    }
}
