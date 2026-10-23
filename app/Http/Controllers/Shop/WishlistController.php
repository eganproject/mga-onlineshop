<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Customer\WishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function toggle(Request $request, Product $product, WishlistService $wishlist): JsonResponse|RedirectResponse
    {
        // Produk nonaktif tetap bisa dihapus dari wishlist, tapi tidak bisa ditambahkan.
        abort_if(! $product->is_active && ! $request->user()->wishlist()->whereKey($product->id)->exists(), 404);

        $added = $wishlist->toggle($request->user(), $product);
        $message = $added ? 'Disimpan ke wishlist.' : 'Dihapus dari wishlist.';

        if ($request->expectsJson()) {
            return response()->json([
                'wishlisted' => $added,
                'count' => $wishlist->count(),
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }
}
