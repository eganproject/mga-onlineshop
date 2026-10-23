<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_sent(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_is_only_sent_over_https(): void
    {
        $this->get('https://localhost/masuk')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_login_is_rate_limited_per_email(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $i) {
            $this->post(route('login'), ['email' => $user->email, 'password' => 'salah'])
                ->assertSessionHasErrors('email');
        }

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(429)
            ->assertSee('Terlalu banyak percobaan');

        $this->assertGuest();
    }

    public function test_registration_is_rate_limited_per_ip(): void
    {
        foreach (range(1, 5) as $i) {
            $this->post(route('register'), ['name' => 'X']);
        }

        $this->post(route('register'), ['name' => 'X'])->assertStatus(429);
    }

    public function test_backup_command_stores_a_compressed_database_dump(): void
    {
        $database = tempnam(sys_get_temp_dir(), 'mga-db');
        file_put_contents($database, 'SQLite format 3');

        config([
            'database.connections.sqlite.database' => $database,
            'backup.disk' => 'local',
            'backup.path' => 'backups',
        ]);
        Storage::fake('local');

        // Backup lama (lebih dari keep_days) harus terhapus.
        Storage::disk('local')->put('backups/db-2020-01-01_000000.sql.gz', 'lama');
        touch(Storage::disk('local')->path('backups/db-2020-01-01_000000.sql.gz'), now()->subDays(30)->getTimestamp());

        $this->artisan('shop:backup --no-uploads')->assertSuccessful();

        $files = Storage::disk('local')->files('backups');
        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('/db-\d{4}-\d{2}-\d{2}_\d{6}\.sql\.gz$/', $files[0]);
        $this->assertSame('SQLite format 3', gzdecode(Storage::disk('local')->get($files[0])));

        unlink($database);
    }

    public function test_production_check_fails_outside_production(): void
    {
        $this->artisan('shop:check')->assertFailed();
    }

    public function test_backup_and_cleanup_tasks_are_scheduled(): void
    {
        $commands = collect(app(Schedule::class)->events())->pluck('command')->implode("\n");

        $this->assertStringContainsString('shop:backup', $commands);
        $this->assertStringContainsString('queue:prune-failed', $commands);
    }
}
