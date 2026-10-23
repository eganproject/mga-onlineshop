<?php

namespace App\Http\Controllers\Account;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $reviews = $user->reviews()->with('product')->latest()->get();

        // Produk dari pesanan selesai yang belum diulas.
        $toReview = Product::active()
            ->whereIn('id', fn ($q) => $q->select('product_id')->from('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.user_id', $user->id)
                ->where('orders.status', OrderStatus::Completed->value))
            ->whereNotIn('id', $reviews->pluck('product_id'))
            ->get();

        return view('account.reviews', [
            'reviews' => $reviews,
            'toReview' => $toReview,
        ]);
    }
}
