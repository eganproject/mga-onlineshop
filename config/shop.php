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

    /*
    |--------------------------------------------------------------------------
    | Tampilan Toko
    |--------------------------------------------------------------------------
    |
    | hero_image: path/URL gambar besar di beranda (opsional). Jika kosong,
    | beranda memakai tampilan tipografi + foto produk unggulan.
    | announcements: teks yang bergulir di bar paling atas.
    |
    */

    'storefront' => [
        'hero_image' => env('SHOP_HERO_IMAGE'),
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
