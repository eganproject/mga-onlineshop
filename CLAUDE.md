# MGA Online Shop — catatan untuk agent

- Laravel 13, PHP 8.3+, MySQL (test pakai SQLite in-memory), Tailwind v4 + Alpine.js, Blade (tanpa Livewire/Inertia).
- Bahasa UI: Indonesia. Locale `id`, timezone `Asia/Jakarta`.
- Peran user: `App\Enums\UserRole` (admin/customer). Route dilindungi middleware `role:admin` / `role:customer`.
- Kolom `role` dan `is_active` sengaja tidak mass-assignable; ubah dengan `forceFill`.
- Pembayaran & ongkir lewat interface `App\Contracts\PaymentGateway` dan `App\Contracts\ShippingProvider`; driver dipilih di `AppServiceProvider` dari `config/shop.php`.
- Perubahan status pesanan lewat `App\Services\Orders\OrderService` (aturan transisi ada di `OrderStatus::nextStatuses()`).
- Komponen Blade: `resources/views/components` (layout `x-layouts.shop|admin|account|auth`, `x-icon`, `x-product-card`, dll). Kelas utilitas `.btn`, `.input`, `.card`, `.badge` di `resources/css/app.css`.
- Jalankan `php artisan test` sebelum commit.
