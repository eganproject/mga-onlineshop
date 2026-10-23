<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup
    |--------------------------------------------------------------------------
    |
    | `php artisan shop:backup` membuat dump database (gzip) dan, bila
    | include_uploads true, arsip foto produk (storage/app/public).
    | File disimpan di disk "disk" pada folder "path". Gunakan disk s3 /
    | penyimpanan lain supaya backup tidak hilang bersama servernya.
    |
    */

    'enabled' => (bool) env('BACKUP_ENABLED', true),

    'disk' => env('BACKUP_DISK', 'local'),

    'path' => env('BACKUP_PATH', 'backups'),

    'include_uploads' => (bool) env('BACKUP_INCLUDE_UPLOADS', true),

    // Backup lebih tua dari ini dihapus otomatis.
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),

    // Jam backup harian (zona waktu aplikasi).
    'time' => env('BACKUP_TIME', '02:00'),

    // Path biner mysqldump bila tidak ada di PATH (mis. /usr/bin/mysqldump).
    'mysqldump' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),

];
