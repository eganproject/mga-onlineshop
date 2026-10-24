<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Support\InfiniteScroll;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class WishlistController extends Controller
{
    public function index(Request $request): View|Response
    {
        return InfiniteScroll::respond($request, view('account.wishlist', [
            'products' => $request->user()->wishlist()->with('category')->paginate(12),
        ]));
    }
}
