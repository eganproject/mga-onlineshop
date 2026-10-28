<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\AdminList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->trim()->value();

        // Tampilkan sebagai pohon: kategori induk (dipaginasi) lalu subkategorinya.
        $paginator = $search
            ? AdminList::paginate(Category::withCount('products')->with('parent')->where('name', 'like', "%{$search}%")->orderBy('name'), $request)
            : AdminList::paginate(Category::root()->withCount('products')
                ->with(['children' => fn ($q) => $q->withCount('products')])
                ->orderBy('sort_order')->orderBy('name'), $request);

        $categories = $search
            ? $paginator->getCollection()
            : $paginator->getCollection()->flatMap(fn (Category $c) => [$c, ...$c->children->each->setRelation('parent', $c)]);

        return view('admin.categories.index', ['categories' => $categories, 'paginator' => $paginator, 'search' => $search]);
    }

    public function create(): View
    {
        return view('admin.categories.form', [
            'category' => new Category(['is_active' => true]),
            'parents' => $this->parentOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Category::create($this->validated($request));

        return redirect()->route('admin.categories.index')->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.form', [
            'category' => $category,
            'parents' => $this->parentOptions($category),
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category->update($this->validated($request, $category));

        return redirect()->route('admin.categories.index')->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->image_path) {
            Storage::disk('public')->delete($category->image_path);
        }

        $category->delete();

        return back()->with('success', 'Kategori dihapus. Produk & subkategori di dalamnya tetap ada.');
    }

    /** Aktif/nonaktifkan cepat dari daftar. */
    public function toggle(Category $category): RedirectResponse
    {
        $category->update(['is_active' => ! $category->is_active]);

        return back()->with('success', "Kategori {$category->name} ".($category->is_active ? 'ditampilkan.' : 'disembunyikan.'));
    }

    /** Kategori yang boleh jadi induk: hanya kategori level atas, bukan dirinya sendiri. */
    private function parentOptions(?Category $category = null)
    {
        return Category::root()
            ->when($category, fn ($q) => $q->whereKeyNot($category->id))
            ->orderBy('sort_order')->orderBy('name')->get();
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:120', 'alpha_dash', Rule::unique('categories')->ignore($category)],
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('categories', 'id')->whereNull('parent_id'),
                Rule::notIn(array_filter([$category?->id])),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['sometimes', 'boolean'],
        ], [
            'parent_id.exists' => 'Induk harus kategori level atas (maksimal 2 tingkat).',
            'parent_id.not_in' => 'Kategori tidak bisa menjadi induk dirinya sendiri.',
        ], ['name' => 'nama kategori', 'image' => 'gambar', 'parent_id' => 'induk kategori', 'meta_title' => 'judul SEO', 'meta_description' => 'deskripsi SEO']);

        // Kategori yang punya subkategori tidak boleh dijadikan subkategori (maks 2 tingkat).
        if (($data['parent_id'] ?? null) && $category?->children()->exists()) {
            throw ValidationException::withMessages([
                'parent_id' => 'Kategori ini punya subkategori, jadi harus tetap di level atas.',
            ]);
        }

        $data['slug'] = ($data['slug'] ?? null) ?: $this->uniqueSlug($data['name'], $category);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['parent_id'] = $data['parent_id'] ?? null;

        if ($request->hasFile('image') || $request->boolean('remove_image')) {
            if ($category?->image_path) {
                Storage::disk('public')->delete($category->image_path);
            }
            $data['image_path'] = $request->hasFile('image') ? $request->file('image')->store('categories', 'public') : null;
        }

        unset($data['image'], $data['remove_image']);

        return $data;
    }

    private function uniqueSlug(string $name, ?Category $ignore): string
    {
        $base = Str::slug($name) ?: 'kategori';
        $slug = $base;
        $i = 2;

        while (Category::where('slug', $slug)->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
