# MGA Online Shop

Toko online **Media Gudang Acc (MGA)** berbasis Laravel 13, MySQL, Tailwind CSS v4, dan Alpine.js.

## Fitur saat ini

- **Toko**: beranda, katalog (cari, filter kategori, urutkan), detail produk, keranjang (bisa tanpa login), checkout.
- **Akun pelanggan**: ringkasan, riwayat & detail pesanan (progres status, bayar, batal), buku alamat, profil & password.
- **Admin panel** (`/admin`): dashboard (pendapatan, grafik 7 hari, stok menipis), produk, kategori, pesanan (ubah status + nomor resi), pelanggan (blokir/aktifkan), status integrasi.
- **Auth**: login, daftar, lupa/reset password. Dua peran: `admin` dan `customer` (pendaftaran publik selalu `customer`).
- **Siap integrasi**: Midtrans (pembayaran) dan RajaOngkir (ongkir) lewat interface + driver yang dipilih di `.env`.

## Instalasi

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
# buat database MySQL "mga_onlineshop", sesuaikan DB_* di .env
php artisan migrate --seed
php artisan storage:link
npm run dev        # atau npm run build
php artisan serve
```

Akun contoh dari seeder (password: `password`):

| Peran | Email |
|---|---|
| Admin | `admin@mga.test` |
| Pelanggan | `pelanggan@mga.test` |

Jalankan test: `php artisan test`

## Arsitektur singkat

```
app/
├── Contracts/          PaymentGateway, ShippingProvider (interface)
├── Data/               ShippingRate, Destination (DTO)
├── Enums/              UserRole, OrderStatus (+ aturan transisi), PaymentStatus
├── Http/Controllers/
│   ├── Admin/          panel admin
│   ├── Account/        area pelanggan
│   ├── Auth/           login, daftar, reset password
│   ├── Shop/           beranda, produk, keranjang, checkout
│   └── Webhooks/       notifikasi Midtrans
├── Http/Middleware/    EnsureUserHasRole  (route: ->middleware('role:admin'))
└── Services/
    ├── Cart/           CartService (keranjang berbasis session)
    ├── Orders/         CheckoutService (kunci & kurangi stok), OrderService (status)
    ├── Payment/        FakePaymentGateway, MidtransPaymentGateway
    └── Shipping/       FakeShippingProvider, RajaOngkirShippingProvider
```

- Harga disimpan sebagai rupiah utuh (integer), berat produk dalam gram.
- Pesanan menyimpan salinan alamat & harga saat checkout, jadi riwayat tidak berubah.
- Ongkir selalu dihitung ulang di server saat pesanan dibuat.
- Warna brand diatur di `resources/css/app.css` (`--color-brand-*`).

## Integrasi Midtrans & RajaOngkir

Default `.env` memakai driver `fake` agar alur checkout bisa dicoba tanpa API key: ongkir memakai tarif contoh dan pembayaran bisa disimulasikan dari halaman pesanan (non-production).

Untuk mengaktifkan:

```env
PAYMENT_DRIVER=midtrans
MIDTRANS_SERVER_KEY=...
MIDTRANS_CLIENT_KEY=...
MIDTRANS_IS_PRODUCTION=false

SHIPPING_DRIVER=rajaongkir
RAJAONGKIR_API_KEY=...
SHOP_ORIGIN_CITY_ID=...   # ID lokasi gudang di RajaOngkir
```

URL notifikasi Midtrans: `https://domain-anda/webhooks/midtrans` (juga tampil di Admin › Integrasi).

> Kode Midtrans (Snap + verifikasi signature webhook) dan RajaOngkir (API Komerce) sudah ditulis tetapi belum diuji dengan API key asli.

## Deploy ke produksi

Panduan lengkap (VPS & shared hosting, tanpa Docker): **[docs/DEPLOY.md](docs/DEPLOY.md)**.

- `.env.production.example`: contoh `.env` server produksi.
- `deploy/`: contoh konfigurasi Nginx, Supervisor, `.htaccess` shared hosting, dan skrip `deploy.sh`.
- `php artisan shop:check`: cek kesiapan konfigurasi produksi.
- `php artisan shop:backup`: backup database + foto produk (otomatis tiap hari lewat scheduler).
- Cron wajib: `* * * * * cd /path/app && php artisan schedule:run >> /dev/null 2>&1`
