<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ShippingUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Activity\ActivityLogger;
use App\Services\Catalog\Wholesale;
use App\Services\Orders\ManualOrderService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Penjualan manual: staf menginput pesanan, cek ongkir, lalu membuat tagihan QRIS/VA. */
class ManualOrderController extends Controller
{
    public function __construct(private readonly ManualOrderService $manual) {}

    public function create(): View
    {
        return view('admin.orders.create', [
            'shippingReady' => (bool) config('shop.shipping.origin_district_id') || config('shop.shipping.driver') === 'fake',
        ]);
    }

    /** Cari produk untuk ditambahkan ke pesanan (nama atau SKU). */
    public function products(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));
        // ids = memulihkan baris setelah validasi gagal.
        $ids = array_filter(array_map('intval', explode(',', (string) $request->query('ids'))));

        $products = Product::query()
            ->with(['variants' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->when($ids, fn ($q) => $q->whereKey($ids))
            ->when(! $ids && $term !== '', function ($q) use ($term) {
                $q->where(function ($q) use ($term) {
                    foreach (preg_split('/\s+/', $term) as $word) {
                        $q->where('name', 'like', "%{$word}%");
                    }
                })->orWhere('sku', 'like', "%{$term}%")
                    ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', "%{$term}%"));
            })
            ->orderByDesc('is_active')->orderBy('name')
            ->limit($ids ? 100 : 20)
            ->get();

        return response()->json(['data' => $products->map(fn (Product $product) => [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'image' => $product->imageUrl(),
            'active' => $product->is_active,
            'has_variants' => $product->has_variants,
            'price' => Wholesale::regularPrice($product),
            'price_label' => Money::rupiah(Wholesale::regularPrice($product)),
            'stock' => $product->stock,
            'wholesale' => (bool) $product->wholesale_min_qty,
            'variants' => $product->variants->map(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'name' => $variant->name,
                'sku' => $variant->sku,
                'active' => $variant->is_active,
                'price' => Wholesale::regularPrice($product, $variant),
                'price_label' => Money::rupiah(Wholesale::regularPrice($product, $variant)),
                'stock' => $variant->stock,
            ])->values(),
        ])]);
    }

    /** Ongkir ke kecamatan tujuan (RajaOngkir), termurah dulu. */
    public function rates(Request $request): JsonResponse
    {
        $data = $request->validate([
            'district_id' => ['required', 'regex:/^\d+$/'],
            'weight' => ['required', 'integer', 'min:1', 'max:1000000'],
        ]);

        try {
            $rates = $this->manual->rates($data['district_id'], (int) $data['weight']);
        } catch (ShippingUnavailableException $e) {
            return response()->json(['data' => [], 'message' => $e->getMessage()], 503);
        }

        return response()->json(['data' => array_map(fn ($rate) => [
            'key' => $rate->key(),
            'courier' => $rate->courierName,
            'service' => $rate->service,
            'description' => $rate->description,
            'cost' => $rate->cost,
            'cost_label' => Money::rupiah($rate->cost),
            'etd' => $rate->etd,
        ], $rates)]);
    }

    /** Pratinjau harga (grosir, voucher, PPN, total) selama form diisi. */
    public function quote(Request $request): JsonResponse
    {
        return response()->json($this->manual->quote($request->validate($this->rules(draft: true))));
    }

    public function store(Request $request, ActivityLogger $activity): RedirectResponse
    {
        $data = $request->validate($this->rules(), $this->messages());

        $order = $this->manual->create($data, $request->user());

        $activity->log('order.manual_created', "Membuat penjualan manual {$order->code}", $order);

        return redirect()->route('admin.orders.show', $order)
            ->with('success', "Penjualan manual {$order->code} tersimpan. Pilih metode bayar untuk membuat QRIS / VA.");
    }

    private function rules(bool $draft = false): array
    {
        $required = $draft ? 'nullable' : 'required';
        $courier = $draft ? 'nullable' : 'required_if:shipping_mode,courier';
        $custom = $draft ? 'nullable' : 'required_if:shipping_mode,custom';

        return [
            'items' => [$required, 'array', 'max:100'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.price' => ['nullable', 'integer', 'min:1', 'max:10000000000'],
            'customer_name' => [$required, 'string', 'max:255'],
            'customer_phone' => [$required, 'string', 'max:20'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'shipping_mode' => [$required, Rule::in([ManualOrderService::MODE_COURIER, ManualOrderService::MODE_CUSTOM, ManualOrderService::MODE_PICKUP])],
            'province_id' => [$courier, 'nullable', 'regex:/^\d+$/'],
            'city_id' => [$courier, 'nullable', 'regex:/^\d+$/'],
            'district_id' => [$courier, 'nullable', 'regex:/^\d+$/'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'address_line' => [$draft ? 'nullable' : 'required_unless:shipping_mode,pickup', 'nullable', 'string', 'max:500'],
            'rate' => [$courier, 'nullable', 'string', 'max:80'],
            'courier_name' => [$custom, 'nullable', 'string', 'max:30'],
            'shipping_cost' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'voucher_code' => ['nullable', 'string', 'max:50'],
            'manual_discount' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function messages(): array
    {
        return [
            'items.required' => 'Tambahkan minimal satu produk.',
            'items.*.price.min' => 'Harga minimal Rp1. Kosongkan untuk memakai harga web.',
            'items.*.price.integer' => 'Harga harus berupa angka.',
            'customer_name.required' => 'Isi nama pembeli.',
            'customer_phone.required' => 'Isi nomor HP pembeli.',
            'district_id.required_if' => 'Pilih kecamatan tujuan.',
            'province_id.required_if' => 'Pilih provinsi tujuan.',
            'city_id.required_if' => 'Pilih kota tujuan.',
            'rate.required_if' => 'Cek ongkir lalu pilih kurir.',
            'courier_name.required_if' => 'Isi nama kurir / cara kirim.',
            'address_line.required_unless' => 'Isi alamat pengiriman.',
        ];
    }
}
