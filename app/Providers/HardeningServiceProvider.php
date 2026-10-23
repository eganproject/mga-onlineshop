<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Pengaturan keamanan & produksi: rate limiter auth, HTTPS, proxy,
 * dan aturan password. Dipisah dari AppServiceProvider supaya rapi.
 */
class HardeningServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureRateLimiters();
        $this->configureHttps();

        // Password pelanggan di produksi wajib berisi huruf dan angka.
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(8)->letters()->numbers()
            : Password::min(8));

        // Cegah migrate:fresh / db:wipe tak sengaja di server produksi.
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }

    protected function configureRateLimiters(): void
    {
        $limits = config('security.rate_limits');

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute($limits['login'])
                ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute($limits['login_per_ip'])->by('ip:'.$request->ip()),
        ]);

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute($limits['register'])
            ->by($request->ip()));

        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute($limits['password_reset'])
            ->by($request->ip()));
    }

    protected function configureHttps(): void
    {
        if ($proxies = config('security.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        if (config('security.force_https')) {
            URL::forceScheme('https');
        }
    }
}
