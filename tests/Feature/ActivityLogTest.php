<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\StaffRole;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_changes_are_logged_automatically_with_subject(): void
    {
        $staff = User::factory()->staff(StaffRole::Warehouse)->create();
        $order = Order::factory()->create();

        $this->actingAs($staff)->patch(route('admin.orders.status', $order), [
            'status' => OrderStatus::Processing->value,
            'note' => 'Dikemas',
        ]);

        $log = ActivityLog::where('action', 'orders.status')->sole();
        $this->assertSame($staff->id, $log->user_id);
        $this->assertSame($staff->name, $log->user_name);
        $this->assertSame('Mengubah status pesanan', $log->description);
        $this->assertSame($order->code, $log->subject_label);
        $this->assertTrue($log->subject->is($order));
        $this->assertSame('processing', $log->properties['status']);
        $this->assertArrayNotHasKey('_token', $log->properties);
    }

    public function test_reads_failed_validation_and_forbidden_actions_are_not_logged(): void
    {
        $staff = User::factory()->staff(StaffRole::CustomerService)->create();
        $order = Order::factory()->create();

        $this->actingAs($staff);
        $this->get(route('admin.orders.show', $order))->assertOk();
        $this->patch(route('admin.orders.status', $order), ['status' => 'processing'])->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.orders.tracking-number', $order), ['tracking_number' => ''])
            ->assertSessionHasErrors();

        $this->assertSame(0, ActivityLog::count());
    }

    public function test_passwords_are_never_stored(): void
    {
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)->put(route('admin.profile.password'), [
            'current_password' => 'password',
            'password' => 'passwordBaru123',
            'password_confirmation' => 'passwordBaru123',
        ]);

        $log = ActivityLog::where('action', 'profile.password')->sole();
        $this->assertStringNotContainsString('passwordBaru123', json_encode($log->properties));
        $this->assertStringNotContainsString('"password"', (string) json_encode($log->getAttributes()));
    }

    public function test_activity_page_lists_and_filters_logs(): void
    {
        $owner = User::factory()->admin()->create();
        $staff = User::factory()->staff(StaffRole::Warehouse)->create(['name' => 'Rudi Gudang']);
        $order = Order::factory()->create();

        $this->actingAs($staff)->patch(route('admin.orders.tracking-number', $order), ['tracking_number' => 'JNE123']);
        $this->actingAs($owner)->patch(route('admin.profile.update'), ['name' => 'Owner Baru', 'email' => $owner->email]);

        $this->get(route('admin.activity.index'))->assertOk()
            ->assertSee('Rudi Gudang')
            ->assertSee($order->code)
            ->assertSee('mengubah profil sendiri');

        $this->get(route('admin.activity.index', ['staf' => $staff->id]))->assertOk()
            ->assertSee($order->code)
            ->assertDontSee('mengubah profil sendiri');

        $this->get(route('admin.activity.index', ['grup' => 'profile']))->assertOk()
            ->assertDontSee($order->code);
    }

    public function test_old_logs_are_pruned(): void
    {
        $old = ActivityLog::create(['action' => 'x.y', 'description' => 'Lama', 'created_at' => now()->subDays(400)]);
        $new = ActivityLog::create(['action' => 'x.y', 'description' => 'Baru', 'created_at' => now()->subDays(10)]);

        $this->artisan('model:prune', ['--model' => [ActivityLog::class]])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($new);
    }
}
