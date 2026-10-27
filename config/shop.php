<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identitas Toko
    |--------------------------------------------------------------------------
    */

    'name' => 'MGA',
    'full_name' => 'Media Gudang Acc',
    'tagline' => 'Gudangnya perlengkapan promosi & display akrilik',
    'whatsapp' => env('SHOP_WHATSAPP'),
    // Tombol WhatsApp melayang di halaman pelanggan (tampil bila nomor terisi).
    'whatsapp_button' => (bool) env('SHOP_WHATSAPP_BUTTON', true),
    // Pesan pembuka yang otomatis terisi saat pelanggan membuka chat.
    'whatsapp_message' => env('SHOP_WHATSAPP_MESSAGE', 'Halo, saya mau tanya tentang produk di toko online.'),
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
        'hero_eyebrow' => 'Perlengkapan promosi usaha',
        'hero_title' => 'Bikin promosi tokomu lebih menonjol.',
        'hero_subtitle' => 'Tripod banner, frame akrilik, tempat brosur, dan light box langsung dari gudang. Harga grosir untuk UMKM, kafe, dan event.',
        'hero_primary_label' => 'Belanja sekarang',
        'hero_primary_url' => '/produk',
        'hero_secondary_label' => 'Lihat promo',
        'hero_secondary_url' => '/produk?promo=1',
        'hero_text' => 'light',
        'announcements' => [
            'Gudang Jakarta & Surabaya, kirim ke seluruh Indonesia',
            'Pesan sebelum 16.00 WIB, dikirim hari ini',
            'Bayar mudah: transfer bank, e-wallet & QRIS',
        ],
        // Tab Best Seller: "auto" (terlaris 90 hari) atau "manual" (pilihan admin, berurutan).
        'best_seller_mode' => 'auto',
        'best_seller_products' => [],
        'best_seller_fill' => true,
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

    /*
    |--------------------------------------------------------------------------
    | Pajak, Biaya & Invoice
    |--------------------------------------------------------------------------
    |
    | Semua nilai di sini bisa diubah dari Admin › Pajak & Biaya; isi di sini
    | hanya nilai bawaan. Angka yang sudah tersimpan di pesanan tidak ikut
    | berubah bila pengaturan diganti (pesanan menyimpan salinannya).
    |
    | tax_mode: "exclusive" (PPN ditambahkan ke tagihan pembeli) atau
    | "inclusive" (harga produk sudah termasuk PPN, hanya dirinci di invoice).
    | tax_dpp: "full" (DPP = harga jual) atau "other" (DPP nilai lain 11/12,
    | dipakai bersama tarif 12% sesuai PMK 131/2024).
    | payment_methods: tarif biaya Midtrans per metode. charge_to "buyer" =
    | ditambahkan ke tagihan pembeli, "store" = ditanggung toko.
    | rounding_mode: none | nearest | up | down, ke kelipatan rounding_unit.
    | invoice_format: token {PREFIX} {YYYY} {YY} {MM} {DD} {SEQ} {ORDER}.
    |
    */

    'billing' => [
        'tax_enabled' => false,
        'tax_label' => 'PPN',
        'tax_rate' => 11,
        'tax_dpp' => 'full',
        'tax_mode' => 'exclusive',
        'tax_shipping' => false,
        'tax_price_note' => true,

        'service_fee' => 0,
        'service_fee_label' => 'Biaya layanan',

        'payment_fee_label' => 'Biaya transaksi',
        'payment_fee_gross_up' => false,
        'payment_fee_vat' => 0,
        'payment_methods' => [],

        'rounding_mode' => 'none',
        'rounding_unit' => 100,

        'invoice_prefix' => 'INV',
        'invoice_format' => '{PREFIX}/{YYYY}/{MM}/{SEQ}',
        'invoice_padding' => 5,
        'invoice_reset' => 'yearly',
        'invoice_issue_on' => 'paid',
        'company_name' => null,
        'company_npwp' => null,
        'company_address' => null,
        'company_phone' => null,
        'company_email' => null,
        'is_pkp' => false,
        'buyer_tax_info' => true,
        'invoice_notes' => null,
        'invoice_footer' => 'Terima kasih telah berbelanja. Invoice ini dibuat otomatis oleh sistem dan sah tanpa tanda tangan basah.',
        'signatory_name' => null,
        'signatory_title' => null,
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
        'description' => env('SEO_DESCRIPTION', 'Belanja perlengkapan promosi & display akrilik: tripod banner, X banner, frame akrilik, tempat brosur, standing sign, light box. Harga gudang, kirim ke seluruh Indonesia.'),
        'default_image' => env('SEO_DEFAULT_IMAGE') ?: 'images/brand/og-default.png',
    ],

];
