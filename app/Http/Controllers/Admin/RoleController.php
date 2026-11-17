<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\Activity\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin › Jabatan & Hak Akses: atur izin tiap jabatan dan buat jabatan baru.
 * Khusus Superadmin (Gate roles.manage). Superadmin sendiri selalu punya semua
 * izin dan tidak bisa dihapus, jadi panel admin tidak mungkin terkunci.
 */
class RoleController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::query()->ordered()->withCount('users')->get(),
            'groups' => Permission::grouped(),
        ]);
    }

    public function create(Request $request): View
    {
        $role = new Role(['name' => '']);

        // "Salin dari" jabatan lain sebagai titik awal.
        if ($source = Role::findByKey($request->string('salin')->value() ?: null)) {
            $role->fill([
                'description' => $source->description,
                'color' => $source->isOwner() ? 'stone' : $source->color,
                'permissions' => array_map(fn (Permission $p) => $p->value, $source->permissionList()),
            ]);
        }

        return view('admin.roles.form', ['role' => $role, 'source' => $source, 'groups' => Permission::grouped()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $role = new Role([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'color' => $data['color'],
            'permissions' => $this->permissionsFrom($request),
            'sort_order' => (int) Role::query()->max('sort_order') + 1,
        ]);
        $role->key = Role::makeKey($data['name']);
        $role->save();

        $this->logger->log('roles.store', 'Membuat jabatan', $role, ['jabatan' => $role->name, 'izin' => $role->permissions]);

        return redirect()->route('admin.roles.index')->with('success', "Jabatan {$role->name} dibuat. Pilih jabatan ini di Admin › Staf untuk akun yang bersangkutan.");
    }

    public function edit(Role $role): View
    {
        return view('admin.roles.form', ['role' => $role, 'source' => null, 'groups' => Permission::grouped()]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $this->validated($request, $role);
        $before = $role->permissions ?? [];

        $role->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'color' => $data['color'],
        ]);
        // Superadmin selalu semua izin; kolomnya tidak dipakai untuk pengecekan.
        if (! $role->isOwner()) {
            $role->permissions = $this->permissionsFrom($request);
        }
        $role->save();

        $after = $role->permissions ?? [];
        $this->logger->log('roles.update', 'Mengubah jabatan', $role, array_filter([
            'jabatan' => $role->name,
            'izin_ditambah' => array_values(array_diff($after, $before)) ?: null,
            'izin_dicabut' => array_values(array_diff($before, $after)) ?: null,
        ]));

        return redirect()->route('admin.roles.index')->with('success', "Hak akses {$role->name} disimpan. Berlaku langsung untuk semua staf berjabatan ini.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->isOwner()) {
            return back()->with('error', 'Jabatan Superadmin tidak bisa dihapus.');
        }

        $count = $role->users()->count();
        if ($count > 0) {
            return back()->with('error', "Jabatan {$role->name} masih dipakai {$count} akun staf. Pindahkan dulu ke jabatan lain di Admin › Staf.");
        }

        $role->delete();
        $this->logger->log('roles.destroy', 'Menghapus jabatan', null, ['jabatan' => $role->name]);

        return redirect()->route('admin.roles.index')->with('success', "Jabatan {$role->name} dihapus.");
    }

    /** Saklar izin dikirim sebagai permissions[NamaCase] = 0/1. */
    private function permissionsFrom(Request $request): array
    {
        return Permission::normalize(array_filter(Permission::cases(), fn (Permission $p) => $request->boolean("permissions.{$p->name}")));
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('staff_roles', 'name')->ignore($role)],
            'description' => ['nullable', 'string', 'max:255'],
            'color' => ['required', Rule::in(array_keys(Role::COLORS))],
        ], [
            'name.unique' => 'Sudah ada jabatan dengan nama ini.',
        ], ['name' => 'nama jabatan', 'description' => 'keterangan', 'color' => 'warna']);
    }
}
