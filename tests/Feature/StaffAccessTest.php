<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_staff_can_process_orders_and_stock_but_not_catalog_or_reports(): void
    {
        $staff = User::factory()->staff(StaffRole::Warehouse)->create();
        $order = Order::factory()->create();

        $this->actingAs($staff);

        $this->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Pesanan')
            ->assertSee('Stok')
            ->assertDontSee(route('admin.products.index'))
            ->assertDontSee(route('admin.reports.index'))
            ->assertDontSee(route('admin.staff.index'))
            ->assertDontSee('Pendapatan');

        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Perbarui status');
        $this->get(route('admin.stock.index'))->assertOk();
        $this->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Processing->value])->assertRedirect();
        $this->assertSame(OrderStatus::Processing, $order->fresh()->status);

        $this->get(route('admin.products.index'))->assertForbidden();
        $this->get(route('admin.reports.index'))->assertForbidden();
        $this->get(route('admin.customers.index'))->assertForbidden();
        $this->get(route('admin.store-settings.edit'))->assertForbidden();
        $this->get(route('admin.staff.index'))->assertForbidden();
        $this->get(route('admin.activity.index'))->assertForbidden();
        $this->get(route('admin.vouchers.index'))->assertForbidden();
        $this->get(route('admin.banners.index'))->assertForbidden();
    }

    public function test_customer_service_can_view_orders_but_not_change_them(): void
    {
        $staff = User::factory()->staff(StaffRole::CustomerService)->create();
        $order = Order::factory()->create();
        $customer = User::factory()->create();

        $this->actingAs($staff);

        $this->get(route('admin.orders.show', $order))->assertOk()->assertDontSee('Perbarui status');
        $this->get(route('admin.customers.show', $customer))->assertOk()->assertDontSee('Blokir akun');
        $this->get(route('admin.reviews.index'))->assertOk();

        $this->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Processing->value])->assertForbidden();
        $this->post(route('admin.orders.bulk'), ['action' => 'process', 'orders' => [$order->id]])->assertForbidden();
        $this->post(route('admin.orders.bulk'), ['action' => 'packing-slips', 'orders' => [$order->id]])->assertOk();
        $this->patch(route('admin.customers.toggle', $customer))->assertForbidden();
        $this->get(route('admin.stock.index'))->assertForbidden();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertTrue($customer->fresh()->is_active);
    }

    public function test_admin_role_can_do_everything_except_manage_staff(): void
    {
        $this->actingAs(User::factory()->staff(StaffRole::Admin)->create());

        $this->get(route('admin.products.index'))->assertOk();
        $this->get(route('admin.reports.index'))->assertOk();
        $this->get(route('admin.store-settings.edit'))->assertOk();
        $this->get(route('admin.activity.index'))->assertOk();
        $this->get(route('admin.staff.index'))->assertForbidden();
    }

    public function test_owner_creates_staff_account(): void
    {
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)->get(route('admin.staff.index'))->assertOk()->assertSee($owner->email);
        $this->get(route('admin.staff.create'))->assertOk();

        $this->post(route('admin.staff.store'), [
            'name' => 'Rudi Gudang',
            'email' => 'rudi@mga.test',
            'staff_role' => StaffRole::Warehouse->value,
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            // Tidak boleh bisa menyelundupkan peran lewat input.
            'role' => 'customer',
            'is_active' => '0',
        ])->assertRedirect(route('admin.staff.index'));

        $staff = User::where('email', 'rudi@mga.test')->firstOrFail();
        $this->assertSame(UserRole::Admin, $staff->role);
        $this->assertSame(StaffRole::Warehouse, $staff->staff_role);
        $this->assertTrue($staff->is_active);
        $this->assertNotNull($staff->email_verified_at);

        $this->assertDatabaseHas('activity_logs', ['action' => 'staff.store', 'user_id' => $owner->id, 'subject_id' => $staff->id]);

        // Staf baru bisa login dan langsung masuk panel admin.
        auth()->logout();
        $this->post(route('login'), ['email' => 'rudi@mga.test', 'password' => 'rahasia123'])->assertRedirect(route('admin.dashboard'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.login', 'user_id' => $staff->id]);
        $this->assertNotNull($staff->fresh()->last_login_at);
    }

    public function test_staff_email_must_be_unique_across_customers(): void
    {
        $customer = User::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.staff.store'), [
                'name' => 'X', 'email' => $customer->email, 'staff_role' => 'cs',
                'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
            ])->assertSessionHasErrors('email');

        $this->assertSame(UserRole::Customer, $customer->fresh()->role);
    }

    public function test_owner_changes_role_and_resets_password(): void
    {
        $owner = User::factory()->admin()->create();
        $staff = User::factory()->staff(StaffRole::CustomerService)->create();

        $this->actingAs($owner)->get(route('admin.staff.edit', $staff))->assertOk();
        $this->put(route('admin.staff.update', $staff), [
            'name' => $staff->name, 'email' => $staff->email, 'staff_role' => StaffRole::Admin->value,
            'password' => 'passwordbaru1', 'password_confirmation' => 'passwordbaru1',
        ])->assertRedirect(route('admin.staff.index'));

        $staff->refresh();
        $this->assertSame(StaffRole::Admin, $staff->staff_role);
        $this->assertTrue(password_verify('passwordbaru1', $staff->password));
        $log = $staff->morphMany(\App\Models\ActivityLog::class, 'subject')->where('action', 'staff.update')->firstOrFail();
        $this->assertSame(['dari' => 'Customer Service', 'menjadi' => 'Admin'], $log->properties['jabatan']);
        $this->assertTrue($log->properties['sandi_direset']);
        $this->assertStringNotContainsString('passwordbaru1', json_encode($log->properties));
    }

    public function test_last_active_owner_cannot_be_demoted_or_deactivated(): void
    {
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)->put(route('admin.staff.update', $owner), [
            'name' => $owner->name, 'email' => $owner->email, 'staff_role' => StaffRole::Admin->value,
        ])->assertSessionHasErrors('staff_role');
        $this->assertSame(StaffRole::Owner, $owner->fresh()->staff_role);

        $this->patch(route('admin.staff.toggle', $owner))->assertSessionHasErrors('staff');
        $this->assertTrue($owner->fresh()->is_active);

        // Dengan Owner lain yang aktif, Owner kedua boleh dinonaktifkan.
        $second = User::factory()->admin()->create();
        $this->patch(route('admin.staff.toggle', $second))->assertSessionHasNoErrors();
        $this->assertFalse($second->fresh()->is_active);
    }

    public function test_deactivated_staff_is_logged_out_on_next_request(): void
    {
        $staff = User::factory()->staff(StaffRole::Warehouse)->create();

        $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk();

        $staff->forceFill(['is_active' => false])->save();

        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_non_owner_cannot_manage_staff_and_customers_are_not_staff(): void
    {
        $admin = User::factory()->staff(StaffRole::Admin)->create();
        $customer = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.staff.store'), [
            'name' => 'X', 'email' => 'x@mga.test', 'staff_role' => 'owner',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'x@mga.test']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.staff.edit', $customer))->assertNotFound();
    }

    public function test_existing_admins_without_staff_role_are_owners(): void
    {
        $legacy = User::factory()->admin()->create();
        $legacy->forceFill(['staff_role' => null])->save();

        $this->assertTrue($legacy->fresh()->isOwner());
        $this->actingAs($legacy->fresh())->get(route('admin.staff.index'))->assertOk();
    }

    public function test_product_pages_render_for_owner_with_new_menu(): void
    {
        Product::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.store-settings.edit'))
            ->assertSee(route('admin.staff.index'))
            ->assertSee(route('admin.activity.index'));
    }
}
