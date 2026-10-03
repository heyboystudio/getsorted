<?php

declare(strict_types=1);

use App\Domain\Accounts\Enums\Role;
use App\Domain\Accounts\Support\PhoneNumbers;
use App\Filament\Admin\Pages\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('sends security headers on public pages and the admin panel', function (string $path): void {
    $response = $this->get($path);

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'")
        ->toContain("default-src 'self'");
})->with(['/', '/admin/login', '/up']);

it('sends HSTS only over HTTPS', function (): void {
    $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    $this->get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');
});

it('keeps admin sessions to a 2-hour idle timeout with no remember-me', function (): void {
    expect(config('session.lifetime'))->toBe(120)
        ->and(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax')
        ->and(Filament::getPanel('admin')->getLoginRouteAction())->toBe(Login::class);

    $admin = User::factory()->create();
    $admin->assignRole(Role::AdminSuper->value);
    $tokenBefore = $admin->getRememberToken();
    Filament::setCurrentPanel('admin');

    Livewire::test(Login::class)
        ->assertDontSee('Remember me')
        ->fillForm(['email' => $admin->email, 'password' => 'password'])
        ->set('data.remember', true)
        ->call('authenticate');

    $this->assertAuthenticatedAs($admin);
    // Logging in with "remember" would replace the token; it must be untouched.
    expect($admin->fresh()->getRememberToken())->toBe($tokenBefore);
});

it('defines the OTP and webhook rate limits from the security baseline', function (): void {
    $request = Request::create('/', 'POST', ['phone' => '+27821234567'], server: ['REMOTE_ADDR' => '10.0.0.1']);

    $sendLimits = RateLimiter::limiter('otp-send')($request);

    expect($sendLimits)->toHaveCount(2)
        ->and($sendLimits[0]->maxAttempts)->toBe(3)
        ->and($sendLimits[0]->decaySeconds)->toBe(15 * 60)
        ->and($sendLimits[0]->key)->not->toContain('27821234567')
        ->and($sendLimits[1]->maxAttempts)->toBe(10)
        ->and($sendLimits[1]->decaySeconds)->toBe(60 * 60)
        ->and(RateLimiter::limiter('otp-verify')($request)->maxAttempts)->toBe(10)
        ->and(RateLimiter::limiter('webhooks')($request)->maxAttempts)->toBe(120);
});

it('makes models strict outside production', function (): void {
    expect(Model::preventsLazyLoading())->toBeTrue()
        ->and(Model::preventsSilentlyDiscardingAttributes())->toBeTrue()
        ->and(Model::preventsAccessingMissingAttributes())->toBeTrue();
});

it('blocks tests from reaching the internet', function (): void {
    Http::get('https://example.com');
})->throws(RuntimeException::class);

it('masks phone numbers in fake messaging logs', function (): void {
    expect(PhoneNumbers::maskForLogs('+27821234567'))->toBe('+278******67')
        ->and(PhoneNumbers::maskForDisplay('+27821234567'))->toBe('+27 82 *** 4567');
});
