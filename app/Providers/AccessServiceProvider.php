<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\User;
use App\Services\Activity\ActivityLogger;
use App\Services\Settings\StoreSettings;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/** Pengaturan toko dari database, hak akses staf, dan log aktivitas admin. */
class AccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StoreSettings::class);
        $this->app->singleton(ActivityLogger::class);
    }

    public function boot(): void
    {
        // `config:cache` tidak boleh ikut membekukan nilai dari database.
        if (! $this->app->runningConsoleCommand('config:cache', 'optimize')) {
            $this->app->make(StoreSettings::class)->apply();
        }

        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user) => $user->hasPermission($permission));
        }

        Event::listen(function (Login $event) {
            if (! $event->user instanceof User || ! $event->user->isAdmin()) {
                return;
            }

            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            app(ActivityLogger::class)->log('auth.login', 'Masuk ke panel admin', user: $event->user);
        });
    }
}
