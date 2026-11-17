<?php

namespace App\Enums;

/**
 * Hak akses panel admin. Nilainya dipakai sebagai nama Gate, jadi di route
 * cukup ->middleware('can:orders.manage') dan di Blade @can('orders.manage').
 */
enum Permission: string
{
    case OrdersView = 'orders.view';
    case OrdersManage = 'orders.manage';
    case OrdersCreate = 'orders.create';
    case CatalogManage = 'catalog.manage';
    case StockManage = 'stock.manage';
    case PromosManage = 'promos.manage';
    case ArticlesManage = 'articles.manage';
    case ReviewsManage = 'reviews.manage';
    case CustomersView = 'customers.view';
    case CustomersManage = 'customers.manage';
    case ReportsView = 'reports.view';
    case SettingsManage = 'settings.manage';
    case BillingManage = 'billing.manage';
    case IntegrationsManage = 'integrations.manage';
    case StaffManage = 'staff.manage';
    case ActivityView = 'activity.view';

    public function label(): string
    {
        return match ($this) {
            self::OrdersView => 'Lihat pesanan, invoice & packing slip',
            self::OrdersManage => 'Proses pesanan (status & resi)',
            self::OrdersCreate => 'Buat penjualan manual & tagihan pembayaran (QRIS/VA)',
            self::CatalogManage => 'Kelola produk & kategori',
            self::StockManage => 'Kelola stok',
            self::PromosManage => 'Kelola promo, voucher & banner',
            self::ArticlesManage => 'Tulis & terbitkan artikel blog',
            self::ReviewsManage => 'Moderasi ulasan',
            self::CustomersView => 'Lihat data pelanggan',
            self::CustomersManage => 'Blokir / aktifkan pelanggan',
            self::ReportsView => 'Lihat laporan & angka penjualan',
            self::SettingsManage => 'Ubah pengaturan toko',
            self::BillingManage => 'Atur pajak (PPN), biaya transaksi & invoice',
            self::IntegrationsManage => 'Atur integrasi & kunci API (Midtrans, RajaOngkir, Firebase)',
            self::StaffManage => 'Kelola akun staf',
            self::ActivityView => 'Lihat log aktivitas',
        };
    }

    /** Kelompok di halaman Jabatan & Hak Akses. */
    public function group(): string
    {
        return match ($this) {
            self::OrdersView, self::OrdersManage, self::OrdersCreate => 'Pesanan',
            self::CatalogManage, self::StockManage, self::ReviewsManage => 'Katalog & stok',
            self::PromosManage, self::ArticlesManage => 'Promosi & konten',
            self::CustomersView, self::CustomersManage => 'Pelanggan',
            self::ReportsView, self::ActivityView => 'Laporan & log',
            self::SettingsManage, self::BillingManage, self::IntegrationsManage, self::StaffManage => 'Pengaturan & keamanan',
        };
    }

    /** Menu/halaman yang terbuka dengan izin ini (untuk keterangan di halaman jabatan). */
    public function hint(): string
    {
        return match ($this) {
            self::OrdersView => 'Menu Pesanan: daftar & detail pesanan, cetak invoice dan packing slip.',
            self::OrdersManage => 'Ubah status pesanan dan input nomor resi.',
            self::OrdersCreate => 'Penjualan manual, kartu tagihan QRIS/VA dan tombol kirim ke gudang di detail pesanan.',
            self::CatalogManage => 'Menu Produk & Kategori, termasuk impor/ekspor produk.',
            self::StockManage => 'Menu Stok: ubah stok, impor stok massal, sinkron gudang.',
            self::PromosManage => 'Menu Voucher, Banner & Tampilan Beranda.',
            self::ArticlesManage => 'Menu Artikel & Kategori Artikel.',
            self::ReviewsManage => 'Menu Ulasan: setujui, sembunyikan atau hapus ulasan.',
            self::CustomersView => 'Menu Pelanggan: data, alamat & riwayat belanja.',
            self::CustomersManage => 'Tombol blokir / aktifkan akun pelanggan.',
            self::ReportsView => 'Menu Laporan dan angka pendapatan di dashboard.',
            self::SettingsManage => 'Menu Pengaturan Toko & SEO.',
            self::BillingManage => 'Menu Pajak & Biaya (PPN, biaya layanan, tarif Midtrans, invoice).',
            self::IntegrationsManage => 'Menu Integrasi: kunci API Midtrans, RajaOngkir, Firebase & gudang.',
            self::StaffManage => 'Menu Staf: tambah akun, reset password, nonaktifkan staf (kecuali akun Superadmin).',
            self::ActivityView => 'Menu Log Aktivitas: siapa mengubah apa dan kapan.',
        };
    }

    /** Izin sensitif (kunci API, akun staf): beri hanya ke orang yang benar-benar dipercaya. */
    public function isSensitive(): bool
    {
        return in_array($this, [self::IntegrationsManage, self::StaffManage], true);
    }

    /**
     * Izin lain yang otomatis ikut, karena halamannya berada di dalam menu itu
     * (mis. memproses pesanan perlu bisa membuka detail pesanan).
     *
     * @return list<self>
     */
    public function requires(): array
    {
        return match ($this) {
            self::OrdersManage, self::OrdersCreate => [self::OrdersView],
            self::CustomersManage => [self::CustomersView],
            default => [],
        };
    }

    /**
     * Rapikan daftar izin dari form: buang yang tidak dikenal, tambah yang wajib ikut, urut sesuai daftar.
     *
     * @param  iterable<string|self>  $values
     * @return list<string>
     */
    public static function normalize(iterable $values): array
    {
        $chosen = [];
        foreach ($values as $value) {
            $permission = $value instanceof self ? $value : self::tryFrom((string) $value);
            if ($permission) {
                $chosen[$permission->value] = true;
                foreach ($permission->requires() as $required) {
                    $chosen[$required->value] = true;
                }
            }
        }

        return array_values(array_filter(array_map(fn (self $p) => $p->value, self::cases()), fn (string $v) => isset($chosen[$v])));
    }

    /** @return array<string, list<self>> */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::cases() as $permission) {
            $groups[$permission->group()][] = $permission;
        }

        return $groups;
    }
}
