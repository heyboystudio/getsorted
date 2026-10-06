<?php

declare(strict_types=1);

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Auth\SessionGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureSecurity();
        $this->configureRateLimiting();
        $this->configureCloudflare();
    }

    /**
     * Behind the Cloudflare proxy every request arrives from a Cloudflare address, so trust only those
     * ranges (https://www.cloudflare.com/ips) to read the visitor's real IP. Review the list if Cloudflare changes it.
     */
    private function configureCloudflare(): void
    {
        if (config('sortd.behind_cloudflare') !== true) {
            return;
        }

        TrustProxies::at([
            '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18',
            '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
            '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
            '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
        ]);
        TrustProxies::withHeaders(Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    private function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Strict models catch lazy loading, unknown attributes and silently dropped
     * attributes while developing; HTTPS links everywhere except local and tests.
     */
    private function configureSecurity(): void
    {
        Model::shouldBeStrict(! app()->isProduction());

        URL::forceHttps(! app()->environment(['local', 'testing']));

        // "Keep me logged in" lasts 30 days (security baseline §1); admins never get the option.
        $guard = Auth::guard('web');

        if ($guard instanceof SessionGuard) {
            $guard->setRememberDuration((int) config('sortd.auth.remember_days') * 24 * 60);
        }
    }

    /**
     * Named limits for routes. OTP and sign-up limits are enforced inside the
     * login component via App\Domain\Accounts\Support\LoginThrottle.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('webhooks', fn (Request $request): Limit => Limit::perMinute(120)->by('webhooks:ip:'.$request->ip()));
    }
}
