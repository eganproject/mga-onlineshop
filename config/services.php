<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'merchant_id' => env('MIDTRANS_MERCHANT_ID'),
        'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
    ],

    // Login pelanggan dengan Google lewat Firebase Authentication. Nilai di
    // bawah adalah konfigurasi web app Firebase (bukan rahasia). Tombol login
    // hanya tampil bila login_enabled aktif serta API key dan Project ID terisi
    // (`enabled` dihitung ulang oleh StoreSettings setelah nilai dari database).
    // Kunci Midtrans, RajaOngkir & Firebase bisa diatur superadmin di
    // Admin › Integrasi; nilai di file ini/.env menjadi cadangan.
    'firebase' => [
        'login_enabled' => (bool) env('FIREBASE_LOGIN_ENABLED', true),
        'enabled' => (bool) env('FIREBASE_LOGIN_ENABLED', true) && filled(env('FIREBASE_API_KEY')) && filled(env('FIREBASE_PROJECT_ID')),
        'api_key' => env('FIREBASE_API_KEY'),
        'auth_domain' => env('FIREBASE_AUTH_DOMAIN') ?: (env('FIREBASE_PROJECT_ID') ? env('FIREBASE_PROJECT_ID').'.firebaseapp.com' : null),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'app_id' => env('FIREBASE_APP_ID'),
    ],

    'rajaongkir' => [
        'api_key' => env('RAJAONGKIR_API_KEY'),
        'base_url' => env('RAJAONGKIR_BASE_URL', 'https://rajaongkir.komerce.id/api/v1'),
    ],

    // Sinkron stok dari sistem gudang warehouse29 (GET /api/v1/stocks, token
    // Bearer). Bisa diatur superadmin di Admin › Integrasi.
    'warehouse' => [
        'enabled' => (bool) env('WAREHOUSE_SYNC_ENABLED', false),
        'base_url' => env('WAREHOUSE_API_URL'),
        'token' => env('WAREHOUSE_API_TOKEN'),
        'interval' => (int) env('WAREHOUSE_SYNC_INTERVAL', 5),
        'timeout' => (int) env('WAREHOUSE_API_TIMEOUT', 20),
    ],

];
