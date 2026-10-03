<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Accounts\Support\LoginThrottle;
use App\Domain\Accounts\Support\PhoneNumbers;
use Carbon\CarbonImmutable;
use Illuminate\Auth\SessionGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
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
     * Named limits from the security baseline §1. Phone numbers are hashed in
     * keys so they never land in the cache table in plain text.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('otp-send', fn (Request $request): array => LoginThrottle::sendLimits(
            PhoneNumbers::normaliseSaMobile((string) $request->input('phone')) ?? (string) $request->input('phone'),
            $request->ip(),
        ));

        RateLimiter::for('otp-verify', fn (Request $request): Limit => LoginThrottle::verifyLimit($request->ip()));

        RateLimiter::for('webhooks', fn (Request $request): Limit => Limit::perMinute(120)->by('webhooks:ip:'.$request->ip()));
    }
}
