<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;

/**
 * Tujuan setelah login: halaman yang tadi diminta (intended), kecuali halaman itu khusus peran lain
 * (mis. staf yang login dari tombol Checkout, atau pelanggan dari link /admin) supaya tidak berakhir di 403.
 */
class LoginRedirect
{
    public static function to(Request $request, User $user, ?string $default = null): RedirectResponse
    {
        $intended = $request->session()->pull('url.intended');

        if (is_string($intended) && self::allows($user, $intended)) {
            return redirect()->to($intended);
        }

        return redirect()->to($default ?? $user->homeRoute());
    }

    private static function allows(User $user, string $url): bool
    {
        try {
            $route = Route::getRoutes()->match(Request::create($url));
        } catch (Throwable) {
            return true;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'role:')
                && ! in_array($user->role->value, explode(',', Str::after($middleware, 'role:')), true)) {
                return false;
            }
        }

        return true;
    }
}
