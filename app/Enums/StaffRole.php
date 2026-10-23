<?php

namespace App\Enums;

/** Jabatan akun staf (users.role = admin). Menentukan menu & aksi yang boleh diakses. */
enum StaffRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Warehouse = 'warehouse';
    case CustomerService = 'cs';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Superadmin',
            self::Admin => 'Admin',
            self::Warehouse => 'Staf Gudang',
            self::CustomerService => 'Customer Service',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Owner => 'Akses penuh, termasuk akun staf dan kunci API integrasi (Midtrans, RajaOngkir, Firebase).',
            self::Admin => 'Semua menu toko kecuali akun staf dan integrasi.',
            self::Warehouse => 'Memproses & mengirim pesanan, input resi, dan mengelola stok.',
            self::CustomerService => 'Melihat pesanan & pelanggan, serta memoderasi ulasan.',
        };
    }

    /** @return list<Permission> */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => Permission::cases(),
            self::Admin => array_values(array_filter(Permission::cases(), fn (Permission $p) => ! in_array($p, [Permission::StaffManage, Permission::IntegrationsManage], true))),
            self::Warehouse => [Permission::OrdersView, Permission::OrdersManage, Permission::StockManage],
            self::CustomerService => [Permission::OrdersView, Permission::CustomersView, Permission::ReviewsManage],
        };
    }

    public function allows(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Owner => 'bg-stone-900 text-white ring-stone-900/20',
            self::Admin => 'bg-brand-50 text-brand-700 ring-brand-600/20',
            self::Warehouse => 'bg-amber-50 text-amber-700 ring-amber-600/20',
            self::CustomerService => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        };
    }
}
