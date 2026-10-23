<?php

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Enums\ImportType;
use App\Models\ProductImport;
use App\Services\Catalog\Transfer\Importer;
use App\Services\Catalog\Transfer\ProductImporter;
use App\Services\Catalog\Transfer\StockImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** Validasi atau jalankan impor. File kecil diproses langsung, file besar lewat antrean. */
class ProcessProductImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 1800;

    public function __construct(public ProductImport $import, public bool $commit = false) {}

    public function handle(): void
    {
        $import = $this->import;
        $expected = $this->commit ? ImportStatus::Importing : ImportStatus::Validating;

        // File besar bisa diproses lebih lama dari retry_after antrean. Kunci ini
        // mencegah worker lain mengambil ulang impor yang sedang berjalan.
        $lock = Cache::lock("product-import:{$import->id}", $this->timeout + 60);
        if (! $lock->get()) {
            return;
        }

        try {
            $import->refresh();
            if ($import->status !== $expected) {
                return;
            }

            $importer = self::importer($import);

            if ($this->commit) {
                // Impor yang pernah mulai lalu terhenti tidak diulang (mode tambah stok bisa dobel).
                if ($import->result['started'] ?? false) {
                    $importer->fail($import, 'Impor terhenti di tengah jalan. Periksa riwayat stok & daftar produk sebelum mengimpor ulang.');

                    return;
                }
                $import->forceFill(['result' => ['started' => true]])->save();
            }

            // Supaya riwayat stok mencatat admin yang mengunggah, juga saat jalan di antrean.
            if ($import->user && Auth::guard()->guest()) {
                Auth::guard()->setUser($import->user);
            }

            $this->commit ? $importer->commit($import) : $importer->validate($import);
        } finally {
            $lock->release();
        }
    }

    public function failed(?Throwable $e): void
    {
        $import = $this->import->refresh();

        if ($import->status->isRunning()) {
            self::importer($import)->fail($import, 'Proses impor terhenti. Silakan coba unggah ulang.');
        }
    }

    public static function importer(ProductImport $import): Importer
    {
        return app($import->type === ImportType::Stock ? StockImporter::class : ProductImporter::class);
    }
}
