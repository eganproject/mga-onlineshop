<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Services\Catalog\SoldCountService;
use App\Services\Reviews\ReviewService;
use Database\Seeders\DemoOrderSeeder;
use Database\Seeders\DemoReviewSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\confirm;

/**
 * Isi ulang pesanan & ulasan contoh untuk katalog saat ini (mis. setelah
 * `shop:import-mga --replace`), supaya bintang dan jumlah terjual tampil.
 *   php artisan shop:demo-activity --reset   hapus SEMUA pesanan & ulasan lalu buat contoh baru
 *   php artisan shop:demo-activity --sync    hanya hitung ulang jumlah terjual & rating
 */
#[Signature('shop:demo-activity
    {--reset : Hapus semua pesanan & ulasan yang ada, lalu buat data contoh baru}
    {--sync : Hanya hitung ulang jumlah terjual & ringkasan rating semua produk}
    {--force : Jangan minta konfirmasi}')]
#[Description('Buat pesanan & ulasan contoh (demo) dan hitung ulang jumlah terjual produk')]
class DemoActivityCommand extends Command
{
    public function handle(SoldCountService $sold, ReviewService $reviews): int
    {
        if ($this->option('sync')) {
            $this->resync($sold, $reviews);

            return self::SUCCESS;
        }

        if (app()->isProduction()) {
            $this->components->error('Data contoh tidak boleh dibuat di produksi. Untuk menghitung ulang saja pakai --sync.');

            return self::FAILURE;
        }

        if ($this->option('reset')) {
            if (! $this->option('force')
                && ! confirm('SEMUA pesanan, pembayaran, dan ulasan akan dihapus permanen lalu diganti data contoh. Lanjutkan?', default: false)) {
                $this->components->warn('Dibatalkan, tidak ada yang diubah.');

                return self::FAILURE;
            }
        } elseif (Order::exists()) {
            $this->components->warn('Sudah ada pesanan, jadi pesanan contoh tidak dibuat. Pakai --reset untuk mengganti semuanya.');
        }

        // Satu transaksi: kalau pembuatan data contoh gagal, pesanan & ulasan lama tidak ikut hilang.
        DB::transaction(function () {
            if ($this->option('reset')) {
                ProductReview::query()->delete();
                Order::query()->delete(); // item, pembayaran & riwayat status ikut terhapus (cascade)
            }

            Order::withoutEvents(fn () => app(DemoOrderSeeder::class)->run());
            app(DemoReviewSeeder::class)->run();
        });

        $this->resync($sold, $reviews);

        return self::SUCCESS;
    }

    private function resync(SoldCountService $sold, ReviewService $reviews): void
    {
        $sold->sync();
        Product::query()->each(fn (Product $product) => $reviews->syncSummary($product));

        $this->components->twoColumnDetail('Pesanan', (string) Order::count());
        $this->components->twoColumnDetail('Ulasan tayang', (string) ProductReview::approved()->count());
        $this->components->twoColumnDetail('Produk sudah terjual', (string) Product::where('sold_count', '>', 0)->count());
        $this->components->twoColumnDetail('Produk berulasan', (string) Product::where('review_count', '>', 0)->count());
    }
}
