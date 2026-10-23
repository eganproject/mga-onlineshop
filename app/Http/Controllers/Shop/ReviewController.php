<?php

namespace App\Http\Controllers\Shop;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Reviews\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Product $product, ReviewService $reviews): RedirectResponse
    {
        abort_unless($product->is_active, 404);

        if (! $reviews->canReview($request->user(), $product)) {
            return back()->with('error', 'Ulasan bisa ditulis setelah pesanan berisi produk ini selesai.');
        }

        $data = $request->validateWithBag('review', [
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:10', 'max:2000'],
        ], [], ['rating' => 'rating', 'title' => 'judul', 'body' => 'ulasan']);

        $review = $reviews->submit($request->user(), $product, $data);

        return redirect()->to(route('products.show', $product).'#ulasan')->with('success', $review->status === ReviewStatus::Approved
            ? 'Terima kasih! Ulasanmu sudah tayang.'
            : 'Terima kasih! Ulasanmu akan tayang setelah dicek tim kami.');
    }
}
