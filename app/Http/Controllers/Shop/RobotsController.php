<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    /** Halaman privat yang tidak perlu dirayapi mesin pencari. */
    private const PRIVATE_PATHS = ['/admin', '/akun', '/keranjang', '/checkout', '/email', '/wishlist', '/masuk', '/daftar', '/lupa-password', '/reset-password'];

    public function __invoke(): Response
    {
        $lines = ['User-agent: *'];

        if (config('shop.seo.indexing')) {
            foreach (self::PRIVATE_PATHS as $path) {
                $lines[] = "Disallow: {$path}";
            }
            $lines[] = '';
            $lines[] = 'Sitemap: '.route('sitemap');
        } else {
            // Staging / development: jangan diindeks sama sekali.
            $lines[] = 'Disallow: /';
        }

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
