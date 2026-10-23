<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('shop:check')]
#[Description('Periksa apakah konfigurasi server sudah siap produksi')]
class ProductionCheckCommand extends Command
{
    /** @var array<int, array{0: string, 1: string, 2: string}> */
    protected array $rows = [];

    public function handle(): int
    {
        $production = app()->isProduction();
        $https = str_starts_with((string) config('app.url'), 'https://');

        $this->check('APP_ENV = production', $production, 'Set APP_ENV=production');
        $this->check('APP_DEBUG mati', ! config('app.debug'), 'Set APP_DEBUG=false (debug membocorkan data sensitif)');
        $this->check('APP_KEY terisi', filled(config('app.key')), 'Jalankan php artisan key:generate');
        $this->check('APP_URL memakai https', $https, 'Pasang SSL lalu set APP_URL=https://domain-anda', warn: true);
        $this->check('Koneksi database', $this->databaseWorks(), 'Periksa DB_* di .env');
        $this->check('Link storage (public/storage)', is_link(public_path('storage')) || is_dir(public_path('storage')), 'Jalankan php artisan storage:link');
        $this->check('Folder storage bisa ditulis', is_writable(storage_path('framework')) && is_writable(storage_path('logs')), 'chmod -R 775 storage bootstrap/cache');
        $this->check('Folder bootstrap/cache bisa ditulis', is_writable(base_path('bootstrap/cache')), 'chmod -R 775 bootstrap/cache');
        $this->check('Aset sudah di-build', is_file(public_path('build/manifest.json')), 'Jalankan npm ci && npm run build (atau upload folder public/build)');
        $this->check('Config di-cache', app()->configurationIsCached(), 'Jalankan php artisan optimize', warn: true);
        $this->check('Cookie sesi hanya lewat HTTPS', ! $https || config('session.secure'), 'Set SESSION_SECURE_COOKIE=true', warn: true);
        $this->check('Queue bukan "sync"', config('queue.default') !== 'sync', 'Set QUEUE_CONNECTION=database (email tidak memperlambat checkout)', warn: true);
        $this->check('Mailer sungguhan', ! in_array(config('mail.default'), ['log', 'array'], true), 'Isi MAIL_* dengan SMTP asli', warn: true);
        $this->check('LOG_LEVEL bukan debug', config('logging.channels.daily.level', 'debug') !== 'debug' || ! $production, 'Set LOG_LEVEL=warning', warn: true);
        $this->check('Payment bukan driver "fake"', config('shop.payment.driver') !== 'fake', 'Set PAYMENT_DRIVER=midtrans');
        $this->check('Ongkir bukan driver "fake"', config('shop.shipping.driver') !== 'fake', 'Set SHIPPING_DRIVER=rajaongkir');

        if (config('shop.payment.driver') === 'midtrans') {
            $this->check('Kunci Midtrans terisi', filled(config('services.midtrans.server_key')) && filled(config('services.midtrans.client_key')), 'Isi MIDTRANS_SERVER_KEY & MIDTRANS_CLIENT_KEY');
            $this->check('Midtrans mode produksi', (bool) config('services.midtrans.is_production'), 'Set MIDTRANS_IS_PRODUCTION=true saat siap terima uang asli', warn: true);
        }

        if (config('shop.shipping.driver') === 'rajaongkir') {
            $this->check('RajaOngkir siap', filled(config('services.rajaongkir.api_key')) && filled(config('shop.shipping.origin_city_id')), 'Isi RAJAONGKIR_API_KEY & SHOP_ORIGIN_CITY_ID');
        }

        $this->table(['Status', 'Pemeriksaan', 'Saran'], $this->rows);

        $failed = collect($this->rows)->where(0, '<fg=red>GAGAL</>')->count();

        if ($failed > 0) {
            $this->components->error("{$failed} pemeriksaan wajib belum lolos.");

            return self::FAILURE;
        }

        $this->components->info('Konfigurasi wajib sudah siap produksi.');

        return self::SUCCESS;
    }

    protected function check(string $label, bool $passed, string $hint, bool $warn = false): void
    {
        $this->rows[] = match (true) {
            $passed => ['<fg=green>OK</>', $label, ''],
            $warn => ['<fg=yellow>SARAN</>', $label, $hint],
            default => ['<fg=red>GAGAL</>', $label, $hint],
        };
    }

    protected function databaseWorks(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
