<?php

namespace App\Support;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Aturan bersama untuk daftar di panel admin: jumlah baris per halaman dan
 * urutan kolom. Dipakai bersama komponen <x-admin.pagination> dan
 * <x-admin.sort-link>, jadi semua daftar terasa sama.
 *
 * - `?per_page=25` → disimpan di session per daftar, jadi tetap dipakai saat
 *   pindah halaman atau kembali dari form edit.
 * - `?sort=nama` (naik) / `?sort=-nama` (turun).
 */
class AdminList
{
    public const PER_PAGE_OPTIONS = [10, 15, 25, 50, 100];

    public static function perPage(Request $request, int $default = 15, ?string $key = null): int
    {
        $key = 'admin.per_page.'.($key ?? $request->route()?->getName() ?? $request->path());
        $value = (int) $request->query('per_page');

        if (in_array($value, self::PER_PAGE_OPTIONS, true)) {
            $request->session()->put($key, $value);

            return $value;
        }

        $remembered = (int) $request->session()->get($key);

        return in_array($remembered, self::PER_PAGE_OPTIONS, true) ? $remembered : $default;
    }

    /**
     * Paginate dengan jumlah baris pilihan pengguna, filter di URL ikut di link
     * halaman. Halaman di luar jangkauan (mis. setelah menghapus data terakhir
     * di halaman terakhir) diarahkan ke halaman terakhir, bukan daftar kosong.
     */
    public static function paginate(Builder $query, Request $request, int $default = 15, string $pageName = 'page', ?string $key = null): LengthAwarePaginator
    {
        $perPage = self::perPage($request, $default, $key);
        $paginator = (clone $query)->paginate($perPage, ['*'], $pageName);

        if ($paginator->isEmpty() && $paginator->currentPage() > 1 && $paginator->total() > 0) {
            $paginator = $query->paginate($perPage, ['*'], $pageName, $paginator->lastPage());
        }

        return $paginator->withQueryString();
    }

    /**
     * Terapkan urutan dari `?sort=`. Kunci $columns = nilai di URL, isinya kolom
     * SQL (atau closure fn (Builder $q, string $direction)).
     *
     * @param  array<string, string|\Closure>  $columns
     */
    public static function sort(Builder $query, Request $request, array $columns, string $default): Builder
    {
        [$key, $direction] = self::parse((string) $request->query('sort', $default));

        if (! array_key_exists($key, $columns)) {
            [$key, $direction] = self::parse($default);
        }

        // Dibaca <x-admin.sort-link> untuk menandai kolom yang sedang aktif.
        $request->attributes->set('admin_sort', ($direction === 'desc' ? '-' : '').$key);
        $column = $columns[$key];

        if ($column instanceof \Closure) {
            $column($query, $direction);
        } else {
            $query->orderBy($column, $direction);
        }

        return $query;
    }

    /** @return array{0: string, 1: 'asc'|'desc'} */
    public static function parse(string $sort): array
    {
        return str_starts_with($sort, '-') ? [substr($sort, 1), 'desc'] : [$sort, 'asc'];
    }
}
