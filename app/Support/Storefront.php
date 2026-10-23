<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Data tampilan beranda (hero & bar pengumuman) dari config('shop.storefront'),
 * yang bisa diubah di Admin › Tampilan Beranda.
 */
class Storefront
{
    /** Ilustrasi bawaan bila belum ada foto hero. */
    public const DEFAULT_IMAGE = 'images/hero/hero-default.svg';

    public const DEFAULT_IMAGE_MOBILE = 'images/hero/hero-default-mobile.svg';

    /** Folder unggahan foto hero di disk public. */
    public const UPLOAD_DIR = 'storefront';

    /**
     * @return array{image: string, mobile_image: ?string, custom: bool, eyebrow: ?string, title: string, subtitle: ?string,
     *     primary: ?array{label: string, url: string}, secondary: ?array{label: string, url: string}, light: bool}
     */
    public static function hero(): array
    {
        $c = config('shop.storefront');
        $image = self::url($c['hero_image'] ?? null);
        $mobile = self::url($c['hero_image_mobile'] ?? null);

        return [
            'image' => $image ?? asset(self::DEFAULT_IMAGE),
            // Tanpa foto khusus ponsel: pakai foto utama (dipotong), atau ilustrasi versi ponsel.
            'mobile_image' => $mobile ?? ($image ? null : asset(self::DEFAULT_IMAGE_MOBILE)),
            'custom' => (bool) $image,
            'eyebrow' => $c['hero_eyebrow'] ?? null,
            'title' => $c['hero_title'] ?? config('shop.tagline'),
            'subtitle' => $c['hero_subtitle'] ?? null,
            'primary' => self::button($c['hero_primary_label'] ?? null, $c['hero_primary_url'] ?? null),
            'secondary' => self::button($c['hero_secondary_label'] ?? null, $c['hero_secondary_url'] ?? null),
            'light' => ($c['hero_text'] ?? 'light') !== 'dark',
        ];
    }

    /** @return list<string> */
    public static function announcements(): array
    {
        return array_values(array_filter((array) config('shop.storefront.announcements', []), 'filled'));
    }

    /** Path disk public ("storefront/x.jpg"), path folder public/ ("images/x.jpg"), atau URL. */
    public static function url(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://', '//'])) {
            return $value;
        }

        $value = ltrim($value, '/');

        return Str::startsWith($value, self::UPLOAD_DIR.'/')
            ? Storage::disk('public')->url($value)
            : asset($value);
    }

    /** Link internal ("/produk") jadi URL penuh; link luar dibiarkan. */
    public static function href(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        return str_starts_with($url, '/') ? url($url) : $url;
    }

    /** @return ?array{label: string, url: string} */
    private static function button(?string $label, ?string $url): ?array
    {
        return filled($label) && filled($url) ? ['label' => $label, 'url' => self::href($url)] : null;
    }
}
