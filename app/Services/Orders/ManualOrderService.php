<?php

namespace App\Services\Orders;

use App\Data\ShippingRate;
use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Enums\UserRole;
use App\Exceptions\ShippingUnavailableException;
use App\Exceptions\VoucherException;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Billing\PriceCalculator;
use App\Services\Catalog\StockService;
use App\Services\Catalog\Wholesale;
use App\Services\Promo\VoucherService;
use App\Services\Shipping\ShippingService;
use App\Support\OrderSummary;
use App\Support\ShippingConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Penjualan manual: pesanan yang diinput staf di admin (jualan langsung,
 * WhatsApp, marketplace, dll). Harga grosir, voucher, PPN & biaya layanan
 * dihitung dengan aturan yang sama seperti checkout website. Stok langsung
 * dipotong dan kembali otomatis bila pesanan batal/kedaluwarsa.
 *
 * Data masukan (sudah divalidasi Admin\ManualOrderController):
 *   items[]: product_id, variant_id?, quantity, price? (kosong = harga web)
 *   customer_name, customer_phone, customer_email?
 *   shipping_mode: courier | custom | pickup
 *     courier: province_id, city_id, district_id, postal_code?, address_line, rate (mis. "jne|REG")
 *     custom: address_line, courier_name, shipping_cost
 *   voucher_code?, manual_discount?, notes?
 */
class ManualOrderService
{
    public const MODE_COURIER = 'courier';

    public const MODE_CUSTOM = 'custom';

    public const MODE_PICKUP = 'pickup';

    public function __construct(
        private readonly StockService $stock,
        private readonly VoucherService $vouchers,
        private readonly PriceCalculator $prices,
        private readonly ShippingService $shipping,
    ) {}

    /** Akun pelanggan yang cocok dengan email (pesanan muncul di akunnya). */
    public function findCustomer(?string $email): ?User
    {
        $email = trim((string) $email);

        return $email === '' ? null : User::where('role', UserRole::Customer)->where('email', $email)->first();
    }

    /**
     * Pratinjau rincian untuk form (tanpa mengunci/mengubah data). Masalah
     * voucher & stok dikembalikan sebagai pesan, bukan exception.
     *
     * @return array<string, mixed>
     */
    public function quote(array $data): array
    {
        $items = collect($data['items'] ?? [])->filter(fn ($i) => ! empty($i['product_id']) && (int) ($i['quantity'] ?? 0) > 0)->values();
        $products = Product::with('variants')->whereKey($items->pluck('product_id')->unique())->get()->keyBy('id');
        $variants = $products->flatMap->variants->keyBy('id');

        $priced = $this->price($items, $products, $variants, strict: false);
        $customer = $this->findCustomer($data['customer_email'] ?? null);

        $shippingCost = $this->shippingCostForQuote($data, $priced['weight']);
        $voucherError = null;
        $discounts = ['discount' => 0, 'shipping_discount' => 0];

        if (Voucher::normalizeCode($data['voucher_code'] ?? null) !== '') {
            try {
                $voucher = $this->vouchers->find($data['voucher_code']) ?? throw new VoucherException('Kode voucher tidak ditemukan.');
                $this->vouchers->check($voucher, $customer, $priced['voucher_subtotal']);
                $discounts = $this->vouchers->calculate($voucher, $priced['voucher_subtotal'], $shippingCost);
            } catch (VoucherException $e) {
                $voucherError = $e->getMessage();
            }
        }

        $manual = $this->manualDiscount($data, $priced['subtotal'] - $discounts['discount']);
        $charges = $this->prices->calculate($priced['subtotal'], $discounts['discount'] + $manual, $shippingCost, $discounts['shipping_discount']);

        $draft = new Order([
            'subtotal' => $priced['subtotal'],
            'shipping_cost' => $shippingCost,
            'discount' => $discounts['discount'],
            'shipping_discount' => $discounts['shipping_discount'],
            'manual_discount' => $manual,
            'voucher_code' => $voucherError ? null : (Voucher::normalizeCode($data['voucher_code'] ?? null) ?: null),
            ...$this->courierForQuote($data),
            ...$charges,
        ]);

        return [
            'lines' => $priced['lines'],
            'problems' => $priced['problems'],
            'weight' => $priced['weight'],
            'customer' => $customer ? ['name' => $customer->name, 'email' => $customer->email] : null,
            'voucher_error' => $voucherError,
            'discount' => $discounts['discount'],
            'shipping_discount' => $discounts['shipping_discount'],
            'manual_discount' => $manual,
            'shipping_cost' => $shippingCost,
            'total' => $charges['total'],
            'rows' => collect(OrderSummary::rows($draft))
                ->map(fn ($row) => ['label' => $row['label'], 'amount' => OrderSummary::format($row), 'tone' => $row['tone'], 'note' => $row['note'] ?? null])
                ->values()->all(),
        ];
    }

    /**
     * Ongkir ke kecamatan tujuan untuk berat tertentu (gram).
     *
     * @return list<ShippingRate>
     *
     * @throws ShippingUnavailableException
     */
    public function rates(string $districtId, int $weight): array
    {
        return $this->shipping->rates($this->destination($districtId), $weight);
    }

    /** Simpan penjualan manual. Stok dikunci & dipotong dalam satu transaksi. */
    public function create(array $data, User $staff): Order
    {
        $items = collect($data['items'])->values();
        $customer = $this->findCustomer($data['customer_email'] ?? null);

        // Alamat & tarif dicek sebelum transaksi (memanggil API ongkir).
        $shipping = $this->resolveShipping($data, $this->estimateWeight($items));

        return DB::transaction(function () use ($data, $items, $customer, $staff, $shipping) {
            $products = Product::whereKey($items->pluck('product_id')->unique())->lockForUpdate()->get()->keyBy('id');
            $variants = ProductVariant::whereKey($items->pluck('variant_id')->filter())->lockForUpdate()->get()->keyBy('id');

            $priced = $this->price($items, $products, $variants, strict: true);

            [$voucher, $discounts] = $this->applyVoucher($data['voucher_code'] ?? null, $customer, $priced['voucher_subtotal'], $shipping['cost']);
            $manual = $this->manualDiscount($data, $priced['subtotal'] - $discounts['discount']);

            $charges = $this->prices->calculate($priced['subtotal'], $discounts['discount'] + $manual, $shipping['cost'], $discounts['shipping_discount']);

            $order = new Order([
                'code' => Order::generateCode(),
                'status' => OrderStatus::PendingPayment,
                'source' => Order::SOURCE_MANUAL,
                'user_id' => $customer?->id,
                'subtotal' => $priced['subtotal'],
                'shipping_cost' => $shipping['cost'],
                'discount' => $discounts['discount'],
                'shipping_discount' => $discounts['shipping_discount'],
                'manual_discount' => $manual,
                ...$charges,
                'billing_email' => $customer ? null : (filled($data['customer_email'] ?? null) ? trim($data['customer_email']) : null),
                'voucher_id' => $voucher?->id,
                'voucher_code' => $voucher?->code,
                'total_weight' => $priced['weight'],
                'recipient_name' => trim($data['customer_name']),
                'recipient_phone' => trim($data['customer_phone']),
                'shipping_address' => $shipping['address'],
                'destination_id' => $shipping['district_id'],
                'destination_postal_code' => preg_match('/^\d{5}$/', (string) ($data['postal_code'] ?? '')) ? $data['postal_code'] : null,
                'courier' => $shipping['courier'],
                'courier_service' => $shipping['service'],
                'shipping_etd' => $shipping['etd'],
                'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
                'payment_due_at' => now()->addHours((int) config('shop.payment.expiry_hours')),
            ]);
            $order->forceFill(['created_by' => $staff->id])->save();

            $order->items()->createMany(array_column($priced['rows'], 'attributes'));

            foreach ($priced['rows'] as $row) {
                $this->stock->adjust($row['product'], $row['variant'], -$row['quantity'], StockMovementType::Sale, 'Penjualan manual', order: $order);
            }

            return $order;
        });
    }

    /**
     * Hitung harga tiap baris (harga grosir dari jumlah gabungan per produk).
     * strict = lempar ValidationException bila ada masalah (saat menyimpan).
     *
     * @param  Collection<int, array>  $items
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, ProductVariant>  $variants
     */
    private function price(Collection $items, Collection $products, Collection $variants, bool $strict): array
    {
        $subtotal = 0;
        $voucherSubtotal = 0;
        $weight = 0;
        $rows = [];
        $lines = [];
        $problems = [];

        $productTotals = $items->groupBy(fn ($i) => (int) $i['product_id'])->map(fn ($g) => $g->sum(fn ($i) => (int) $i['quantity']));

        foreach ($items as $index => $item) {
            $product = $products->get((int) $item['product_id']);
            $variant = filled($item['variant_id'] ?? null) ? $variants->get((int) $item['variant_id']) : null;
            $quantity = (int) $item['quantity'];
            $problem = null;

            if (! $product) {
                $problem = 'Produk tidak ditemukan.';
            } elseif ($product->has_variants && (! $variant || $variant->product_id !== $product->id)) {
                $problem = "Pilih varian {$product->name}.";
            } elseif (! $product->has_variants && $variant) {
                $problem = "{$product->name} tidak punya varian.";
            } elseif (Wholesale::regularPrice($product, $variant) <= 0) {
                $problem = "{$product->name} belum punya harga.";
            } else {
                $available = $variant ? $variant->stock : $product->stock;
                // Baris lain untuk barang yang sama ikut dihitung.
                $sameItem = $items->filter(fn ($i) => (int) $i['product_id'] === $product->id && (int) ($i['variant_id'] ?? 0) === (int) $variant?->id)->sum(fn ($i) => (int) $i['quantity']);

                if ($available < $sameItem) {
                    $problem = 'Stok '.$this->label($product, $variant)." tinggal {$available} pcs.";
                }
            }

            if ($problem) {
                if ($strict) {
                    throw ValidationException::withMessages(["items.{$index}.product_id" => $problem]);
                }
                $problems[$index] = $problem;
                $lines[$index] = null;

                continue;
            }

            $regularPrice = Wholesale::regularPrice($product, $variant);
            $webPrice = Wholesale::unitPrice($product, $variant, (int) $productTotals[$product->id]);
            $custom = filled($item['price'] ?? null) && (int) $item['price'] > 0 && (int) $item['price'] !== $webPrice;
            // Harga diubah staf: berlaku apa adanya (bukan grosir), harga web disimpan untuk laporan.
            $price = $custom ? (int) $item['price'] : $webPrice;
            $listPrice = $custom ? $webPrice : null;
            $isWholesale = ! $custom && $price < $regularPrice;
            $unitWeight = $variant?->weight ?: $product->weight;
            $lineSubtotal = $price * $quantity;

            $subtotal += $lineSubtotal;
            $weight += $unitWeight * $quantity;
            if (! $isWholesale || Wholesale::voucherCombinable()) {
                $voucherSubtotal += $lineSubtotal;
            }

            $lines[$index] = ['price' => $price, 'web_price' => $webPrice, 'custom' => $custom, 'regular_price' => $isWholesale ? $regularPrice : null, 'subtotal' => $lineSubtotal];
            $rows[] = [
                'product' => $product,
                'variant' => $variant,
                'quantity' => $quantity,
                'attributes' => [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'product_name' => $product->name,
                    'variant_name' => $variant?->name,
                    'sku' => $variant?->sku ?: $product->sku,
                    'price' => $price,
                    'regular_price' => $isWholesale ? $regularPrice : null,
                    'list_price' => $listPrice,
                    'quantity' => $quantity,
                    'weight' => $unitWeight,
                    'subtotal' => $lineSubtotal,
                ],
            ];
        }

        if ($strict && $rows === []) {
            throw ValidationException::withMessages(['items' => 'Tambahkan minimal satu produk.']);
        }

        return [
            'subtotal' => $subtotal,
            'voucher_subtotal' => $voucherSubtotal,
            'weight' => $weight,
            'rows' => $rows,
            'lines' => $lines,
            'problems' => $problems,
        ];
    }

    /** Berat untuk cek ongkir sebelum transaksi (tanpa kunci). */
    private function estimateWeight(Collection $items): int
    {
        $products = Product::with('variants')->whereKey($items->pluck('product_id')->unique())->get()->keyBy('id');

        return (int) $items->sum(function ($item) use ($products) {
            $product = $products->get((int) $item['product_id']);
            $variant = $product?->variants->firstWhere('id', (int) ($item['variant_id'] ?? 0));

            return ($variant?->weight ?: $product?->weight ?: 0) * (int) $item['quantity'];
        });
    }

    /**
     * @return array{cost:int, address:string, district_id:?string, courier:?string, service:?string, etd:?string}
     */
    private function resolveShipping(array $data, int $weight): array
    {
        return match ($data['shipping_mode']) {
            self::MODE_PICKUP => [
                'cost' => 0, 'address' => 'Ambil di toko', 'district_id' => null,
                'courier' => null, 'service' => null, 'etd' => null,
            ],
            self::MODE_CUSTOM => [
                'cost' => (int) ($data['shipping_cost'] ?? 0),
                'address' => trim($data['address_line']),
                'district_id' => null,
                'courier' => mb_substr(trim($data['courier_name']), 0, 30),
                'service' => null,
                'etd' => null,
            ],
            default => $this->resolveCourier($data, $weight),
        };
    }

    private function resolveCourier(array $data, int $weight): array
    {
        try {
            $region = $this->shipping->resolveRegion((string) $data['province_id'], (string) $data['city_id'], (string) $data['district_id']);

            if (! $region) {
                throw ValidationException::withMessages(['district_id' => 'Pilih ulang provinsi, kota, dan kecamatan tujuan.']);
            }

            $rate = $this->shipping->findRate($this->destination($data['district_id']), $weight, (string) $data['rate']);
        } catch (ShippingUnavailableException $e) {
            throw ValidationException::withMessages(['rate' => $e->getMessage()]);
        }

        if (! $rate) {
            throw ValidationException::withMessages(['rate' => 'Tarif kurir berubah. Cek ongkir lagi lalu pilih kurir.']);
        }

        $address = collect([
            trim($data['address_line']),
            $region['district']->name,
            $region['city']->name,
            $region['province']->name,
            $data['postal_code'] ?? null,
        ])->filter()->implode(', ');

        return [
            'cost' => $rate->cost,
            'address' => $address,
            'district_id' => (string) $data['district_id'],
            'courier' => $rate->courier,
            'service' => $rate->service,
            'etd' => $rate->etd,
        ];
    }

    /** Ongkir untuk pratinjau: dari tarif yang dipilih (cache) atau isian manual. */
    private function shippingCostForQuote(array $data, int $weight): int
    {
        $mode = $data['shipping_mode'] ?? self::MODE_PICKUP;

        if ($mode === self::MODE_CUSTOM) {
            return max(0, (int) ($data['shipping_cost'] ?? 0));
        }

        if ($mode !== self::MODE_COURIER || empty($data['district_id']) || empty($data['rate']) || $weight <= 0) {
            return 0;
        }

        try {
            return $this->shipping->findRate($this->destination((string) $data['district_id']), $weight, (string) $data['rate'])?->cost ?? 0;
        } catch (ShippingUnavailableException) {
            return 0;
        }
    }

    /** @return array{courier: ?string, courier_service: ?string} */
    private function courierForQuote(array $data): array
    {
        return match ($data['shipping_mode'] ?? null) {
            self::MODE_CUSTOM => ['courier' => filled($data['courier_name'] ?? null) ? mb_substr(trim($data['courier_name']), 0, 30) : 'Kurir', 'courier_service' => null],
            self::MODE_COURIER => filled($data['rate'] ?? null)
                ? ['courier' => explode('|', (string) $data['rate'])[0], 'courier_service' => explode('|', (string) $data['rate'], 2)[1] ?? null]
                : ['courier' => null, 'courier_service' => null],
            default => ['courier' => null, 'courier_service' => null],
        };
    }

    /** @return array{0: ?Voucher, 1: array{discount: int, shipping_discount: int}} */
    private function applyVoucher(?string $code, ?User $customer, int $subtotal, int $shipping): array
    {
        if (Voucher::normalizeCode($code) === '') {
            return [null, ['discount' => 0, 'shipping_discount' => 0]];
        }

        $voucher = Voucher::where('code', Voucher::normalizeCode($code))->lockForUpdate()->first();

        try {
            if (! $voucher) {
                throw new VoucherException('Kode voucher tidak ditemukan.');
            }

            $this->vouchers->check($voucher, $customer, $subtotal);
        } catch (VoucherException $e) {
            throw ValidationException::withMessages(['voucher_code' => $e->getMessage()]);
        }

        return [$voucher, $this->vouchers->calculate($voucher, $subtotal, $shipping)];
    }

    /** Diskon khusus tidak boleh membuat harga barang minus. */
    private function manualDiscount(array $data, int $max): int
    {
        return max(0, min((int) ($data['manual_discount'] ?? 0), $max));
    }

    private function destination(string $districtId): Address
    {
        // Kecamatan baru saja dipilih dari data wilayah driver yang aktif.
        return (new Address)->forceFill(['district_id' => $districtId, 'region_source' => ShippingConfig::regionSource()]);
    }

    private function label(Product $product, ?ProductVariant $variant): string
    {
        return $variant ? "{$product->name} ({$variant->name})" : $product->name;
    }
}
