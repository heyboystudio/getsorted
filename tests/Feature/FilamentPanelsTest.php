<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows the admin login page to guests', function (): void {
    $this->get('/admin/login')->assertOk()->assertSee('Sign in');
});

it('sends guests from the admin dashboard to the login page', function (): void {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('offers optional app-based multi-factor authentication to admins', function (): void {
    $panel = Filament::getPanel('admin');

    expect($panel->isMultiFactorAuthenticationRequired())->toBeFalse()
        ->and($panel->getMultiFactorAuthenticationProviders())->toHaveKey('app')
        ->and($panel->getMultiFactorAuthenticationProviders()['app'])->toBeInstanceOf(AppAuthentication::class);
});

it('denies the admin panel to users without an admin role', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertForbidden();
});

it('rejects admin login for users without an admin role', function (): void {
    $user = User::factory()->create();
    Filament::setCurrentPanel('admin');

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest();
});

it('keeps the pro panel locked', function (string $path): void {
    $this->get($path)->assertNotFound();
    $this->actingAs(User::factory()->create())->get($path)->assertNotFound();
})->with(['/pro', '/pro/login', '/pro/anything']);

it('stores MFA secrets encrypted and never serialises them', function (): void {
    $user = User::factory()->create();
    $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $user->saveAppAuthenticationRecoveryCodes(['code-one', 'code-two']);

    $row = DB::table('users')->where('id', $user->id)->first();

    expect($row->app_authentication_secret)->not->toContain('JBSWY3DPEHPK3PXP')
        ->and($row->app_authentication_recovery_codes)->not->toContain('code-one')
        ->and($user->fresh()->getAppAuthenticationSecret())->toBe('JBSWY3DPEHPK3PXP')
        ->and($user->toArray())->not->toHaveKeys(['app_authentication_secret', 'app_authentication_recovery_codes']);
});
