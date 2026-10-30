<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Catalog\Wholesale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductRequest extends FormRequest
{
    public const MAX_IMAGES = 10;

    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        // Izinkan input harga dengan titik ribuan, contoh "150.000".
        $digits = fn ($value) => filled($value) ? preg_replace('/\D/', '', (string) $value) : $value;

        // Tingkat grosir: angka bersih, baris kosong dibuang.
        $tiers = fn ($rows) => collect(is_array($rows) ? $rows : [])
            ->filter(fn ($t) => is_array($t) && (filled($t['min_qty'] ?? null) || filled($t['price'] ?? null)))
            ->map(fn ($t) => ['min_qty' => $digits($t['min_qty'] ?? null), 'price' => $digits($t['price'] ?? null)])
            ->values()->all();

        $merge = [
            'price' => $digits($this->input('price')),
            'compare_at_price' => $digits($this->input('compare_at_price')),
            'wholesale_tiers' => $tiers($this->input('wholesale_tiers')),
            'min_order_qty' => $digits($this->input('min_order_qty')) ?: 1,
        ];

        if (is_array($this->input('variants'))) {
            $merge['variants'] = collect($this->input('variants'))
                ->map(fn ($v) => is_array($v) ? array_merge($v, [
                    'price' => $digits($v['price'] ?? null),
                    'compare_at_price' => $digits($v['compare_at_price'] ?? null),
                    'wholesale_tiers' => $tiers($v['wholesale_tiers'] ?? null),
                ]) : $v)
                ->values()
                ->all();
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('products')->ignore($product)],
            'sku' => ['nullable', 'string', 'max:64', Rule::unique('products')->ignore($product)],
            'category_id' => ['nullable', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:10000'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'weight' => ['required', 'integer', 'min:1', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'has_variants' => ['sometimes', 'boolean'],

            // Tanpa varian: harga & stok di level produk.
            'price' => ['exclude_if:has_variants,1', 'required', 'integer', 'min:0'],
            'compare_at_price' => ['exclude_if:has_variants,1', 'nullable', 'integer', 'min:0'],
            'stock' => ['exclude_if:has_variants,1', 'required', 'integer', 'min:0', 'max:1000000'],
            'stock_original' => ['exclude_if:has_variants,1', 'nullable', 'integer'],
            'wholesale_tiers' => ['exclude_if:has_variants,1', 'array', 'max:'.Wholesale::MAX_TIERS],
            'wholesale_tiers.*.min_qty' => ['exclude_if:has_variants,1', 'required', 'integer', 'min:2', 'max:9999'],
            'wholesale_tiers.*.price' => ['exclude_if:has_variants,1', 'required', 'integer', 'min:1'],
            'min_order_qty' => ['required', 'integer', 'min:1', 'max:999'],

            // Dengan varian: harga & stok per varian.
            'variant_label' => ['exclude_unless:has_variants,1', 'nullable', 'string', 'max:50'],
            'variants' => ['exclude_unless:has_variants,1', 'required', 'array', 'min:1', 'max:50'],
            'variants.*.id' => ['exclude_unless:has_variants,1', 'nullable', 'integer'],
            'variants.*.name' => ['exclude_unless:has_variants,1', 'required', 'string', 'max:100', 'distinct:ignore_case'],
            'variants.*.sku' => ['exclude_unless:has_variants,1', 'nullable', 'string', 'max:64', 'distinct:ignore_case'],
            'variants.*.price' => ['exclude_unless:has_variants,1', 'required', 'integer', 'min:0'],
            'variants.*.compare_at_price' => ['exclude_unless:has_variants,1', 'nullable', 'integer', 'min:0'],
            'variants.*.stock' => ['exclude_unless:has_variants,1', 'required', 'integer', 'min:0', 'max:1000000'],
            'variants.*.stock_original' => ['exclude_unless:has_variants,1', 'nullable', 'integer'],
            'variants.*.weight' => ['exclude_unless:has_variants,1', 'nullable', 'integer', 'min:1', 'max:100000'],
            'variants.*.image_id' => ['exclude_unless:has_variants,1', 'nullable', 'integer'],
            'variants.*.is_active' => ['exclude_unless:has_variants,1', 'sometimes', 'boolean'],
            'variants.*.wholesale_tiers' => ['exclude_unless:has_variants,1', 'array', 'max:'.Wholesale::MAX_TIERS],
            'variants.*.wholesale_tiers.*.min_qty' => ['exclude_unless:has_variants,1', 'required', 'integer', 'min:2', 'max:9999'],
            'variants.*.wholesale_tiers.*.price' => ['exclude_unless:has_variants,1', 'required', 'integer', 'min:1'],

            'images' => ['nullable', 'array', 'max:'.self::MAX_IMAGES],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'image_order' => ['nullable', 'array'],
            'image_order.*' => ['integer'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Product|null $product */
            $product = $this->route('product');

            // Batas jumlah foto setelah ditambah & dikurangi.
            $existing = $product ? $product->images()->whereKeyNot($this->input('remove_images', []))->count() : 0;
            if ($existing + count($this->file('images', [])) > self::MAX_IMAGES) {
                $validator->errors()->add('images', 'Maksimal '.self::MAX_IMAGES.' foto per produk.');
            }

            if (! $this->boolean('has_variants') && ($problem = self::tierProblem($this->input('wholesale_tiers', []), (int) $this->input('price')))) {
                $validator->errors()->add('wholesale_tiers', "Harga grosir: {$problem}");
            }

            foreach ($this->boolean('has_variants') ? $this->input('variants', []) : [] as $i => $variant) {
                if ($problem = self::tierProblem($variant['wholesale_tiers'] ?? [], (int) ($variant['price'] ?? 0))) {
                    $validator->errors()->add("variants.{$i}.wholesale_tiers", "Harga grosir varian {$variant['name']}: {$problem}");
                }

                $id = $variant['id'] ?? null;

                if ($id && (! $product || ! $product->variants()->whereKey($id)->exists())) {
                    $validator->errors()->add("variants.{$i}.name", 'Varian tidak ditemukan. Muat ulang halaman.');
                }

                if (filled($variant['sku'] ?? null)
                    && ProductVariant::where('sku', $variant['sku'])->when($id, fn ($q) => $q->whereKeyNot($id))->exists()) {
                    $validator->errors()->add("variants.{$i}.sku", "SKU {$variant['sku']} sudah dipakai varian lain.");
                }
            }
        }];
    }

    /** Cek urutan tingkat grosir: jumlah naik, harga turun & di bawah harga normal. */
    public static function tierProblem(array $tiers, int $regularPrice): ?string
    {
        $prevQty = 1;
        $prevPrice = $regularPrice;

        foreach (array_values($tiers) as $i => $tier) {
            $qty = (int) ($tier['min_qty'] ?? 0);
            $price = (int) ($tier['price'] ?? 0);

            if ($qty <= $prevQty) {
                return $i === 0 ? 'jumlah minimal 2 pcs.' : 'jumlah tiap tingkat harus lebih banyak dari tingkat sebelumnya.';
            }

            if ($price >= $prevPrice) {
                return $i === 0 ? 'harga harus lebih murah dari harga normal.' : 'harga tiap tingkat harus lebih murah dari tingkat sebelumnya.';
            }

            [$prevQty, $prevPrice] = [$qty, $price];
        }

        return null;
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama produk',
            'category_id' => 'kategori',
            'description' => 'deskripsi',
            'meta_title' => 'judul SEO',
            'meta_description' => 'deskripsi SEO',
            'price' => 'harga',
            'compare_at_price' => 'harga coret',
            'stock' => 'stok',
            'weight' => 'berat',
            'variant_label' => 'judul varian',
            'variants' => 'varian',
            'variants.*.name' => 'nama varian',
            'variants.*.sku' => 'SKU varian',
            'variants.*.price' => 'harga varian',
            'variants.*.compare_at_price' => 'harga coret varian',
            'variants.*.stock' => 'stok varian',
            'variants.*.weight' => 'berat varian',
            'wholesale_tiers' => 'harga grosir',
            'wholesale_tiers.*.min_qty' => 'jumlah grosir',
            'wholesale_tiers.*.price' => 'harga grosir',
            'variants.*.wholesale_tiers.*.min_qty' => 'jumlah grosir varian',
            'variants.*.wholesale_tiers.*.price' => 'harga grosir varian',
            'min_order_qty' => 'minimal pembelian',
            'images' => 'foto produk',
            'images.*' => 'foto produk',
        ];
    }

    public function messages(): array
    {
        return [
            'variants.required' => 'Tambahkan minimal satu varian, atau matikan opsi varian.',
            'variants.*.name.distinct' => 'Nama varian tidak boleh sama.',
            'variants.*.sku.distinct' => 'SKU varian tidak boleh sama.',
        ];
    }
}
