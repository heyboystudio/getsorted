<?php

declare(strict_types=1);

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
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
    }

    /**
     * Named limits from the security baseline §1. Phone numbers are hashed in
     * keys so they never land in the cache table in plain text.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('otp-send', fn (Request $request): array => [
            Limit::perMinutes(15, 3)->by('otp-send:phone:'.hash('sha256', (string) $request->input('phone'))),
            Limit::perHour(10)->by('otp-send:ip:'.$request->ip()),
        ]);

        RateLimiter::for('otp-verify', fn (Request $request): Limit => Limit::perMinutes(15, 10)->by('otp-verify:ip:'.$request->ip()));

        RateLimiter::for('webhooks', fn (Request $request): Limit => Limit::perMinute(120)->by('webhooks:ip:'.$request->ip()));
    }
}
