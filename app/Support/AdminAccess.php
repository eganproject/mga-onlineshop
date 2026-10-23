<?php

namespace App\Support;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Cek apakah staf yang login boleh membuka route admin, dibaca langsung dari
 * middleware `can:...` di routes/web.php. Dipakai menu sidebar supaya menu
 * yang tidak boleh diakses tidak tampil, tanpa daftar izin ganda.
 */
class AdminAccess
{
    public static function canVisit(string $routeName): bool
    {
        $route = Route::getRoutes()->getByName($routeName);

        if (! $route) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && Str::startsWith($middleware, 'can:')) {
                $ability = Str::before(Str::after($middleware, 'can:'), ',');

                if (Gate::denies($ability)) {
                    return false;
                }
            }
        }

        return true;
    }
}
