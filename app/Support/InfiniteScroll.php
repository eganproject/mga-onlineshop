<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Infinite scroll daftar pelanggan (`<x-infinite-list>`).
 *
 * Halaman berikutnya diambil lewat fetch dengan header X-Infinite-Scroll;
 * server cukup membalas potongan `@fragment('infinite-list')` (tanpa header,
 * footer, dll.) supaya ringan. Tanpa header, halaman dirender utuh seperti
 * biasa (link halaman tetap ada untuk SEO / tanpa JS).
 */
final class InfiniteScroll
{
    public const HEADER = 'X-Infinite-Scroll';

    public const FRAGMENT = 'infinite-list';

    public static function respond(Request $request, View $view): View|Response
    {
        if (! $request->headers->has(self::HEADER)) {
            return $view;
        }

        return response($view->fragment(self::FRAGMENT))
            ->header('Vary', self::HEADER)
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex');
    }
}
