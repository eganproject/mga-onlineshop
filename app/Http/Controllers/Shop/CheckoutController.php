<?php

namespace App\Http\Controllers\Shop;

use App\Data\ShippingRate;
use App\Exceptions\ShippingUnavailableException;
use App\Exceptions\VoucherException;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Voucher;
use App\Services\Billing\BillingSettings;
use App\Services\Billing\FeeCalculator;
use App\Services\Cart\CartService;
use App\Services\Orders\CheckoutService;
use App\Services\Promo\VoucherService;
use App\Services\Shipping\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly ShippingService $shipping,
        private readonly VoucherService $vouchers,
    ) {}

    public function index(Request $request, FeeCalculator $fees): View|RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Keranjang belanja masih kosong.');
        }

        $addresses = $request->user()->addresses()->orderByDesc('is_default')->latest()->get();
        $address = $addresses->firstWhere('id', (int) $request->query('alamat', old('address_id'))) ?? $addresses->first();

        if ($this->cart->belowMinimum() !== []) {
            return redirect()->route('cart.index')->with('error', $this->cart->belowMinimum()[0]['message']);
        }

        // Voucher dihitung dari subtotal yang boleh memakai voucher (lihat Pengaturan Toko › Grosir).
        $subtotal = $this->cart->voucherSubtotal();
        [$voucher, $voucherError] = $this->appliedVoucher($request, $subtotal);

        return view('shop.checkout', [
            'lines' => $this->cart->lines(),
            'adjustments' => $this->cart->adjustments(),
            'subtotal' => $this->cart->subtotal(),
            'voucherSubtotal' => $subtotal,
            'wholesaleSavings' => $this->cart->wholesaleSavings(),
            'voucher' => $voucher ? $this->vouchers->payload($voucher, $subtotal) : null,
            'voucherError' => $voucherError,
            'suggestions' => $this->vouchers->suggestions($request->user(), $subtotal),
            'weight' => $this->cart->totalWeight(),
            'addresses' => $addresses,
            'address' => $address,
            'quote' => $address ? $this->quote($address) : ['rates' => [], 'error' => null],
            'billing' => BillingSettings::current(),
            'needsMethodChoice' => $fees->requiresMethodChoice(),
            // Data perusahaan dari pesanan terakhir, supaya tidak perlu diketik ulang.
            'lastBilling' => $request->user()->orders()->whereNotNull('billing_name')->latest('id')
                ->first(['billing_name', 'billing_npwp', 'billing_address', 'billing_email']),
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
        if ($request->filled('billing_npwp')) {
            $request->merge(['billing_npwp' => preg_replace('/\D+/', '', (string) $request->input('billing_npwp'))]);
        }

        $data = $request->validate([
            'address_id' => ['required', 'integer'],
            'shipping' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'company_invoice' => ['boolean'],
            'billing_name' => ['exclude_unless:company_invoice,1', 'required', 'string', 'max:150'],
            'billing_npwp' => ['exclude_unless:company_invoice,1', 'nullable', 'regex:/^\d{15,16}$/'],
            'billing_address' => ['exclude_unless:company_invoice,1', 'nullable', 'string', 'max:500'],
            'billing_email' => ['exclude_unless:company_invoice,1', 'nullable', 'email', 'max:150'],
        ], [
            'address_id.required' => 'Pilih alamat pengiriman terlebih dahulu.',
            'shipping.required' => 'Pilih layanan pengiriman terlebih dahulu.',
            'billing_name.required' => 'Isi nama perusahaan / instansi untuk invoice.',
            'billing_npwp.regex' => 'NPWP berisi 15 atau 16 digit angka.',
        ], [
            'billing_address' => 'alamat penagihan', 'billing_email' => 'email penagihan',
        ]);

        if (! BillingSettings::current()->buyerTaxInfo()) {
            $data = Arr::except($data, ['billing_name', 'billing_npwp', 'billing_address', 'billing_email']);
        }

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

        $order = $checkout->placeOrder(
            $request->user(), $address, $rate, $data['notes'] ?? null, $this->vouchers->appliedCode(), $data,
        );

        return redirect()->route('account.orders.payment', $order)
            ->with('success', 'Pesanan berhasil dibuat. Silakan selesaikan pembayaran.');
    }

    /** Pasang kode voucher. JSON untuk checkout ber-JavaScript, redirect untuk form biasa. */
    public function applyVoucher(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:32']], [
            'code.required' => 'Masukkan kode voucher.',
        ]);

        if ($this->cart->isEmpty()) {
            return $this->voucherError($request, 'Keranjang belanja masih kosong.');
        }

        $subtotal = $this->cart->voucherSubtotal();

        try {
            $voucher = $this->vouchers->resolve($data['code'], $request->user(), $subtotal);
        } catch (VoucherException $e) {
            return $this->voucherError($request, $e->getMessage());
        }

        $this->vouchers->remember($voucher);
        $message = "Voucher {$voucher->code} dipasang: {$voucher->benefitLabel()}.";

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'voucher' => $this->vouchers->payload($voucher, $subtotal)]);
        }

        return back()->with('success', $message);
    }

    public function removeVoucher(Request $request): JsonResponse|RedirectResponse
    {
        $this->vouchers->forget();

        return $request->expectsJson()
            ? response()->json(['message' => 'Voucher dilepas.'])
            : back()->with('success', 'Voucher dilepas.');
    }

    /**
     * Voucher yang sedang dipasang, diperiksa ulang karena isi keranjang,
     * kuota, atau masa berlaku bisa berubah. Voucher yang tidak valid dilepas.
     *
     * @return array{0: ?Voucher, 1: ?string}
     */
    private function appliedVoucher(Request $request, int $subtotal): array
    {
        $code = $this->vouchers->appliedCode();

        if (! $code) {
            return [null, null];
        }

        try {
            return [$this->vouchers->resolve($code, $request->user(), $subtotal), null];
        } catch (VoucherException $e) {
            $this->vouchers->forget();

            return [null, "Voucher {$code} dilepas: {$e->getMessage()}"];
        }
    }

    private function voucherError(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'errors' => ['code' => [$message]]], 422);
        }

        return back()->withErrors(['voucher' => $message])->withInput();
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
