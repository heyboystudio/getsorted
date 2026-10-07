<?php

declare(strict_types=1);

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;

it('serves the Get Sorted public home page', function (): void {
    $this->withoutVite();

    $this->get('/')
        ->assertOk()
        ->assertSeeText('Get Sorted')
        ->assertSeeText('Get Your Home Sorted, Properly.')
        ->assertSee(route('register'), false);
});

it('reports application health', function (): void {
    $this->get('/up')->assertOk();
});

it('does not expose starter account pages', function (string $path): void {
    $this->withoutVite();

    $this->get($path)->assertNotFound();
})->with([
    // /register, /forgot-password and /reset-password are Get Sorted's own pages since spec 014.
    '/email/verify', '/two-factor-challenge', '/user/confirm-password',
    '/dashboard', '/settings/profile', '/settings/security', '/settings/appearance',
]);

it('does not accept starter account mutations', function (string $path): void {
    $this->post($path, [])->assertNotFound();
})->with([
    '/two-factor-challenge', '/user/confirm-password',
]);

it('accepts sign-in only through the Livewire form, not a plain POST', function (): void {
    $this->post('/login', ['email' => 'a@b.test', 'password' => 'secret'])->assertMethodNotAllowed();
});

it('denies user management until an account policy is implemented', function (): void {
    $user = new User;

    expect(Gate::getPolicyFor(User::class))->toBeInstanceOf(UserPolicy::class);

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'] as $ability) {
        expect(Gate::forUser($user)->allows($ability, $user))->toBeFalse();
    }
});
