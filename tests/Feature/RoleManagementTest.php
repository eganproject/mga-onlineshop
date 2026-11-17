<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\StaffRole;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    /** Isi awal tabel staff_roles harus sama persis dengan aturan lama di enum StaffRole. */
    public function test_seeded_roles_match_previous_hardcoded_permissions(): void
    {
        foreach (StaffRole::cases() as $case) {
            $role = Role::findByKey($case->value);
            $this->assertNotNull($role, $case->value);
            $this->assertSame($case->label(), $role->name);
            $this->assertEqualsCanonicalizing(
                array_map(fn (Permission $p) => $p->value, $case->permissions()),
                array_map(fn (Permission $p) => $p->value, $role->permissionList()),
                $case->value,
            );
        }
    }

    public function test_only_superadmin_can_open_roles_page(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))->assertSee(route('admin.roles.index'));
        $this->get(route('admin.roles.index'))->assertOk()->assertSee('Staf Gudang')->assertSee('Customer Service');
        $this->get(route('admin.roles.edit', 'warehouse'))->assertOk();
        $this->get(route('admin.roles.edit', 'owner'))->assertOk()->assertSee('selalu punya semua hak akses');
        $this->get(route('admin.roles.create'))->assertOk();
        $this->get(route('admin.roles.create', ['salin' => 'cs']))->assertOk();

        // Admin punya semua izin lain, bahkan staff.manage bila diberikan, tetap tidak bisa mengatur jabatan.
        Role::findByKey('admin')->update(['permissions' => array_map(fn ($p) => $p->value, Permission::cases())]);
        $admin = User::factory()->staff(StaffRole::Admin)->create();
        $this->actingAs($admin)->get(route('admin.roles.index'))->assertForbidden();
        $this->put(route('admin.roles.update', 'admin'), ['name' => 'Admin', 'color' => 'brand'])->assertForbidden();
        $this->get(route('admin.dashboard'))->assertDontSee(route('admin.roles.index'));
    }

    public function test_changing_permissions_applies_to_staff_immediately(): void
    {
        $owner = User::factory()->admin()->create();
        $staff = User::factory()->staff(StaffRole::Warehouse)->create();

        $this->actingAs($staff)->get(route('admin.products.index'))->assertForbidden();

        $this->actingAs($owner)->put(route('admin.roles.update', 'warehouse'), [
            'name' => 'Staf Gudang',
            'color' => 'amber',
            'permissions' => ['OrdersView' => '1', 'OrdersManage' => '1', 'StockManage' => '1', 'CatalogManage' => '1', 'ReportsView' => '0'],
        ])->assertRedirect(route('admin.roles.index'));

        $this->assertDatabaseHas('activity_logs', ['action' => 'roles.update', 'user_id' => $owner->id]);

        $this->actingAs($staff->fresh())->get(route('admin.products.index'))->assertOk();
        $this->get(route('admin.reports.index'))->assertForbidden();

        // Cabut lagi: langsung ditolak.
        $this->actingAs($owner)->put(route('admin.roles.update', 'warehouse'), [
            'name' => 'Staf Gudang', 'color' => 'amber', 'permissions' => ['StockManage' => '1'],
        ]);
        $this->actingAs($staff->fresh())->get(route('admin.products.index'))->assertForbidden();
        $this->get(route('admin.orders.index'))->assertForbidden();
        $this->get(route('admin.stock.index'))->assertOk();
    }

    public function test_dependent_permissions_are_added_and_unknown_ones_ignored(): void
    {
        $this->actingAs(User::factory()->admin()->create())->put(route('admin.roles.update', 'cs'), [
            'name' => 'Customer Service', 'color' => 'sky',
            'permissions' => ['OrdersManage' => '1', 'CustomersManage' => '1', 'Bogus' => '1'],
        ]);

        $this->assertSame(['orders.view', 'orders.manage', 'customers.view', 'customers.manage'], Role::findByKey('cs')->permissions);
    }

    public function test_create_custom_role_and_assign_it_to_staff(): void
    {
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)->post(route('admin.roles.store'), [
            'name' => 'Marketing',
            'description' => 'Promo dan artikel',
            'color' => 'violet',
            'permissions' => ['PromosManage' => '1', 'ArticlesManage' => '1'],
        ])->assertRedirect(route('admin.roles.index'));

        $role = Role::query()->where('name', 'Marketing')->firstOrFail();
        $this->assertSame('marketing', $role->key);
        $this->assertSame(['promos.manage', 'articles.manage'], $role->permissions);

        // Nama sama ditolak.
        $this->post(route('admin.roles.store'), ['name' => 'Marketing', 'color' => 'rose'])->assertSessionHasErrors('name');

        $this->get(route('admin.staff.create'))->assertOk()->assertSee('Marketing');
        $this->post(route('admin.staff.store'), [
            'name' => 'Mira', 'email' => 'mira@mga.test', 'staff_role' => 'marketing',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])->assertRedirect(route('admin.staff.index'));

        $mira = User::where('email', 'mira@mga.test')->firstOrFail();
        $this->assertSame('marketing', $mira->staff_role);
        $this->assertSame('Marketing', $mira->staffRole()->label());

        $this->actingAs($mira)->get(route('admin.articles.index'))->assertOk();
        $this->get(route('admin.vouchers.index'))->assertOk();
        $this->get(route('admin.orders.index'))->assertForbidden();
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Marketing')->assertDontSee(route('admin.orders.index'));

        // Jabatan yang tidak ada ditolak.
        $this->actingAs($owner)->post(route('admin.staff.store'), [
            'name' => 'X', 'email' => 'x@mga.test', 'staff_role' => 'tidak_ada',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors('staff_role');
    }

    public function test_owner_role_keeps_full_access_and_cannot_be_deleted(): void
    {
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)->put(route('admin.roles.update', 'owner'), [
            'name' => 'Pemilik', 'color' => 'dark', 'permissions' => ['OrdersView' => '1'],
        ])->assertRedirect();

        $role = Role::findByKey('owner');
        $this->assertSame('Pemilik', $role->name);
        $this->assertCount(count(Permission::cases()), $role->permissionList());
        $this->assertTrue($owner->fresh()->can('staff.manage'));
        $this->assertTrue($owner->fresh()->can('roles.manage'));

        $this->delete(route('admin.roles.destroy', 'owner'))->assertRedirect();
        $this->assertNotNull(Role::findByKey('owner'));

        // Baris Superadmin hilang (mis. dihapus lewat database) → pemilik tetap tidak terkunci.
        Role::query()->where('key', 'owner')->delete();
        $this->assertTrue($owner->fresh()->isOwner());
        $this->assertTrue($owner->fresh()->can('integrations.manage'));
    }

    public function test_role_in_use_cannot_be_deleted(): void
    {
        $owner = User::factory()->admin()->create();
        User::factory()->staff(StaffRole::CustomerService)->create();

        $this->actingAs($owner)->delete(route('admin.roles.destroy', 'cs'))->assertRedirect()->assertSessionHas('error');
        $this->assertNotNull(Role::findByKey('cs'));

        $this->delete(route('admin.roles.destroy', 'warehouse'))->assertRedirect(route('admin.roles.index'));
        $this->assertNull(Role::findByKey('warehouse'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'roles.destroy']);
    }

    public function test_non_owner_with_staff_permission_cannot_touch_superadmin(): void
    {
        $owner = User::factory()->admin()->create();
        Role::findByKey('admin')->update(['permissions' => [...Role::findByKey('admin')->permissions, 'staff.manage']]);
        $admin = User::factory()->staff(StaffRole::Admin)->create();
        $cs = User::factory()->staff(StaffRole::CustomerService)->create();

        $this->actingAs($admin)->get(route('admin.staff.index'))->assertOk()->assertSee('Hanya Superadmin');
        $this->get(route('admin.staff.create'))->assertOk()->assertDontSee('value="owner"', false);

        $this->post(route('admin.staff.store'), [
            'name' => 'X', 'email' => 'x@mga.test', 'staff_role' => 'owner',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors('staff_role');

        $this->put(route('admin.staff.update', $cs), ['name' => $cs->name, 'email' => $cs->email, 'staff_role' => 'owner'])
            ->assertSessionHasErrors('staff_role');
        $this->assertSame('cs', $cs->fresh()->staff_role);

        $this->get(route('admin.staff.edit', $owner))->assertForbidden();
        $this->patch(route('admin.staff.toggle', $owner))->assertForbidden();
        $this->assertTrue($owner->fresh()->is_active);

        // Staf biasa tetap bisa diubah.
        $this->put(route('admin.staff.update', $cs), ['name' => 'CS Baru', 'email' => $cs->email, 'staff_role' => 'warehouse'])
            ->assertRedirect(route('admin.staff.index'));
        $this->assertSame('warehouse', $cs->fresh()->staff_role);
    }

    public function test_inactive_staff_has_no_permissions(): void
    {
        $order = Order::factory()->create();
        $staff = User::factory()->staff(StaffRole::Warehouse)->inactive()->create();

        $this->assertFalse($staff->can('orders.view'));
        $this->assertNotNull($order);
    }
}
