<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use App\Services\Reviews\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $status = ReviewStatus::tryFrom((string) $request->query('status'));
        $rating = $request->integer('rating');

        return view('admin.reviews.index', [
            'reviews' => ProductReview::with(['product', 'user'])
                ->when($status, fn ($q) => $q->where('status', $status))
                ->when($rating >= 1 && $rating <= 5, fn ($q) => $q->where('rating', $rating))
                ->when($request->filled('q'), function ($q) use ($request) {
                    $term = $request->string('q')->trim()->value();
                    $q->where(fn ($q) => $q->where('body', 'like', "%{$term}%")
                        ->orWhere('title', 'like', "%{$term}%")
                        ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$term}%"))
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")));
                })
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'counts' => ProductReview::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'activeStatus' => $status,
        ]);
    }

    public function update(Request $request, ProductReview $review, ReviewService $reviews): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::enum(ReviewStatus::class)],
            'admin_reply' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        if (isset($data['status'])) {
            $reviews->moderate($review, ReviewStatus::from($data['status']));
        }

        if (array_key_exists('admin_reply', $data)) {
            $reviews->reply($review, $data['admin_reply']);
        }

        return back()->with('success', 'Ulasan diperbarui.');
    }

    public function destroy(ProductReview $review, ReviewService $reviews): RedirectResponse
    {
        $reviews->delete($review);

        return back()->with('success', 'Ulasan dihapus.');
    }
}
