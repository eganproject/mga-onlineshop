<?php

namespace App\Console\Commands;

use App\Services\Catalog\MgaCatalog;
use App\Services\Catalog\ProductService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

/**
 * Isi katalog dengan produk asli MGA (database/data/mga-catalog.json).
 *   php artisan shop:import-mga --images            tambah produk + unduh foto
 *   php artisan shop:import-mga --images --replace  sekalian hapus produk demo lama
 * Aman dijalankan ulang: produk yang sudah ada tidak ditimpa.
 */
#[Signature('shop:import-mga
    {--images : Unduh foto produk dari situs MGA ke storage}
    {--replace : Hapus produk & kategori lain (mis. data demo). Produk yang pernah dipesan hanya dinonaktifkan}
    {--stock=25 : Stok awal untuk produk/varian baru}
    {--force : Jangan minta konfirmasi}')]
#[Description('Impor katalog produk MGA (dari toko Shopee gudang.acc) beserta fotonya')]
class ImportMgaCatalogCommand extends Command
{
    public function handle(MgaCatalog $catalog, ProductService $products): int
    {
        $total = count($catalog->slugs());

        if ($this->option('replace') && ! $this->option('force')
            && ! confirm('Produk & kategori yang bukan katalog MGA akan dihapus (yang pernah dipesan hanya dinonaktifkan). Lanjutkan?', default: false)) {
            $this->components->warn('Dibatalkan, tidak ada yang diubah.');

            return self::FAILURE;
        }

        if ($this->option('replace')) {
            $removed = $catalog->removeOthers($products);
            $this->components->info("Produk lama: {$removed['deleted']} dihapus, {$removed['deactivated']} dinonaktifkan (punya pesanan). Kategori kosong dihapus: {$removed['categories']}.");
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $result = $catalog->import(
            withImages: (bool) $this->option('images'),
            initialStock: max(0, (int) $this->option('stock')),
            progress: fn () => $bar->advance(),
        );

        $bar->finish();
        $this->newLine(2);

        $this->components->twoColumnDetail('Kategori baru', (string) $result['categories']);
        $this->components->twoColumnDetail('Produk baru', (string) $result['created']);
        $this->components->twoColumnDetail('Produk sudah ada (dilewati)', (string) $result['skipped']);
        if ($this->option('images')) {
            $this->components->twoColumnDetail('Foto diunduh', (string) $result['images']);
        }

        foreach (array_slice($result['warnings'], 0, 20) as $warning) {
            $this->components->warn($warning);
        }
        if (count($result['warnings']) > 20) {
            $this->components->warn('... dan '.(count($result['warnings']) - 20).' peringatan lain.');
        }

        if (! $this->option('images')) {
            $this->components->info('Foto belum diunduh. Jalankan lagi dengan --images di server yang punya akses internet.');
        }
        $this->components->info('Produk tanpa harga masuk sebagai nonaktif. Isi harganya di Admin › Produk lalu aktifkan.');

        return self::SUCCESS;
    }
}
