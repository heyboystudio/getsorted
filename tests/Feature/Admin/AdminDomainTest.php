<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Runs only when the app boots with SORTD_ADMIN_DOMAIN set, e.g. SORTD_ADMIN_DOMAIN=admin.sorted.test vendor/bin/pest tests/Feature/Admin
it('serves the admin panel on its own host and the public site on the main host', function (): void {
    $host = (string) config('sortd.admin_domain');

    $this->get("http://{$host}/login")->assertOk()->assertSee('Sign in', false);
    $this->get("http://{$host}/")->assertRedirect("http://{$host}/login");
    $this->get('http://sorted.test/')->assertOk()->assertDontSee('admin', false);
    $this->get('http://sorted.test/admin')->assertRedirect("https://{$host}");
})->skip(fn (): bool => config('sortd.admin_domain') === null, 'Needs SORTD_ADMIN_DOMAIN set at boot.');

it('keeps the admin panel at /admin when no admin host is set', function (): void {
    $this->get('/admin/login')->assertOk();
})->skip(fn (): bool => config('sortd.admin_domain') !== null, 'Only applies without an admin host.');
