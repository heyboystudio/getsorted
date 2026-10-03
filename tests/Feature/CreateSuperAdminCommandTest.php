<?php

declare(strict_types=1);

use App\Domain\Accounts\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\PendingCommand;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // The "not in a known breach" check calls Have I Been Pwned; fake it so tests stay offline.
    $this->breachedPasswords = [];

    Http::preventStrayRequests();
    Http::fake(['api.pwnedpasswords.com/*' => function () {
        $lines = array_map(
            fn (string $password): string => mb_substr(mb_strtoupper(sha1($password)), 5).':42',
            $this->breachedPasswords,
        );

        return Http::response(implode("\n", $lines));
    }]);
});

const STRONG_PASSWORD = 'Correct-Horse-42-Battery';

function runCreateSuperAdmin(string $email, string $password, ?string $confirmation = null): PendingCommand
{
    return test()->artisan('sortd:create-super-admin')
        ->expectsQuestion('Full name', 'Founder Person')
        ->expectsQuestion('Email address', $email)
        ->expectsQuestion('Password (at least 12 characters, upper and lower case, a number and a symbol)', $password)
        ->expectsQuestion('Confirm password', $confirmation ?? $password);
}

it('creates a super-admin with a hashed password', function (): void {
    runCreateSuperAdmin('Founder@Example.com', STRONG_PASSWORD)->assertSuccessful();

    $user = User::query()->where('email', 'founder@example.com')->sole();

    expect($user->hasRole(Role::AdminSuper->value))->toBeTrue()
        ->and($user->password)->not->toBe(STRONG_PASSWORD)
        ->and(Hash::check(STRONG_PASSWORD, $user->password))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull();
});

it('rejects weak passwords', function (string $password): void {
    runCreateSuperAdmin('founder@example.com', $password)->assertFailed();

    expect(User::query()->count())->toBe(0);
})->with(['short' => 'Ab1!short', 'no symbol' => 'NoSymbolsHere123', 'no upper case' => 'all-lower-case-123!']);

it('rejects passwords found in known breaches', function (): void {
    $this->breachedPasswords = [STRONG_PASSWORD];

    runCreateSuperAdmin('founder@example.com', STRONG_PASSWORD)->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('rejects mismatched password confirmation', function (): void {
    runCreateSuperAdmin('founder@example.com', STRONG_PASSWORD, 'Something-Else-42!')->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('rejects an email that is already registered', function (): void {
    User::factory()->create(['email' => 'founder@example.com']);

    runCreateSuperAdmin('founder@example.com', STRONG_PASSWORD)->assertFailed();

    expect(User::query()->count())->toBe(1);
});

it('refuses to run without an interactive terminal', function (): void {
    $this->artisan('sortd:create-super-admin', ['--no-interaction' => true])->assertFailed();

    expect(User::query()->count())->toBe(0);
});
