<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
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

        $merge = [
            'price' => $digits($this->input('price')),
            'compare_at_price' => $digits($this->input('compare_at_price')),
        ];

        if (is_array($this->input('variants'))) {
            $merge['variants'] = collect($this->input('variants'))
                ->map(fn ($v) => is_array($v) ? array_merge($v, [
                    'price' => $digits($v['price'] ?? null),
                    'compare_at_price' => $digits($v['compare_at_price'] ?? null),
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

            foreach ($this->boolean('has_variants') ? $this->input('variants', []) : [] as $i => $variant) {
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
