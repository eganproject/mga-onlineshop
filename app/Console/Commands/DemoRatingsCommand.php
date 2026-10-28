<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Reviews\DemoRatingBooster;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

/**
 * Naikkan rating produk di data demo tanpa reset database. Hanya ulasan akun
 * contoh (@mga.test) yang diubah; pesanan, produk, stok, dan semua pengaturan
 * (Integrasi, Pengaturan Toko, dll.) tidak disentuh.
 *   php artisan shop:demo-ratings --dry-run      lihat dulu apa yang akan berubah
 *   php artisan shop:demo-ratings --force        jalankan tanpa konfirmasi
 *   php artisan shop:demo-ratings --all --force  ikutkan ulasan akun lain (mis. akun uji coba di situs demo)
 */
#[Signature('shop:demo-ratings
    {--min=4.7 : Rata-rata rating minimal per produk (1-5)}
    {--all : Ikutkan ulasan dari akun mana pun, bukan hanya akun contoh @mga.test}
    {--dry-run : Hanya tampilkan perubahan, tidak menyimpan apa pun}
    {--force : Jangan minta konfirmasi}')]
#[Description('Naikkan rating ulasan contoh (demo) supaya rata-rata tiap produk mencapai batas minimal')]
class DemoRatingsCommand extends Command
{
    public function handle(DemoRatingBooster $booster): int
    {
        if (app()->isProduction()) {
            $this->components->error('Ulasan tidak boleh diubah di produksi. Perintah ini hanya untuk data demo (APP_ENV selain production).');

            return self::FAILURE;
        }

        $min = filter_var($this->option('min'), FILTER_VALIDATE_FLOAT);
        if ($min === false || $min < 1 || $min > 5) {
            $this->components->error('--min harus angka 1 sampai 5, mis. --min=4.7');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $all = (bool) $this->option('all');
        $preview = $booster->boost($min, dryRun: true, anyAuthor: $all);

        if ($preview === []) {
            $this->components->info('Semua produk berulasan sudah rata-rata '.$min.' ke atas. Tidak ada yang diubah.');

            return self::SUCCESS;
        }

        $this->table(
            ['Produk', 'Sebelum', 'Sesudah', 'Ulasan diubah'],
            array_map(fn (array $r) => [
                str($r['product']->name)->limit(50),
                number_format($r['before'], 2),
                number_format($r['after'], 2).($r['reached'] ? '' : ' (tertahan ulasan akun lain)'),
                $r['changed'],
            ], $preview),
        );

        $changes = array_sum(array_column($preview, 'changed'));
        $stuck = count(array_filter($preview, fn (array $r) => ! $r['reached']));
        if ($stuck > 0 && ! $all) {
            $this->components->warn("{$stuck} produk tidak bisa mencapai {$min} hanya dengan ulasan akun contoh @mga.test. Di situs demo, tambahkan --all supaya ulasan akun lain (mis. akun uji coba) ikut dinaikkan.");
        }
        if ($dryRun) {
            $this->components->info("Mode uji: {$changes} ulasan contoh akan dinaikkan jadi bintang 5. Belum ada yang disimpan.");

            return self::SUCCESS;
        }

        if (! $this->option('force')
            && ! confirm("{$changes} ulasan contoh akan dinaikkan jadi bintang 5. Lanjutkan?", default: true)) {
            $this->components->warn('Dibatalkan, tidak ada yang diubah.');

            return self::FAILURE;
        }

        $results = $booster->boost($min, anyAuthor: $all);
        $this->components->info(array_sum(array_column($results, 'changed')).' ulasan contoh dinaikkan jadi bintang 5.');

        $below = Product::where('review_count', '>', 0)->where('rating_avg', '<', round($min, 1))->count();
        $this->components->twoColumnDetail('Rating terendah sekarang', number_format((float) Product::where('review_count', '>', 0)->min('rating_avg'), 1, ',', '.'));
        if ($below > 0) {
            $this->components->warn("{$below} produk masih di bawah {$min} karena ulasannya dari akun selain @mga.test (tidak diubah, pakai --all untuk ikut menaikkan).");
        }

        return self::SUCCESS;
    }
}
