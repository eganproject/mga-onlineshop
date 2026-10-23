<?php

namespace App\Http\Controllers\Shop;

use App\Contracts\ShippingProvider;
use App\Data\ShippingRate;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Services\Cart\CartService;
use App\Services\Orders\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly ShippingProvider $shipping,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Keranjang belanja masih kosong.');
        }

        $addresses = $request->user()->addresses()->orderByDesc('is_default')->latest()->get();
        $address = $addresses->firstWhere('id', (int) $request->query('alamat')) ?? $addresses->first();

        [$rates, $shippingError] = $address ? $this->ratesFor($address) : [[], null];

        return view('shop.checkout', [
            'lines' => $this->cart->lines(),
            'subtotal' => $this->cart->subtotal(),
            'weight' => $this->cart->totalWeight(),
            'addresses' => $addresses,
            'address' => $address,
            'rates' => $rates,
            'shippingError' => $shippingError,
        ]);
    }

    public function store(Request $request, CheckoutService $checkout): RedirectResponse
    {
        $data = $request->validate([
            'address_id' => ['required', 'integer'],
            'shipping' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'shipping.required' => 'Pilih layanan pengiriman terlebih dahulu.',
        ]);

        /** @var Address $address */
        $address = $request->user()->addresses()->findOrFail($data['address_id']);

        // Ongkir dihitung ulang di server, tidak pernah dipercaya dari form.
        [$rates] = $this->ratesFor($address);
        $rate = collect($rates)->first(fn (ShippingRate $r) => $r->key() === $data['shipping']);

        if (! $rate) {
            throw ValidationException::withMessages(['shipping' => 'Layanan pengiriman tidak tersedia. Silakan pilih ulang.']);
        }

        $order = $checkout->placeOrder($request->user(), $address, $rate, $data['notes'] ?? null);

        return redirect()->route('account.orders.show', $order)
            ->with('success', 'Pesanan berhasil dibuat. Silakan selesaikan pembayaran.');
    }

    /** @return array{0: list<ShippingRate>, 1: ?string} */
    private function ratesFor(Address $address): array
    {
        if (! $address->destination_id) {
            return [[], 'Alamat ini belum memiliki lokasi tujuan. Edit alamat dan pilih kecamatan dari daftar.'];
        }

        try {
            return [$this->shipping->rates($address->destination_id, $this->cart->totalWeight()), null];
        } catch (Throwable $e) {
            Log::warning('Gagal mengambil ongkir', ['error' => $e->getMessage()]);

            return [[], 'Ongkos kirim sedang tidak bisa dihitung. Coba beberapa saat lagi.'];
        }
    }
}
