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
    case CatalogManage = 'catalog.manage';
    case StockManage = 'stock.manage';
    case PromosManage = 'promos.manage';
    case ArticlesManage = 'articles.manage';
    case ReviewsManage = 'reviews.manage';
    case CustomersView = 'customers.view';
    case CustomersManage = 'customers.manage';
    case ReportsView = 'reports.view';
    case SettingsManage = 'settings.manage';
    case IntegrationsManage = 'integrations.manage';
    case StaffManage = 'staff.manage';
    case ActivityView = 'activity.view';

    public function label(): string
    {
        return match ($this) {
            self::OrdersView => 'Lihat pesanan, invoice & packing slip',
            self::OrdersManage => 'Proses pesanan (status & resi)',
            self::CatalogManage => 'Kelola produk & kategori',
            self::StockManage => 'Kelola stok',
            self::PromosManage => 'Kelola promo, voucher & banner',
            self::ArticlesManage => 'Tulis & terbitkan artikel blog',
            self::ReviewsManage => 'Moderasi ulasan',
            self::CustomersView => 'Lihat data pelanggan',
            self::CustomersManage => 'Blokir / aktifkan pelanggan',
            self::ReportsView => 'Lihat laporan & angka penjualan',
            self::SettingsManage => 'Ubah pengaturan toko',
            self::IntegrationsManage => 'Atur integrasi & kunci API (Midtrans, RajaOngkir, Firebase)',
            self::StaffManage => 'Kelola akun staf',
            self::ActivityView => 'Lihat log aktivitas',
        };
    }
}
