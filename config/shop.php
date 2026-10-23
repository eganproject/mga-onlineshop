<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identitas Toko
    |--------------------------------------------------------------------------
    */

    'name' => 'MGA',
    'full_name' => 'Media Gudang Acc',
    'tagline' => 'Gudangnya aksesoris gadget original & terjangkau',
    'whatsapp' => env('SHOP_WHATSAPP'),
    // Alamat gudang/pengirim, dicetak di invoice dan packing slip.
    'address' => env('SHOP_ADDRESS'),
    'instagram' => env('SHOP_INSTAGRAM'),
    'tiktok' => env('SHOP_TIKTOK'),
    'facebook' => env('SHOP_FACEBOOK'),
    'email' => env('SHOP_EMAIL'),

    // Nilai identitas, kontak, pengiriman & pembayaran di file ini bisa ditimpa
    // dari Admin → Pengaturan Toko (disimpan di tabel `settings`).

    // Lama log aktivitas admin disimpan (hari) sebelum dibersihkan `model:prune`.
    'activity_log_days' => (int) env('ACTIVITY_LOG_DAYS', 365),

    /*
    |--------------------------------------------------------------------------
    | Tampilan Toko
    |--------------------------------------------------------------------------
    |
    | Semua nilai di sini bisa diubah dari Admin › Tampilan Beranda; isi di
    | sini/.env hanya nilai bawaan.
    | hero_image / hero_image_mobile: path di disk public, path di folder
    | public/, atau URL. Kosong = ilustrasi bawaan MGA.
    | hero_text: "light" (teks putih, untuk foto gelap) atau "dark".
    | announcements: teks yang bergantian di bar paling atas.
    |
    */

    'storefront' => [
        'hero_image' => env('SHOP_HERO_IMAGE'),
        'hero_image_mobile' => env('SHOP_HERO_IMAGE_MOBILE'),
        'hero_eyebrow' => 'Koleksi terbaru MGA',
        'hero_title' => 'Aksesoris original, harga gudang.',
        'hero_subtitle' => 'Casing, charger, kabel, dan audio yang awet dipakai setiap hari.',
        'hero_primary_label' => 'Belanja sekarang',
        'hero_primary_url' => '/produk',
        'hero_secondary_label' => 'Lihat promo',
        'hero_secondary_url' => '/produk?promo=1',
        'hero_text' => 'light',
        'announcements' => [
            'Kirim ke seluruh Indonesia',
            'Produk 100% original & bergaransi',
            'Bayar mudah: transfer bank, e-wallet & QRIS',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pengiriman
    |--------------------------------------------------------------------------
    |
    | origin_district_id: ID kecamatan gudang asal pengiriman sesuai data
    | RajaOngkir. Cari ID-nya dengan `php artisan ongkir:wilayah`.
    | Driver "fake" dipakai untuk development lokal tanpa API key.
    | cache_minutes: lama hasil cek ongkir disimpan (hemat kuota API).
    |
    */

    'shipping' => [
        'driver' => env('SHIPPING_DRIVER', 'fake'),
        // SHOP_ORIGIN_CITY_ID tetap dibaca untuk kompatibilitas .env lama.
        'origin_district_id' => env('SHOP_ORIGIN_DISTRICT_ID', env('SHOP_ORIGIN_CITY_ID')),
        // Nama wilayah asal (diisi otomatis saat dipilih di Pengaturan Toko).
        'origin_label' => env('SHOP_ORIGIN_LABEL'),
        'couriers' => array_values(array_filter(explode(':', env('RAJAONGKIR_COURIERS', 'jne:sicepat:jnt:pos:tiki')))),
        'cache_minutes' => (int) env('SHIPPING_CACHE_MINUTES', 360),
        // Lacak resi: lama hasil disimpan, dan apakah dicek otomatis terjadwal.
        'tracking_cache_minutes' => (int) env('TRACKING_CACHE_MINUTES', 60),
        'tracking_sync' => (bool) env('TRACKING_SYNC', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pesanan
    |--------------------------------------------------------------------------
    |
    | auto_complete_days: pesanan "Sampai Tujuan" otomatis selesai setelah
    | sekian hari bila pelanggan tidak menekan "Pesanan diterima".
    | shipped_auto_complete_days: batas aman untuk pesanan "Dikirim" yang
    | tidak pernah terdeteksi sampai (0 = nonaktif).
    | cancel_reasons: pilihan alasan saat pelanggan membatalkan pesanan.
    |
    */

    'orders' => [
        'auto_complete_days' => (int) env('ORDER_AUTO_COMPLETE_DAYS', 3),
        'shipped_auto_complete_days' => (int) env('ORDER_SHIPPED_AUTO_COMPLETE_DAYS', 14),
        'cancel_reasons' => [
            'Ingin mengubah pesanan (varian, jumlah, atau alamat)',
            'Ingin mengganti metode pembayaran',
            'Menemukan harga yang lebih murah',
            'Tidak jadi membeli',
            'Lainnya',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pembayaran
    |--------------------------------------------------------------------------
    |
    | Driver "fake" membuat tagihan yang bisa disimulasikan lunas (khusus lokal).
    | Driver "midtrans" memakai Midtrans Snap.
    | expiry_hours: batas bayar sejak checkout; lewat dari itu pesanan dibatalkan
    | otomatis oleh `shop:expire-payments` dan stok dikembalikan.
    |
    */

    'payment' => [
        'driver' => env('PAYMENT_DRIVER', 'fake'),
        'expiry_hours' => (int) env('PAYMENT_EXPIRY_HOURS', 24),
    ],

    'low_stock_threshold' => 5,

    /*
    |--------------------------------------------------------------------------
    | Ulasan Produk
    |--------------------------------------------------------------------------
    |
    | Ulasan hanya bisa ditulis pembeli yang pesanannya sudah selesai.
    | auto_approve=false: ulasan baru menunggu moderasi admin dulu.
    |
    */

    'reviews' => [
        'auto_approve' => (bool) env('REVIEWS_AUTO_APPROVE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | SEO
    |--------------------------------------------------------------------------
    |
    | indexing=false (mis. di server staging) membuat robots.txt menolak semua
    | crawler dan setiap halaman diberi meta noindex.
    |
    */

    'seo' => [
        'indexing' => (bool) env('SEO_INDEXING', true),
        'description' => env('SEO_DESCRIPTION', 'Belanja aksesoris HP & gadget original: casing, charger, kabel data, earphone, powerbank. Harga terjangkau, kirim ke seluruh Indonesia.'),
        'default_image' => env('SEO_DEFAULT_IMAGE') ?: 'images/brand/og-default.png',
    ],

];
