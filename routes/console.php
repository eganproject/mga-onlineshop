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
