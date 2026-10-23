<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_pages_render(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Masuk');
        $this->get(route('register'))->assertOk();
        $this->get(route('password.request'))->assertOk();
        $this->get(route('password.reset', 'token'))->assertOk();
    }

    public function test_registration_always_creates_a_customer(): void
    {
        $this->post(route('register'), [
            'name' => 'Siti Aminah',
            'email' => 'siti@example.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'role' => 'admin', // harus diabaikan
        ])->assertRedirect(route('account.dashboard'));

        $user = User::where('email', 'siti@example.com')->first();
        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_is_redirected_to_admin_panel_after_login(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post(route('login'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_customer_is_redirected_to_account_after_login(): void
    {
        $customer = User::factory()->create();

        $this->post(route('login'), ['email' => $customer->email, 'password' => 'password'])
            ->assertRedirect(route('account.dashboard'));
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'salah'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_customer_cannot_open_admin_panel(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_admin_cannot_open_customer_area(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('account.dashboard'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('account.dashboard'))->assertRedirect(route('login'));
    }

    public function test_logged_in_user_visiting_login_goes_to_their_home(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('login'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_logout(): void
    {
        $this->actingAs(User::factory()->create())->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }
}
