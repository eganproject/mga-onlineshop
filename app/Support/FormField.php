<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Helper kecil untuk komponen Blade form (resources/views/components/form).
 */
final class FormField
{
    /** "variants[0][price]" -> "variants.0.price" (kunci error validasi & old()). */
    public static function errorKey(?string $name): ?string
    {
        if (blank($name)) {
            return null;
        }

        return trim(str_replace(['[]', '[', ']'], ['', '.', ''], $name), '.');
    }

    /** "variants[0][price]" -> "variants_0_price" (id elemen bawaan). */
    public static function id(?string $name): ?string
    {
        if (blank($name)) {
            return null;
        }

        return trim(Str::of($name)->replace(['[', ']', '.'], '_')->replaceMatches('/_+/', '_')->toString(), '_');
    }

    /** Apakah isian ini punya error di bag yang diminta. */
    public static function hasError(?string $name, string $bag = 'default'): bool
    {
        $key = self::errorKey($name);
        $errors = view()->shared('errors');

        return $key !== null && $errors !== null && $errors->getBag($bag)->has($key);
    }

    /** Format angka ke "1.250.000" untuk nilai awal input Rupiah. */
    public static function thousands(mixed $value): string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        return $digits === '' ? '' : number_format((int) $digits, 0, ',', '.');
    }
}
