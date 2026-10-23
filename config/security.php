<?php

return [

    /*
    |--------------------------------------------------------------------------
    | HTTPS & Proxy
    |--------------------------------------------------------------------------
    |
    | force_https: paksa semua URL yang dibuat Laravel memakai https://.
    | trusted_proxies: IP load balancer / reverse proxy (pisahkan dengan koma),
    | atau "*" bila server hanya bisa diakses lewat proxy (mis. Cloudflare
    | Tunnel). Kosongkan bila PHP langsung menerima request dari internet.
    |
    */

    'force_https' => (bool) env('FORCE_HTTPS', false),

    'trusted_proxies' => env('TRUSTED_PROXIES'),

    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    |
    | Header ditambahkan ke setiap respons web oleh middleware SecurityHeaders.
    | HSTS hanya dikirim lewat koneksi HTTPS. CSP opsional karena Alpine.js &
    | Midtrans Snap butuh kebijakan yang longgar; isi bila sudah diuji.
    |
    */

    'headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(self "https://app.midtrans.com" "https://app.sandbox.midtrans.com")',
        'Cross-Origin-Opener-Policy' => 'same-origin-allow-popups',
    ],

    'hsts' => [
        'enabled' => (bool) env('SECURITY_HSTS', true),
        'max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
        'include_subdomains' => (bool) env('SECURITY_HSTS_SUBDOMAINS', false),
    ],

    'csp' => env('SECURITY_CSP'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting (percobaan per menit)
    |--------------------------------------------------------------------------
    */

    'rate_limits' => [
        // Per kombinasi email + IP, dan batas total per IP.
        'login' => (int) env('RATE_LIMIT_LOGIN', 5),
        'login_per_ip' => (int) env('RATE_LIMIT_LOGIN_IP', 20),
        'register' => (int) env('RATE_LIMIT_REGISTER', 5),
        'password_reset' => (int) env('RATE_LIMIT_PASSWORD_RESET', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Antrean via Scheduler (shared hosting)
    |--------------------------------------------------------------------------
    |
    | Shared hosting biasanya tidak bisa menjalankan proses `queue:work`
    | permanen. Bila true, scheduler (cron tiap menit) memproses antrean
    | sampai kosong. Di VPS pakai Supervisor dan biarkan false.
    |
    */

    'queue_via_scheduler' => (bool) env('QUEUE_VIA_SCHEDULER', false),

];
