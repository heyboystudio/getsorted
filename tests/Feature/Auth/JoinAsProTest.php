<?php

declare(strict_types=1);

use App\Domain\Accounts\Enums\ConsentType;
use App\Domain\Accounts\Enums\Role;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Models\Consent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);
});

function proSignIn(string $email): Testable
{
    return Livewire::withQueryParams(['as' => 'pro'])->test(Login::class)->set('email', $email)->set('password', 'password')->call('login');
}

function consentTypes(User $user): array
{
    return Consent::query()->where('user_id', $user->id)->pluck('type')->map->value->sort()->values()->all();
}

it('explains joining as a pro and links to the pro sign-up (AC1, spec 014)', function (): void {
    $this->get('/pros/join')->assertOk()
        ->assertSee('Free to join')
        ->assertSee('href="'.route('pros.register').'"', false);
});

it('links tradespeople to the join page from the home page (AC1)', function (): void {
    $this->get('/')->assertOk()->assertSee('href="'.route('pros.join').'"', false);
});

it('tells a signed-in client that pro accounts are separate, with no way to convert theirs (founder 2026-10-07)', function (): void {
    $this->actingAs(User::factory()->customer()->create())
        ->get('/pros/join')->assertOk()
        ->assertSee('Pro accounts are separate')->assertSee('action="'.route('logout').'"', false)
        ->assertDontSee('/pros/become');
});

it('has separate sign-up pages for clients and pros', function (): void {
    $this->get('/register')->assertOk()->assertSee('Create your client account')->assertDontSee('pro agreement')->assertSee(route('pros.register'), false);
    $this->get('/pros/register')->assertOk()->assertSee('Join GetSorted as a pro')->assertSee('pro agreement')->assertDontSee('Create your client account');
});

it('sends old pro sign-up links to the pro page', function (): void {
    $this->get('/register?as=pro')->assertRedirect(route('pros.register'));
});

it('uses the pro sign-up page with pro wording (AC2, spec 014)', function (): void {
    Livewire::test(Register::class, ['as' => 'pro'])->assertSet('asPro', true)->assertSee('Join GetSorted as a pro');
});

it('signs up a new pro with the pro role and three consents (AC3, AC4, AC6)', function (): void {
    $component = Livewire::test(Register::class, ['as' => 'pro']);
    foreach (['firstName' => 'Sipho', 'lastName' => 'Dlamini', 'email' => 'sipho@example.com', 'password' => 'long-enough-password',
        'acceptTerms' => true, 'acceptPrivacy' => true, 'acceptProAgreement' => true] as $field => $value) {
        $component->set($field, $value);
    }
    $component->call('register')->assertRedirect(route('pros.welcome'));

    $user = User::query()->where('email', 'sipho@example.com')->sole();

    expect($user->hasRole(Role::Pro->value))->toBeTrue()
        ->and($user->hasRole(Role::Customer->value))->toBeFalse()
        ->and(consentTypes($user))->toBe(['privacy', 'pro_agreement', 'terms'])
        ->and(Consent::query()->where('type', ConsentType::ProAgreement)->sole()->version)->toBe('2026-10-draft')
        ->and(Consent::query()->where('type', ConsentType::ProAgreement)->sole()->ip)->toBe('127.0.0.1')
        ->and(Consent::query()->where('type', ConsentType::ProAgreement)->sole()->user_agent)->toBe('Symfony')
        ->and(Activity::query()->where('description', 'account created')->exists())->toBeTrue()
        ->and(Activity::query()->where('description', 'consent granted')->count())->toBe(3);
});

it('does not ask customers for the pro agreement', function (): void {
    Livewire::test(Register::class)->assertDontSee('pro agreement');
});

it('keeps client accounts and pro accounts apart: there is no way to add the pro role to a client (founder 2026-10-07)', function (): void {
    $user = User::factory()->customer()->create();

    $this->actingAs($user)->get('/pros/become')->assertNotFound();
    expect($user->fresh()->hasRole(Role::Pro->value))->toBeFalse();
});

it('does not let a pro sign-up reuse a client email', function (): void {
    User::factory()->customer()->create(['email' => 'thandi@example.com']);
    $component = Livewire::test(Register::class, ['as' => 'pro']);
    foreach (['firstName' => 'Thandi', 'lastName' => 'M', 'email' => 'thandi@example.com', 'password' => 'long-enough-password', 'acceptTerms' => true, 'acceptPrivacy' => true, 'acceptProAgreement' => true] as $field => $value) {
        $component->set($field, $value);
    }

    $component->call('register')->assertHasErrors(['email']);
    expect(User::query()->where('email', 'thandi@example.com')->sole()->hasRole(Role::Pro->value))->toBeFalse();
});

it('lands a returning pro on the pro welcome page (AC6)', function (): void {
    $pro = User::factory()->pro()->create(['email' => 'sipho@example.com', 'first_name' => 'Sipho']);

    Livewire::test(Login::class)->set('email', 'sipho@example.com')->set('password', 'password')->call('login')->assertRedirect(route('pros.welcome'));

    $this->actingAs($pro)->get('/pros/welcome')->assertOk()
        ->assertSee('Thanks, Sipho')
        ->assertSee('Start your application'); // Spec 008 replaced the "opens soon" holding text.
});

it('does not link the pro area to a client account (founder 2026-10-07)', function (): void {
    $this->actingAs(User::factory()->pro()->create())->get('/pros/welcome')->assertOk()
        ->assertDontSee('customer account')->assertDontSee('href="'.route('account.home').'"', false);
});

it('keeps the pro panel closed (AC8)', function (): void {
    $this->actingAs(User::factory()->pro()->create())->get('/pro')->assertNotFound();
});

it('sends non-pros from the pro welcome page to the join page (AC9)', function (): void {
    $this->actingAs(User::factory()->customer()->create())->get('/pros/welcome')->assertRedirect(route('pros.join'));

    auth()->logout();
    $this->get('/pros/welcome')->assertRedirect(route('login'));
});

it('refuses admin accounts on the pro sign-in like any wrong password (AC10)', function (): void {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $admin->assignRole(Role::AdminSupport->value);

    proSignIn('admin@example.com')->assertHasErrors(['email']);
    $this->assertGuest();
});

it('serves the draft pro agreement (AC11)', function (): void {
    $this->get('/pros/agreement')->assertOk()->assertSee('Draft — not yet in force')->assertSee('2026-10-draft');
});

it('lands a pro who is also a customer on the pro welcome page (AC6)', function (): void {
    $user = User::factory()->customer()->create(['email' => 'both@example.com']);
    $user->assignRole(Role::Pro->value);

    Livewire::test(Login::class)->set('email', 'both@example.com')->set('password', 'password')->call('login')->assertRedirect(route('pros.welcome'));
});

it('shows the welcome page with a log-out button (AC6)', function (): void {
    $this->actingAs(User::factory()->pro()->create(['first_name' => 'Sipho']))->get('/pros/welcome')
        ->assertSee('Thanks, Sipho — tell us about your business so we can check your details.')
        ->assertSee('action="'.route('logout').'"', false);
});

it('signs a client who uses the pro sign-in into their client account, nothing more', function (): void {
    $this->actingAs(User::factory()->customer()->create())->get('/login?as=pro')->assertRedirect();
});

it('keeps pro-only accounts out of the customer area', function (): void {
    $this->actingAs(User::factory()->pro()->create())->get('/app')->assertRedirect(route('pros.welcome'));
});
