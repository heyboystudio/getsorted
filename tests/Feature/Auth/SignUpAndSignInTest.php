<?php

declare(strict_types=1);

use App\Domain\Accounts\Actions\SendEmailVerification;
use App\Domain\Accounts\Enums\ConsentType;
use App\Domain\Accounts\Enums\Role;
use App\Domain\Accounts\Support\GoogleSignIn;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\VerifyEmail;
use App\Mail\VerifyEmailAddress;
use App\Models\Consent;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Socialite\Contracts\Factory as Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/** Spec 014: sign up and sign in with email or Google, then verify email and mobile. */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    // The breached-password check asks the Have I Been Pwned range API; answer "not found".
    $this->pwnedSuffixes = '';
    Http::fake(['api.pwnedpasswords.com/*' => fn () => Http::response($this->pwnedSuffixes, 200)]);
});

function fillSignUp(array $overrides = []): Testable
{
    $component = Livewire::test(Register::class);

    foreach ([
        'firstName' => 'Thandi', 'lastName' => 'Nkosi', 'email' => 'Thandi@Example.com', 'password' => 'long-enough-password',
        'acceptTerms' => true, 'acceptPrivacy' => true, ...$overrides,
    ] as $field => $value) {
        $component->set($field, $value);
    }

    return $component;
}

function fakeGoogle(string $id = 'g-123', string $email = 'thandi@gmail.com', bool $verified = true): void
{
    $google = (new GoogleUser)->setRaw(['email_verified' => $verified, 'given_name' => 'Thandi', 'family_name' => 'Nkosi'])
        ->map(['id' => $id, 'email' => $email, 'name' => 'Thandi Nkosi']);
    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->andReturn($google);
    $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
    app(Socialite::class)->extend('google', fn () => $provider);
}

// --- Sign up with email (AC1, AC3) --------------------------------------------------------

it('shows the sign-up page with Google and email options', function (): void {
    $this->get('/register')->assertOk()->assertSeeLivewire(Register::class)
        ->assertSee('Continue with Google')->assertSee('Create client account');
});

it('creates a customer with a hashed password, consents and an unverified email, then asks to check email (AC1, AC3)', function (): void {
    fillSignUp(['marketing' => true])->call('register')->assertHasNoErrors()->assertRedirect(route('account.home'));

    $user = User::query()->sole();
    expect($user->email)->toBe('thandi@example.com')
        ->and(Hash::check('long-enough-password', $user->password))->toBeTrue()
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->hasRole(Role::Customer->value))->toBeTrue()
        ->and(Consent::query()->pluck('type')->map->value->sort()->values()->all())->toBe(['marketing', 'privacy', 'terms']);
    $this->assertAuthenticatedAs($user);
    Mail::assertSent(VerifyEmailAddress::class, fn (VerifyEmailAddress $mail): bool => $mail->hasTo('thandi@example.com'));

    // The gate then sends them to "Check your email".
    $this->get('/app')->assertRedirect(route('verification.email'));
});

it('requires names, a valid email, a 10-character password and both consents (AC1)', function (): void {
    fillSignUp(['firstName' => '', 'email' => 'nope', 'password' => 'short', 'acceptTerms' => false, 'acceptPrivacy' => false])
        ->call('register')->assertHasErrors(['firstName', 'email', 'password', 'acceptTerms', 'acceptPrivacy']);

    expect(User::query()->count())->toBe(0);
});

it('refuses passwords that appear in known data breaches (AC1)', function (): void {
    // SHA-1 of "long-enough-password" split into prefix and suffix, as the range API answers.
    $hash = strtoupper(sha1('long-enough-password'));
    $this->pwnedSuffixes = substr($hash, 5).':42';

    fillSignUp()->call('register')->assertHasErrors(['password']);
});

it('tells someone with an existing email to sign in instead (AC1)', function (): void {
    User::factory()->customer()->create(['email' => 'thandi@example.com']);

    fillSignUp()->call('register')->assertHasErrors(['email'])->assertSee('Sign in instead');
    expect(User::query()->count())->toBe(1);
});

it('signs up pros with the pro agreement (spec 011)', function (): void {
    Livewire::test(Register::class, ['as' => 'pro'])->assertSee('Join GetSorted as a pro')->assertSee('pro agreement');

    $component = Livewire::test(Register::class, ['as' => 'pro']);
    foreach (['firstName' => 'Sipho', 'lastName' => 'Dlamini', 'email' => 'sipho@example.com', 'password' => 'long-enough-password', 'acceptTerms' => true, 'acceptPrivacy' => true] as $field => $value) {
        $component->set($field, $value);
    }
    $component->call('register')->assertHasErrors(['acceptProAgreement']);
    $component->set('acceptProAgreement', true)->call('register')->assertHasNoErrors();

    $user = User::query()->sole();
    expect($user->hasRole(Role::Pro->value))->toBeTrue()
        ->and(Consent::query()->where('type', ConsentType::ProAgreement)->exists())->toBeTrue();
});

it('limits sign-up attempts per IP', function (): void {
    foreach (range(1, 5) as $i) {
        fillSignUp(['email' => "u{$i}@example.com"])->call('register');
        auth()->logout();
    }

    fillSignUp(['email' => 'six@example.com'])->call('register')->assertHasErrors(['firstName'])->assertSee('Too many attempts');
});

// --- Email verification (AC10) -----------------------------------------------------------

it('verifies the email from the signed link, then asks for the mobile (AC6, AC10)', function (): void {
    $user = User::factory()->customer()->unverified()->withoutPhone()->create();

    $this->actingAs($user)->get(SendEmailVerification::link($user))->assertRedirect(route('verification.phone'));

    expect($user->fresh()->email_verified_at)->not->toBeNull()
        ->and(Activity::query()->where('description', 'email verified')->exists())->toBeTrue();
});

it('rejects tampered, expired or someone else\'s verification links (AC10)', function (): void {
    $user = User::factory()->customer()->unverified()->create();
    $link = SendEmailVerification::link($user);

    $this->actingAs(User::factory()->customer()->unverified()->create())->get($link)->assertForbidden();
    $this->actingAs($user)->get(str_replace('signature=', 'signature=x', $link))->assertForbidden();

    $this->travel(61)->minutes();
    $this->actingAs($user)->get($link)->assertForbidden();
    expect($user->fresh()->email_verified_at)->toBeNull();
});

it('stops working when the email address changed (AC10)', function (): void {
    $user = User::factory()->customer()->unverified()->create();
    $link = SendEmailVerification::link($user);
    $user->forceFill(['email' => 'new@example.com'])->save();

    $this->actingAs($user)->get($link)->assertForbidden();
});

it('resends the verification email a few times at most (AC10)', function (): void {
    $this->actingAs(User::factory()->customer()->unverified()->create());

    $component = Livewire::test(VerifyEmail::class);
    foreach (range(1, 3) as $ignored) {
        $component->call('resend')->assertHasNoErrors();
    }
    $component->call('resend')->assertHasErrors(['resend']);

    Mail::assertSent(VerifyEmailAddress::class, 3);
});

// --- The verification gate (AC6, AC10) ---------------------------------------------------

it('blocks the site until the email and then the mobile are verified (AC6, AC10)', function (string $path): void {
    $this->actingAs(User::factory()->customer()->unverified()->withoutPhone()->create())->get($path)->assertRedirect(route('verification.email'));
    $this->actingAs(User::factory()->customer()->withoutPhone()->create())->get($path)->assertRedirect(route('verification.phone'));
    $this->actingAs(User::factory()->customer()->create())->get($path)->assertSuccessful();
})->with(['/', '/app', '/app/properties']);

it('still lets unverified users read the legal pages and sign out', function (): void {
    $this->actingAs(User::factory()->customer()->unverified()->create());

    $this->get('/terms')->assertOk();
    $this->get('/privacy')->assertOk();
    $this->post('/logout')->assertRedirect();
    $this->assertGuest();
});

it('remembers where an unverified user was going', function (): void {
    $this->actingAs(User::factory()->customer()->withoutPhone()->create())->get('/app/properties');

    expect(session('url.intended'))->toBe(url('/app/properties'));
});

it('lets guests browse the public site as before', function (): void {
    $this->get('/')->assertOk();
    $this->get('/pros/join')->assertOk();
});

// --- Sign in (AC8) -----------------------------------------------------------------------

it('signs in with email and password and regenerates the session (AC8)', function (): void {
    $user = User::factory()->customer()->create(['email' => 'thandi@example.com']);
    $before = session()->getId();

    Livewire::test(Login::class)->set('email', ' Thandi@Example.com ')->set('password', 'password')->call('login')
        ->assertHasNoErrors()->assertRedirect(route('account.home'));

    $this->assertAuthenticatedAs($user);
    expect(session()->getId())->not->toBe($before);
});

it('gives one generic message for a wrong password or unknown email (AC8)', function (string $email): void {
    User::factory()->customer()->create(['email' => 'thandi@example.com']);

    Livewire::test(Login::class)->set('email', $email)->set('password', 'wrong-password')->call('login')
        ->assertHasErrors(['email'])->assertSee('don&#039;t match an account', false);
    $this->assertGuest();
})->with(['thandi@example.com', 'nobody@example.com']);

it('limits sign-in attempts per email (AC8)', function (): void {
    User::factory()->customer()->create(['email' => 'thandi@example.com']);

    foreach (range(1, 5) as $ignored) {
        Livewire::test(Login::class)->set('email', 'thandi@example.com')->set('password', 'wrong')->call('login');
    }

    Livewire::test(Login::class)->set('email', 'thandi@example.com')->set('password', 'password')->call('login')
        ->assertHasErrors(['email'])->assertSee('Too many attempts');
    $this->assertGuest();
});

it('never signs admins in on the public page (AC13)', function (): void {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $admin->assignRole(Role::AdminSuper->value);

    Livewire::test(Login::class)->set('email', 'admin@example.com')->set('password', 'password')->call('login')->assertHasErrors(['email']);
    $this->assertGuest();
});

it('keeps people signed in for 30 days only when asked (AC8)', function (bool $remember): void {
    User::factory()->customer()->create(['email' => 'thandi@example.com']);

    Livewire::test(Login::class)->set('email', 'thandi@example.com')->set('password', 'password')->set('remember', $remember)->call('login');

    expect(Cookie::hasQueued(auth()->guard('web')->getRecallerName()))->toBe($remember);
})->with([true, false]);

// --- Google (AC2) --------------------------------------------------------------------------

it('sends "Continue with Google" to Google', function (): void {
    fakeGoogle();

    $this->get('/auth/google')->assertRedirect('https://accounts.google.com/o/oauth2/auth');
});

it('starts a Google sign-up that only needs the consents, with the email already verified (AC2)', function (): void {
    fakeGoogle();

    $this->get('/auth/google/callback')->assertRedirect(route('register', ['with' => 'google']));

    Livewire::withQueryParams(['with' => 'google'])->test(Register::class)
        ->assertSet('withGoogle', true)->assertSet('email', 'thandi@gmail.com')->assertSet('firstName', 'Thandi')
        ->assertDontSee('At least 10 characters')
        ->set('email', 'attacker@example.com') // the email always comes from Google
        ->set('acceptTerms', true)->set('acceptPrivacy', true)
        ->call('register')->assertHasNoErrors();

    $user = User::query()->sole();
    expect($user->email)->toBe('thandi@gmail.com')->and($user->google_id)->toBe('g-123')
        ->and($user->password)->toBeNull()->and($user->email_verified_at)->not->toBeNull();
    Mail::assertNothingSent();
    $this->get('/app')->assertRedirect(route('verification.phone'));
});

it('signs in a returning Google user (AC2, AC8)', function (): void {
    $user = User::factory()->customer()->create(['google_id' => 'g-123']);
    fakeGoogle();

    $this->get('/auth/google/callback')->assertRedirect(route('account.home'));
    $this->assertAuthenticatedAs($user);
});

it('does not sign a registered Google user in from the sign-up page (founder 2026-10-07)', function (): void {
    User::factory()->customer()->create(['google_id' => 'g-123']);
    fakeGoogle();

    $this->get('/auth/google?intent=register')->assertRedirect('https://accounts.google.com/o/oauth2/auth');
    $this->get('/auth/google/callback')->assertRedirect(route('login'));

    $this->assertGuest();
    $this->get(route('login'))->assertSee('Sign in instead');
});

it('does not link or sign in an existing email from the sign-up page', function (): void {
    $user = User::factory()->customer()->create(['email' => 'thandi@gmail.com']);
    fakeGoogle();

    $this->get('/auth/google?intent=register');
    $this->get('/auth/google/callback')->assertRedirect(route('login'));

    $this->assertGuest();
    expect($user->fresh()->google_id)->toBeNull();
});

it('points the sign-up page Google button at the register intent and the sign-in page at plain sign-in', function (): void {
    $this->get('/register')->assertSee('intent=register', false);
    $this->get('/login')->assertDontSee('intent=register', false);
});

it('never takes over a password account with the same email; it links after a password sign-in (AC2)', function (): void {
    $user = User::factory()->customer()->create(['email' => 'thandi@gmail.com']);
    fakeGoogle();

    $this->get('/auth/google/callback')->assertRedirect(route('login'));
    $this->assertGuest();
    expect($user->fresh()->google_id)->toBeNull();

    Livewire::test(Login::class)->assertSet('linkingGoogle', true)
        ->set('password', 'password')->call('login')->assertHasNoErrors();

    expect($user->fresh()->google_id)->toBe('g-123')
        ->and(Activity::query()->where('description', 'google linked')->exists())->toBeTrue();
});

it('refuses Google accounts without a verified email (AC2)', function (): void {
    fakeGoogle(verified: false);

    $this->get('/auth/google/callback')->assertRedirect(route('login'));
    expect(GoogleSignIn::pendingSignUp())->toBeNull();
});

it('forgets a Google sign-up after 15 minutes', function (): void {
    fakeGoogle();
    $this->get('/auth/google/callback');

    $this->travel(16)->minutes();
    Livewire::withQueryParams(['with' => 'google'])->test(Register::class)->assertSet('withGoogle', false);
});

// --- Password reset (AC9) ------------------------------------------------------------------

it('emails a reset link and gives the same answer for unknown emails (AC9)', function (): void {
    Notification::fake();
    $user = User::factory()->customer()->create(['email' => 'thandi@example.com']);

    Livewire::test(ForgotPassword::class)->set('email', 'thandi@example.com')->call('send')->assertSet('sent', true);
    Livewire::test(ForgotPassword::class)->set('email', 'nobody@example.com')->call('send')->assertSet('sent', true);

    Notification::assertSentTo($user, ResetPasswordNotification::class);
    Notification::assertCount(1);
});

it('never emails reset links to admins (AC9, AC13)', function (): void {
    Notification::fake();
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $admin->assignRole(Role::AdminSuper->value);

    Livewire::test(ForgotPassword::class)->set('email', 'admin@example.com')->call('send')->assertSet('sent', true);
    Notification::assertNothingSent();
});

it('sets a new password from a valid reset link (AC9)', function (): void {
    $user = User::factory()->customer()->create(['email' => 'thandi@example.com']);
    $token = Password::createToken($user);

    Livewire::test(ResetPassword::class, ['token' => $token])->set('email', 'thandi@example.com')
        ->set('password', 'a-brand-new-password')->call('save')->assertRedirect(route('login'));

    expect(Hash::check('a-brand-new-password', $user->fresh()->password))->toBeTrue()
        ->and(Activity::query()->where('description', 'password reset')->exists())->toBeTrue();
});

it('rejects a wrong reset token (AC9)', function (): void {
    $user = User::factory()->customer()->create(['email' => 'thandi@example.com']);

    Livewire::test(ResetPassword::class, ['token' => 'nope'])->set('email', 'thandi@example.com')
        ->set('password', 'a-brand-new-password')->call('save')->assertHasErrors(['email']);
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('serves draft terms and privacy pages', function (string $path): void {
    $this->get($path)->assertOk()->assertSee('Reviewed by a South African attorney');
})->with(['/terms', '/privacy']);
