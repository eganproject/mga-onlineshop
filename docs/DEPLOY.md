# Panduan Deploy MGA Online Shop

Panduan ini untuk dua jenis server tanpa Docker:

- **A. VPS** (Ubuntu 24.04, Nginx, PHP-FPM, MySQL). Disarankan: lebih cepat dan antrean email berjalan terus.
- **B. Shared hosting** (cPanel/hPanel/DirectAdmin). Lebih murah, ada beberapa batasan.

Setelah selesai, selalu jalankan `php artisan shop:check` untuk memastikan konfigurasi sudah aman.

---

## Kebutuhan server

| Komponen | Versi |
|---|---|
| PHP | 8.3 atau lebih baru |
| Ekstensi PHP | bcmath, ctype, curl, dom, fileinfo, gd, intl, mbstring, openssl, pdo_mysql, tokenizer, xml, zip |
| Database | MySQL 8 / MariaDB 10.6+ |
| Composer | 2.x |
| Node.js | 20+ (hanya untuk build aset; di shared hosting boleh build di komputer sendiri) |
| Lainnya | `mysqldump` dan `tar` untuk backup |

---

## A. Deploy di VPS

### 1. Siapkan paket

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server supervisor git unzip \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath php8.3-redis
# Composer
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
# Node.js 22
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - && sudo apt install -y nodejs
```

Rapikan PHP produksi di `/etc/php/8.3/fpm/php.ini`:

```ini
expose_php = Off
upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 256M
opcache.enable = 1
opcache.validate_timestamps = 0   ; deploy.sh me-reload PHP-FPM setelah update
```

### 2. Buat database

```bash
sudo mysql
```

```sql
CREATE DATABASE mga_onlineshop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'mga'@'localhost' IDENTIFIED BY 'ganti-password-kuat';
GRANT ALL PRIVILEGES ON mga_onlineshop.* TO 'mga'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Ambil kode & pasang aplikasi

```bash
sudo mkdir -p /var/www && sudo chown $USER:www-data /var/www
cd /var/www
git clone https://github.com/eganproject/mga-onlineshop.git
cd mga-onlineshop

cp .env.production.example .env
nano .env                       # isi semua bagian <...>
php artisan key:generate        # simpan salinan APP_KEY di tempat aman

composer install --no-dev --prefer-dist --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --force     # opsional: data awal + akun admin contoh
php artisan storage:link
php artisan optimize

sudo chown -R $USER:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

> Bila memakai seeder, **segera ganti** password akun `admin@mga.test` dan nonaktifkan akun contoh `gudang@mga.test` / `cs@mga.test` di **Admin → Staf** (atau buat Owner baru lalu nonaktifkan akun contoh).

### 4. Nginx + SSL

```bash
sudo cp deploy/nginx.conf.example /etc/nginx/sites-available/mga
sudo nano /etc/nginx/sites-available/mga          # ganti domain & path
sudo ln -s /etc/nginx/sites-available/mga /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx

sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d tokomga.com -d www.tokomga.com
```

### 5. Worker antrean (Supervisor)

Antrean dipakai untuk email dan pekerjaan latar supaya checkout tetap cepat.

```bash
sudo cp deploy/supervisor-worker.conf.example /etc/supervisor/conf.d/mga-worker.conf
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status
```

### 6. Cron (scheduler)

```bash
sudo crontab -u www-data -e
```

Tambahkan satu baris:

```
* * * * * cd /var/www/mga-onlineshop && php artisan schedule:run >> /dev/null 2>&1
```

Lihat jadwalnya dengan `php artisan schedule:list`.

### 7. Update berikutnya

```bash
cd /var/www/mga-onlineshop
bash deploy/deploy.sh
```

Skrip ini: mode perawatan → `git pull` → composer → build aset → migrasi → `optimize` → restart worker → situs aktif lagi → `shop:check`.

---

## B. Deploy di shared hosting (cPanel)

Pastikan paket hosting punya **akses SSH/Terminal**, **PHP 8.3**, dan **Cron Jobs**.

### 1. Siapkan file di komputer sendiri

Shared hosting biasanya tidak punya Node.js, jadi build aset di lokal:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

Kompres seluruh folder proyek (termasuk `vendor/` dan `public/build/`, **tanpa** `node_modules/` dan `.env`), lalu upload dan ekstrak di luar `public_html`, misalnya `/home/user/mga-onlineshop`.

### 2. Arahkan domain ke folder `public`

- **Bisa ubah document root** (cPanel → Domains): arahkan ke `/home/user/mga-onlineshop/public`. Ini cara terbaik.
- **Tidak bisa**: ekstrak aplikasi langsung di `public_html`, lalu salin `deploy/htaccess-root.example` menjadi `public_html/.htaccess`. File ini meneruskan semua request ke `public/` dan memblokir akses ke `.env`, `vendor`, dsb.

### 3. Database & .env

1. cPanel → MySQL Databases: buat database, user, lalu beri user akses **ALL PRIVILEGES**.
2. Salin `.env.production.example` menjadi `.env`, isi DB_* (nama database & user biasanya diawali nama akun cPanel).
3. Set `QUEUE_VIA_SCHEDULER=true` (tidak ada Supervisor di shared hosting).
4. Bila `mysqldump` tidak tersedia, isi `BACKUP_MYSQLDUMP_PATH` atau gunakan backup bawaan cPanel.

Lewat Terminal cPanel:

```bash
cd ~/mga-onlineshop
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan optimize
php artisan shop:check
```

> Bila perintah `php` menunjuk versi lama, pakai path lengkap, contoh `/usr/local/bin/ea-php83` atau `/opt/alt/php83/usr/bin/php`.

### 4. Cron Job

cPanel → Cron Jobs → *Once Per Minute*:

```
cd /home/user/mga-onlineshop && php artisan schedule:run >> /dev/null 2>&1
```

Dengan `QUEUE_VIA_SCHEDULER=true`, cron ini juga memproses antrean email setiap menit.

### 5. Update berikutnya

Build ulang di lokal, upload file yang berubah (`app/`, `resources/`, `routes/`, `public/build/`, `vendor/` bila ada paket baru), lalu:

```bash
php artisan down
php artisan migrate --force
php artisan optimize
php artisan up
```

---

## Keamanan yang sudah aktif

- **Security headers** di setiap halaman (`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, dan HSTS lewat HTTPS). Atur di `config/security.php`.
- **Pembatasan percobaan**: login 5×/menit per email+IP (20×/menit per IP), daftar 5×/menit, lupa/reset password 3×/menit. Lewat batas → halaman 429.
- **HTTPS**: `FORCE_HTTPS=true` memaksa semua URL ke https. Di belakang Cloudflare/load balancer isi `TRUSTED_PROXIES` supaya IP pengunjung & https terbaca benar.
- **Password** pelanggan di produksi minimal 8 karakter berisi huruf dan angka.
- **Perintah berbahaya** (`migrate:fresh`, `db:wipe`, dll) diblokir saat `APP_ENV=production`.
- Cookie sesi terenkripsi dan hanya lewat HTTPS (`SESSION_ENCRYPT`, `SESSION_SECURE_COOKIE`).

## Backup & restore

Backup otomatis berjalan tiap hari jam `BACKUP_TIME` (lewat cron scheduler):

```bash
php artisan shop:backup               # backup sekarang (database + foto)
php artisan shop:backup --no-uploads  # database saja
```

Hasilnya di disk `BACKUP_DISK` folder `BACKUP_PATH` (default `storage/app/private/backups`), contoh `db-2026-10-23_020000.sql.gz` dan `uploads-2026-10-23_020000.tar.gz`. Backup lebih tua dari `BACKUP_KEEP_DAYS` hari dihapus otomatis.

**Simpan backup di luar server.** Isi `AWS_*` (S3, Cloudflare R2, atau layanan S3-compatible lokal), pasang paket `composer require league/flysystem-aws-s3-v3`, lalu set `BACKUP_DISK=s3`. Minimal, unduh berkala file di folder backup.

Restore:

```bash
php artisan down
gunzip -c db-2026-10-23_020000.sql.gz | mysql -u mga -p mga_onlineshop
tar -xzf uploads-2026-10-23_020000.tar.gz -C storage/app
php artisan optimize:clear && php artisan optimize
php artisan up
```

## Log error

- Error disimpan per hari di `storage/logs/laravel-YYYY-MM-DD.log`, disimpan `LOG_DAILY_DAYS` hari.
- `LOG_LEVEL=warning` supaya log tidak penuh info biasa.
- Notifikasi error kritis ke Slack: isi `LOG_SLACK_WEBHOOK_URL` lalu ubah `LOG_STACK=daily,slack`.
- Lihat log terbaru: `tail -f storage/logs/laravel-$(date +%F).log`.
- Health check untuk monitor uptime (mis. UptimeRobot): `https://domain-anda/up`.

## Checklist sebelum go-live

- [ ] `php artisan shop:check` tanpa status **GAGAL**
- [ ] SSL aktif dan `APP_URL` memakai `https://`
- [ ] Password akun admin contoh sudah diganti
- [ ] Kunci Midtrans produksi terisi, URL notifikasi di dashboard Midtrans: `https://domain-anda/webhooks/midtrans`
- [ ] API key RajaOngkir & `SHOP_ORIGIN_CITY_ID` terisi
- [ ] Email SMTP bisa terkirim (coba fitur lupa password)
- [ ] Cron berjalan (`php artisan schedule:list`), worker antrean aktif
- [ ] `php artisan shop:backup` berhasil dan file backup bisa diunduh

## Masalah umum

| Gejala | Solusi |
|---|---|
| Halaman putih / error 500 | Cek `storage/logs`, pastikan `storage` & `bootstrap/cache` bisa ditulis |
| Foto produk tidak muncul | `php artisan storage:link`, pastikan `APP_URL` benar |
| Tampilan tanpa CSS | Folder `public/build` belum ada: `npm run build` lalu upload |
| Perubahan `.env` tidak terbaca | `php artisan optimize:clear && php artisan optimize` |
| Email tidak terkirim | Worker/cron belum jalan, atau MAIL_* salah. Lihat tabel `failed_jobs` |
| Error 419 saat login | Cek `SESSION_DOMAIN`, dan `SESSION_SECURE_COOKIE=true` hanya bila sudah https |
| Selalu redirect ke http / loop | Isi `TRUSTED_PROXIES` bila di belakang Cloudflare/proxy |
