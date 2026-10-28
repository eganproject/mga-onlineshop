<?php

namespace App\Http\Middleware;

use App\Services\Activity\ActivityLogger;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Catat setiap aksi staf yang mengubah data (POST/PUT/PATCH/DELETE) di panel
 * admin, termasuk menu baru, tanpa perlu menambah kode di tiap controller.
 */
class LogAdminActivity
{
    /** Nama route => deskripsi aksi. Route lain memakai nama route-nya. */
    private const LABELS = [
        'admin.categories.store' => 'Menambah kategori',
        'admin.categories.update' => 'Mengubah kategori',
        'admin.categories.destroy' => 'Menghapus kategori',
        'admin.categories.toggle' => 'Mengubah status kategori',
        'admin.products.store' => 'Menambah produk',
        'admin.products.update' => 'Mengubah produk',
        'admin.products.destroy' => 'Menghapus produk',
        'admin.products.bulk' => 'Aksi massal produk',
        'admin.stock.adjust' => 'Menyesuaikan stok',
        'admin.stock.warehouse.sync' => 'Sinkron stok dari gudang',
        'admin.stock.warehouse.link' => 'Menghubungkan SKU gudang',
        'admin.stock.warehouse.unlink' => 'Memutus SKU gudang',
        'admin.vouchers.store' => 'Menambah voucher',
        'admin.vouchers.update' => 'Mengubah voucher',
        'admin.vouchers.destroy' => 'Menghapus voucher',
        'admin.vouchers.toggle' => 'Mengubah status voucher',
        'admin.banners.store' => 'Menambah banner',
        'admin.banners.update' => 'Mengubah banner',
        'admin.banners.destroy' => 'Menghapus banner',
        'admin.banners.toggle' => 'Mengubah status banner',
        'admin.articles.store' => 'Menulis artikel',
        'admin.articles.update' => 'Mengubah artikel',
        'admin.articles.destroy' => 'Menghapus artikel',
        'admin.article-categories.store' => 'Menambah kategori artikel',
        'admin.article-categories.update' => 'Mengubah kategori artikel',
        'admin.article-categories.destroy' => 'Menghapus kategori artikel',
        'admin.reviews.update' => 'Memoderasi ulasan',
        'admin.reviews.destroy' => 'Menghapus ulasan',
        'admin.orders.bulk' => 'Aksi massal pesanan',
        'admin.orders.status' => 'Mengubah status pesanan',
        'admin.orders.tracking-number' => 'Mengubah nomor resi',
        'admin.customers.toggle' => 'Memblokir / mengaktifkan pelanggan',
        'admin.integrations.update' => 'Mengubah integrasi',
        'admin.integrations.test' => 'Tes koneksi integrasi',
        'admin.profile.update' => 'Mengubah profil sendiri',
        'admin.profile.password' => 'Mengganti password sendiri',
    ];

    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldLog($request, $response)) {
            $route = $request->route();
            $name = $route?->getName() ?? $request->path();
            $subject = collect($route?->parameters() ?? [])->first(fn ($p) => $p instanceof Model);

            $this->logger->log(
                Str::after($name, 'admin.'),
                self::LABELS[$name] ?? Str::headline(Str::after($name, 'admin.')),
                $subject,
                $request->except(array_keys($request->allFiles())),
            );
        }

        return $response;
    }

    private function shouldLog(Request $request, Response $response): bool
    {
        if ($request->isMethodSafe() || ! $request->user() || $this->logger->loggedThisRequest()) {
            return false;
        }

        // Gagal validasi (error baru di-flash) atau ditolak: tidak ada yang berubah.
        $flashed = $request->hasSession() ? (array) $request->session()->get('_flash.new', []) : [];

        return $response->getStatusCode() < 400 && ! in_array('errors', $flashed, true);
    }
}
