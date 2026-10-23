# MGA Online Shop

Toko online **Media Gudang Acc (MGA)** berbasis Laravel 13, MySQL, Tailwind CSS v4, dan Alpine.js.

## Fitur saat ini

- **Toko**: beranda, katalog (cari, filter kategori, urutkan), detail produk, keranjang (bisa tanpa login), checkout.
- **Akun pelanggan**: ringkasan, riwayat & detail pesanan (progres status, bayar, batal), buku alamat, profil & password.
- **Admin panel** (`/admin`): dashboard (pendapatan, grafik 7 hari, stok menipis), produk, kategori, pesanan (ubah status + nomor resi), pelanggan (blokir/aktifkan), status integrasi.
- **Wishlist & ulasan**: pelanggan menyimpan produk ke wishlist; pembeli dengan pesanan selesai bisa memberi rating & ulasan, tayang setelah dimoderasi admin (`/admin/reviews`).
- **Email** (lewat antrean): selamat datang, verifikasi email, reset password, pesanan diterima, dan perubahan status pesanan (dibayar, dikirim + resi, selesai, dibatalkan).
- **SEO**: meta description, canonical, Open Graph/Twitter card, JSON-LD (Product, BreadcrumbList, WebSite), `/sitemap.xml` dan `/robots.txt` dinamis. Halaman akun, keranjang, admin, dan hasil filter otomatis `noindex`.
- **Auth**: login, daftar (dengan verifikasi email), lupa/reset password. Dua peran: `admin` dan `customer` (pendaftaran publik selalu `customer`).
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
| Owner (akses penuh) | `admin@mga.test` |
| Staf Gudang | `gudang@mga.test` |
| Customer Service | `cs@mga.test` |
| Pelanggan | `pelanggan@mga.test` |

Panel admin punya jabatan staf (Owner, Admin, Staf Gudang, Customer Service).
Hak akses tiap jabatan ada di `App\Enums\StaffRole::permissions()`; route admin
dijaga middleware `can:<izin>` dan menu sidebar ikut menyembunyikan yang tidak
boleh dibuka. Owner mengelola akun di **Admin → Staf**; semua perubahan staf
tercatat di **Admin → Log Aktivitas**.

Identitas toko, kontak, media sosial, kecamatan asal, kurir, batas bayar, dan
cek resi otomatis diatur di **Admin → Pengaturan Toko** (disimpan di tabel
`settings`, menimpa nilai `.env`). Kunci API tetap di `.env`.

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
    └── Shipping/       ShippingService (cache & urutkan ongkir), FakeShippingProvider, RajaOngkirShippingProvider
```

- Harga disimpan sebagai rupiah utuh (integer), berat produk dalam gram.
- Pesanan menyimpan salinan alamat & harga saat checkout, jadi riwayat tidak berubah.
- Alamat memakai wilayah bertingkat provinsi → kota → kecamatan; nama wilayah diambil dari penyedia ongkir, bukan dari form.
- Ongkir dihitung per kecamatan, disimpan di cache (`SHIPPING_CACHE_MINUTES`, default 360) dan selalu dihitung ulang di server saat pesanan dibuat.
- Warna brand diatur di `resources/css/app.css` (`--color-brand-*`).
- Email memakai Mailable/Notification `ShouldQueue`, jadi butuh worker antrean (`php artisan queue:work`). Email status pesanan dikirim dari `App\Observers\OrderObserver`. Tampilan email: `resources/views/mail` dan tema di `resources/views/vendor/mail`.
- Ulasan diubah lewat `App\Services\Reviews\ReviewService` agar `products.rating_avg` & `review_count` selalu sinkron. `REVIEWS_AUTO_APPROVE=true` untuk melewati moderasi.
- `SEO_INDEXING=false` (mis. di server staging) membuat seluruh situs `noindex` dan robots.txt menolak crawler.

## Login Google untuk pelanggan (Firebase)

Pelanggan bisa masuk/daftar dengan tombol **Lanjutkan dengan Google** (Firebase Authentication). Staf tetap memakai email + password. Tombol hanya tampil bila `FIREBASE_API_KEY` dan `FIREBASE_PROJECT_ID` diisi; tidak perlu service account. Langkah setup: [docs/FIREBASE.md](docs/FIREBASE.md).

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
SHOP_ORIGIN_DISTRICT_ID=...   # ID kecamatan gudang di RajaOngkir
```

Cari ID kecamatan gudang setelah API key diisi:

```bash
php artisan ongkir:wilayah                 # daftar provinsi
php artisan ongkir:wilayah --provinsi=ID   # daftar kota
php artisan ongkir:wilayah --kota=ID       # daftar kecamatan (salin ID-nya)
```

Data wilayah RajaOngkir disimpan di cache 30 hari agar hemat kuota.

URL notifikasi Midtrans: `https://domain-anda/webhooks/midtrans` (juga tampil di Admin › Integrasi). Di dashboard Midtrans isi juga *Finish Redirect URL* bebas; aplikasi sudah mengirim URL kembali per transaksi.

### Alur pembayaran

- Setelah checkout pelanggan diarahkan ke halaman **Pembayaran** (`/akun/pesanan/{kode}/bayar`) yang membuka Midtrans Snap.
- Batas bayar pesanan = `PAYMENT_EXPIRY_HOURS` (default 24 jam) sejak checkout; tagihan Snap diberi kedaluwarsa yang sama.
- Webhook diverifikasi dengan `signature_key` (SHA-512) dan nominalnya dicocokkan. Notifikasi ganda/tidak berurutan diabaikan, jadi aman di-retry Midtrans.
- Pembayaran gagal/ditolak tidak membatalkan pesanan: pelanggan bisa **Bayar ulang** / **Ganti metode pembayaran** (link baru dibuat, link lama dibatalkan di Midtrans).
- Pesanan yang lewat batas bayar dibatalkan oleh `php artisan shop:expire-payments` (terjadwal tiap 5 menit, butuh cron scheduler) dan stoknya dikembalikan. Status dicek dulu ke Midtrans agar pembayaran yang webhook-nya terlambat tidak ikut batal.
- Saat pesanan dibatalkan (pelanggan, admin, atau kedaluwarsa) tagihan yang masih pending ikut dibatalkan di Midtrans.
- Tombol **Cek status** di halaman pembayaran menanyakan status langsung ke Midtrans, berguna di lokal karena webhook tidak bisa menjangkau `localhost`.

> RajaOngkir (API Komerce) dan Midtrans sudah diuji dengan respons tiruan di test; uji sekali lagi dengan key sandbox sebelum produksi.

## Deploy ke produksi

Panduan lengkap (VPS & shared hosting, tanpa Docker): **[docs/DEPLOY.md](docs/DEPLOY.md)**.

- `.env.production.example`: contoh `.env` server produksi.
- `deploy/`: contoh konfigurasi Nginx, Supervisor, `.htaccess` shared hosting, dan skrip `deploy.sh`.
- `php artisan shop:check`: cek kesiapan konfigurasi produksi.
- `php artisan shop:backup`: backup database + foto produk (otomatis tiap hari lewat scheduler).
- Cron wajib: `* * * * * cd /path/app && php artisan schedule:run >> /dev/null 2>&1`
