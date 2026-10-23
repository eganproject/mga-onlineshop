# Login pelanggan dengan Google (Firebase)

Pelanggan bisa masuk/daftar dengan satu klik **"Lanjutkan dengan Google"** di
halaman `/masuk` dan `/daftar`. Akun staf/admin tetap masuk dengan email +
password (login Google untuk akun staf ditolak).

Selama `FIREBASE_API_KEY` dan `FIREBASE_PROJECT_ID` kosong, tombol Google
tidak muncul dan login biasa tetap jalan seperti sebelumnya.

## 1. Buat project Firebase

1. Buka <https://console.firebase.google.com> → **Add project** (Buat project).
   Nama bebas, mis. `mga-onlineshop`. Google Analytics boleh dimatikan.
2. Paket **Spark (gratis)** sudah cukup untuk login Google.

## 2. Aktifkan login Google

1. Menu **Build → Authentication → Get started**.
2. Tab **Sign-in method → Add new provider → Google → Enable**.
3. Isi **Project support email** (email kamu) → **Save**.

## 3. Daftarkan domain toko

**Authentication → Settings → Authorized domains → Add domain**, tambahkan:

- `mga-onlineshop.test` (Laragon lokal)
- domain toko asli, mis. `mgaacc.com` dan `www.mgaacc.com`

`localhost` biasanya sudah ada. Tanpa langkah ini popup Google menampilkan
error *unauthorized-domain*.

## 4. Ambil konfigurasi web app

1. **Project settings** (ikon ⚙️) → tab **General** → bagian **Your apps** →
   klik ikon **`</>`** (Web). Nama bebas, **tidak perlu** Firebase Hosting.
2. Firebase menampilkan `firebaseConfig`. Salin ke `.env`:

```env
FIREBASE_API_KEY=AIza...          # apiKey
FIREBASE_AUTH_DOMAIN=mga-onlineshop.firebaseapp.com   # authDomain
FIREBASE_PROJECT_ID=mga-onlineshop                    # projectId
FIREBASE_APP_ID=1:1234567890:web:abcdef               # appId
```

Nilai ini memang tampil di browser (bukan rahasia), keamanannya dijaga oleh
daftar *Authorized domains*. **Tidak perlu** service account / file JSON:
server memverifikasi token memakai sertifikat publik Google.

Lalu jalankan `php artisan config:clear` (atau `php artisan config:cache` di
server produksi).

## Cara kerja

1. Pelanggan klik tombol → popup Google (Firebase JS SDK, dimuat hanya saat
   tombol diklik).
2. Browser mengirim *ID token* Firebase ke `POST /masuk/google`.
3. Server memverifikasi token (`App\Services\Auth\FirebaseTokenVerifier`:
   tanda tangan RS256, `aud`/`iss` = Project ID, masa berlaku).
4. `App\Services\Auth\FirebaseLoginService` mencari akun:
   - sudah pernah login Google (`users.firebase_uid`) → langsung masuk;
   - email sama dengan akun pelanggan lama → akun ditautkan, data & pesanan
     tetap sama;
   - belum ada → akun pelanggan baru dibuat (email langsung terverifikasi,
     password acak; pelanggan bisa membuat password lewat "Lupa password").
   - email milik akun staf atau akun nonaktif → ditolak.

## Masalah umum

| Pesan | Solusi |
| --- | --- |
| Domain ini belum diizinkan di Firebase | Tambahkan domain di *Authorized domains* (langkah 3). |
| Login Google belum diaktifkan di Firebase | Aktifkan provider Google (langkah 2). |
| Popup diblokir browser | Izinkan popup untuk situs toko. |
| "Gagal masuk dengan Google" setelah popup | Cek `FIREBASE_PROJECT_ID` sama persis dengan project di Console, server bisa mengakses `www.googleapis.com`, dan jam server benar. Detail ada di `storage/logs`. |
