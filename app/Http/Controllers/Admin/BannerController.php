<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BannerController extends Controller
{
    public function index(): View
    {
        return view('admin.banners.index', [
            'banners' => Banner::orderBy('sort_order')->latest('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.banners.form', ['banner' => new Banner(['is_active' => true, 'show_text' => true, 'sort_order' => 0])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Banner::create($this->validated($request));

        return redirect()->route('admin.banners.index')->with('success', 'Banner berhasil ditambahkan.');
    }

    public function edit(Banner $banner): View
    {
        return view('admin.banners.form', ['banner' => $banner]);
    }

    public function update(Request $request, Banner $banner): RedirectResponse
    {
        $banner->update($this->validated($request, $banner));

        return redirect()->route('admin.banners.index')->with('success', 'Banner berhasil diperbarui.');
    }

    public function destroy(Banner $banner): RedirectResponse
    {
        Storage::disk('public')->delete(array_filter([$banner->image_path, $banner->mobile_image_path]));
        $banner->delete();

        return back()->with('success', 'Banner dihapus.');
    }

    public function toggle(Banner $banner): RedirectResponse
    {
        $banner->update(['is_active' => ! $banner->is_active]);

        return back()->with('success', 'Banner '.($banner->is_active ? 'ditampilkan.' : 'disembunyikan.'));
    }

    private function validated(Request $request, ?Banner $banner = null): array
    {
        $image = ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:min_width=600'];

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'button_label' => ['nullable', 'string', 'max:40'],
            // Link internal ("/produk?promo=1") atau URL https lengkap.
            'link_url' => ['nullable', 'string', 'max:500', 'regex:#^(/(?!/)|https?://)#i'],
            'image' => [$banner ? 'nullable' : 'required', ...$image],
            'mobile_image' => ['nullable', ...$image],
            'remove_mobile_image' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'link_url.regex' => 'Link harus diawali "/" (halaman toko) atau "https://".',
            'image.dimensions' => 'Lebar gambar minimal 600 px.',
            'mobile_image.dimensions' => 'Lebar gambar minimal 600 px.',
            'ends_at.after' => 'Tanggal berakhir harus setelah tanggal mulai.',
        ], [
            'title' => 'judul', 'subtitle' => 'subjudul', 'button_label' => 'teks tombol', 'link_url' => 'link',
            'image' => 'gambar', 'mobile_image' => 'gambar versi HP', 'starts_at' => 'tanggal mulai', 'ends_at' => 'tanggal berakhir',
        ]);

        if ($request->hasFile('image')) {
            if ($banner?->image_path) {
                Storage::disk('public')->delete($banner->image_path);
            }
            $data['image_path'] = $request->file('image')->store('banners', 'public');
        }

        if ($request->hasFile('mobile_image') || $request->boolean('remove_mobile_image')) {
            if ($banner?->mobile_image_path) {
                Storage::disk('public')->delete($banner->mobile_image_path);
            }
            $data['mobile_image_path'] = $request->hasFile('mobile_image') ? $request->file('mobile_image')->store('banners', 'public') : null;
        }

        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');
        $data['show_text'] = $request->boolean('show_text');

        unset($data['image'], $data['mobile_image'], $data['remove_mobile_image']);

        return $data;
    }
}
