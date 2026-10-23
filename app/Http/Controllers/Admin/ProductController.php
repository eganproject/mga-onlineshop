<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $products) {}

    public function index(Request $request): View
    {
        $threshold = config('shop.low_stock_threshold');

        $products = Product::with('category')
            ->withCount('variants')
            ->search($request->string('q')->trim()->value() ?: null)
            ->when($request->filled('kategori'), function ($q) use ($request) {
                $category = Category::find($request->integer('kategori'));
                $q->whereIn('category_id', $category ? $category->familyIds() : [0]);
            })
            ->when($request->query('status') === 'aktif', fn ($q) => $q->where('is_active', true))
            ->when($request->query('status') === 'nonaktif', fn ($q) => $q->where('is_active', false))
            ->when($request->query('status') === 'unggulan', fn ($q) => $q->where('is_featured', true))
            ->when($request->query('status') === 'stok-menipis', fn ($q) => $q->where(fn ($q) => $q
                ->where('stock', '<=', $threshold)
                ->orWhereHas('variants', fn ($v) => $v->where('is_active', true)->where('stock', '<=', $threshold))))
            ->when($request->query('status') === 'stok-habis', fn ($q) => $q->where('stock', 0))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function create(): View
    {
        return view('admin.products.form', [
            'product' => new Product(['is_active' => true, 'weight' => 100]),
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = $this->products->save(new Product, $request->validated(), $request->file('images', []));

        return redirect()->route('admin.products.edit', $product)->with('success', 'Produk berhasil ditambahkan.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.form', [
            'product' => $product->load('images', 'variants'),
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $product = $this->products->save($product, $request->validated(), $request->file('images', []));

        return redirect()->route('admin.products.edit', $product)->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $fromEditPage = url()->previous() === route('admin.products.edit', $product);
        $this->products->delete($product);

        return ($fromEditPage ? redirect()->route('admin.products.index') : back())->with('success', 'Produk dihapus.');
    }

    /** Aksi massal dari daftar produk. */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['activate', 'deactivate', 'feature', 'unfeature', 'delete'])],
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['integer'],
        ], ['ids.required' => 'Pilih minimal satu produk.']);

        $products = Product::whereKey($data['ids'])->get();

        match ($data['action']) {
            'activate' => Product::whereKey($products->modelKeys())->update(['is_active' => true]),
            'deactivate' => Product::whereKey($products->modelKeys())->update(['is_active' => false]),
            'feature' => Product::whereKey($products->modelKeys())->update(['is_featured' => true]),
            'unfeature' => Product::whereKey($products->modelKeys())->update(['is_featured' => false]),
            'delete' => $products->each(fn (Product $p) => $this->products->delete($p)),
        };

        return back()->with('success', "{$products->count()} produk diperbarui.");
    }

    /** Kategori induk diikuti subkategorinya, untuk dropdown. */
    private function categoryOptions()
    {
        return Category::root()->with('children')->orderBy('sort_order')->orderBy('name')->get()
            ->flatMap(fn (Category $c) => [$c, ...$c->children->each(fn ($child) => $child->setRelation('parent', $c))]);
    }
}
