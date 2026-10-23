<?php

namespace App\Support;

use Illuminate\Http\Request;

/** Aturan SEO bawaan untuk setiap halaman (canonical & robots). */
class Seo
{
    /** Halaman privat / transaksi yang tidak boleh diindeks. */
    public const PRIVATE_ROUTES = [
        'admin.*', 'account.*', 'cart.*', 'checkout.*', 'verification.*',
        'login', 'register', 'password.*',
    ];

    /** Parameter yang membuat halaman unik (dipertahankan di canonical). */
    public const CANONICAL_PARAMS = ['kategori', 'tag', 'page'];

    /** Parameter filter/pencarian: halamannya tidak diindeks agar tidak jadi duplikat. */
    public const FILTER_PARAMS = ['q', 'urut', 'promo', 'tersedia', 'harga_min', 'harga_max'];

    public static function canonical(Request $request): string
    {
        $query = array_filter(
            $request->only(self::CANONICAL_PARAMS),
            fn ($value, $key) => is_string($value) && $value !== '' && ! ($key === 'page' && $value === '1'),
            ARRAY_FILTER_USE_BOTH,
        );

        return $request->url().($query ? '?'.http_build_query($query) : '');
    }

    public static function shouldIndex(Request $request): bool
    {
        return config('shop.seo.indexing')
            && ! $request->routeIs(...self::PRIVATE_ROUTES)
            && ! $request->hasAny(self::FILTER_PARAMS)
            && ! app()->isDownForMaintenance();
    }
}
