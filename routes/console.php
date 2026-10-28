<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Jadwal tugas
|--------------------------------------------------------------------------
|
| Aktifkan dengan satu cron di server (lihat docs/DEPLOY.md):
| * * * * * cd /path/ke/app && php artisan schedule:run >> /dev/null 2>&1
|
*/

// Backup database + foto produk setiap hari, backup lama dibersihkan.
Schedule::command('shop:backup')
    ->dailyAt(config('backup.time'))
    ->when(fn () => config('backup.enabled'))
    ->withoutOverlapping()
    ->onOneServer();

// Pesanan yang lewat batas bayar dibatalkan & stoknya dikembalikan. Status
// dicek dulu ke Midtrans agar pembayaran yang webhook-nya terlambat tidak hilang.
Schedule::command('shop:expire-payments')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Bersihkan data kedaluwarsa.
Schedule::command('auth:clear-resets')->everyFifteenMinutes();
Schedule::command('queue:prune-failed --hours=336')->daily();
Schedule::command('queue:prune-batches --hours=48')->daily();
Schedule::command('model:prune')->daily();

// Shared hosting tanpa Supervisor: proses antrean lewat cron tiap menit.
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')
    ->everyMinute()
    ->when(fn () => config('security.queue_via_scheduler'))
    ->withoutOverlapping()
    ->runInBackground();

// Pesanan: cek resi yang sedang dikirim, lalu selesaikan yang sudah sampai.
Schedule::command('orders:sync-tracking')
    ->everyThreeHours()
    ->when(fn () => config('shop.shipping.tracking_sync'))
    ->withoutOverlapping()
    ->onOneServer();
Schedule::command('orders:auto-complete')->hourly()->withoutOverlapping()->onOneServer();

// Stok dari warehouse29: tarik yang berubah sesuai interval (menit), plus
// tarikan penuh tiap malam sebagai pengaman.
Schedule::command('warehouse:sync-stock')
    ->everyMinute()
    ->when(fn () => config('services.warehouse.enabled')
        && now()->minute % max(1, min(60, (int) config('services.warehouse.interval', 5))) === 0)
    ->withoutOverlapping(15)
    ->onOneServer();
Schedule::command('warehouse:sync-stock --full')
    ->dailyAt('02:40')
    ->when(fn () => config('services.warehouse.enabled'))
    ->withoutOverlapping(30)
    ->onOneServer();
