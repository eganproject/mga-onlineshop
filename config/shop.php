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
