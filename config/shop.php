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
    | origin_city_id adalah ID kota/kecamatan asal pengiriman sesuai data
    | RajaOngkir. Driver "fake" dipakai untuk development lokal tanpa API key.
    |
    */

    'shipping' => [
        'driver' => env('SHIPPING_DRIVER', 'fake'),
        'origin_city_id' => env('SHOP_ORIGIN_CITY_ID'),
        'couriers' => explode(':', env('RAJAONGKIR_COURIERS', 'jne:sicepat:jnt:pos:tiki')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pembayaran
    |--------------------------------------------------------------------------
    |
    | Driver "fake" menandai pembayaran langsung berhasil (khusus lokal).
    | Driver "midtrans" memakai Midtrans Snap.
    |
    */

    'payment' => [
        'driver' => env('PAYMENT_DRIVER', 'fake'),
        'expiry_hours' => 24,
    ],

    'low_stock_threshold' => 5,

];
