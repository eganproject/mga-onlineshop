<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateSuperadminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_superadmin_account(): void
    {
        $this->artisan('shop:create-admin')
            ->expectsQuestion('Nama', 'Pemilik Toko')
            ->expectsQuestion('Email', 'Owner@Toko.com')
            ->expectsQuestion('Password (min. 12 karakter, huruf & angka)', 'rahasiaKuat2026')
            ->expectsQuestion('Ulangi password', 'rahasiaKuat2026')
            ->assertSuccessful();

        $user = User::where('email', 'owner@toko.com')->firstOrFail();
        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertSame(StaffRole::Owner, $user->staff_role);
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('rahasiaKuat2026', $user->password));
    }

    public function test_rejects_weak_password(): void
    {
        $this->artisan('shop:create-admin')
            ->expectsQuestion('Nama', 'Pemilik Toko')
            ->expectsQuestion('Email', 'owner@toko.com')
            ->expectsQuestion('Password (min. 12 karakter, huruf & angka)', 'pendek1')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'owner@toko.com']);
    }

    public function test_rejects_mismatched_confirmation(): void
    {
        $this->artisan('shop:create-admin')
            ->expectsQuestion('Nama', 'Pemilik Toko')
            ->expectsQuestion('Email', 'owner@toko.com')
            ->expectsQuestion('Password (min. 12 karakter, huruf & angka)', 'rahasiaKuat2026')
            ->expectsQuestion('Ulangi password', 'rahasiaBeda2026')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'owner@toko.com']);
    }

    public function test_promotes_existing_account_only_after_confirmation(): void
    {
        $user = User::factory()->create(['email' => 'owner@toko.com']);

        $this->artisan('shop:create-admin')
            ->expectsQuestion('Nama', 'Pemilik Toko')
            ->expectsQuestion('Email', 'owner@toko.com')
            ->expectsConfirmation('Email owner@toko.com sudah terdaftar. Jadikan Superadmin dan ganti password-nya?', 'no')
            ->assertFailed();

        $this->assertSame(UserRole::Customer, $user->fresh()->role);

        $this->artisan('shop:create-admin')
            ->expectsQuestion('Nama', 'Pemilik Toko')
            ->expectsQuestion('Email', 'owner@toko.com')
            ->expectsConfirmation('Email owner@toko.com sudah terdaftar. Jadikan Superadmin dan ganti password-nya?', 'yes')
            ->expectsQuestion('Password (min. 12 karakter, huruf & angka)', 'rahasiaKuat2026')
            ->expectsQuestion('Ulangi password', 'rahasiaKuat2026')
            ->assertSuccessful();

        $this->assertSame(StaffRole::Owner, $user->fresh()->staff_role);
    }

    public function test_demo_seeder_is_skipped_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseMissing('users', ['email' => 'admin@mga.test']);
    }
}
