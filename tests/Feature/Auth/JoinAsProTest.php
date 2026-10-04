<?php

declare(strict_types=1);

use App\Contracts\MessagingChannel;
use App\Domain\Accounts\Enums\ConsentType;
use App\Domain\Accounts\Enums\LoginStep;
use App\Domain\Accounts\Enums\Role;
use App\Integrations\Fakes\FakeMessagingChannel;
use App\Livewire\Auth\Login;
use App\Livewire\Pros\BecomePro;
use App\Models\Consent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

const PRO_PHONE = '+27821234567';

function proCode(): string
{
    /** @var FakeMessagingChannel $messaging */
    $messaging = app(MessagingChannel::class);
    $sent = $messaging->sent();

    return end($sent)->parameters['code'];
}

function proLogin(string $phone = '082 123 4567'): Testable
{
    return Livewire::withQueryParams(['as' => 'pro'])->test(Login::class)->set('phone', $phone)->call('sendCode');
}

function consentTypes(User $user): array
{
    return Consent::query()->where('user_id', $user->id)->pluck('type')->map->value->sort()->values()->all();
}

it('explains joining as a pro and links to the pro login (AC1)', function (): void {
    $this->get('/pros/join')->assertOk()
        ->assertSee('Free to join')
        ->assertSee('href="'.route('login', ['as' => 'pro']).'"', false);
});

it('links tradespeople to the join page from the home page (AC1)', function (): void {
    $this->get('/')->assertOk()->assertSee('href="'.route('pros.join').'"', false);
});

it('sends logged-in customers from the join page to the become-a-pro step (AC1, AC5)', function (): void {
    $this->actingAs(User::factory()->customer()->create())
        ->get('/pros/join')->assertOk()
        ->assertSee('href="'.route('pros.become').'"', false);
});

it('uses the spec 001 phone and code steps with pro wording (AC2)', function (): void {
    Livewire::withQueryParams(['as' => 'pro'])->test(Login::class)->assertSee('Join Sortd as a pro');

    proLogin()->assertSet('step', LoginStep::Code)->assertSet('asPro', true)->assertSee('We sent a code to');
});

it('signs up a new pro with the pro role and three consents (AC3, AC4, AC6)', function (): void {
    $component = proLogin()->set('code', proCode())->call('verifyCode')
        ->assertSet('step', LoginStep::Profile)
        ->assertSee('pro agreement');

    $component->set('firstName', 'Sipho')->set('lastName', 'Dlamini')
        ->set('acceptTerms', true)->set('acceptPrivacy', true)->set('acceptProAgreement', true)
        ->call('register')->assertRedirect(route('pros.welcome'));

    $user = User::query()->where('phone_e164', PRO_PHONE)->sole();

    expect($user->hasRole(Role::Pro->value))->toBeTrue()
        ->and($user->hasRole(Role::Customer->value))->toBeFalse()
        ->and(consentTypes($user))->toBe(['privacy', 'pro_agreement', 'terms'])
        ->and(Consent::query()->where('type', ConsentType::ProAgreement)->sole()->version)->toBe('2026-10-draft')
        ->and(Consent::query()->where('type', ConsentType::ProAgreement)->sole()->ip)->toBe('127.0.0.1')
        ->and(Consent::query()->where('type', ConsentType::ProAgreement)->sole()->user_agent)->toBe('Symfony')
        ->and(Activity::query()->where('description', 'account created')->exists())->toBeTrue()
        ->and(Activity::query()->where('description', 'consent granted')->count())->toBe(3);
});

it('requires the pro agreement for pro sign-ups (AC3)', function (): void {
    proLogin()->set('code', proCode())->call('verifyCode')
        ->set('firstName', 'Sipho')->set('lastName', 'Dlamini')
        ->set('acceptTerms', true)->set('acceptPrivacy', true)->set('acceptProAgreement', false)
        ->call('register')->assertHasErrors(['acceptProAgreement']);

    expect(User::query()->count())->toBe(0);
});

it('does not ask customers for the pro agreement', function (): void {
    Livewire::test(Login::class)->set('phone', '082 123 4567')->call('sendCode')
        ->set('code', proCode())->call('verifyCode')
        ->assertDontSee('pro agreement');
});

it('sends an existing customer who joins as a pro to the agreement step (AC5)', function (): void {
    User::factory()->customer()->create(['phone_e164' => PRO_PHONE]);

    proLogin()->set('code', proCode())->call('verifyCode')->assertRedirect(route('pros.become'));
});

it('adds the pro role to an existing customer who accepts the agreement (AC5)', function (): void {
    $user = User::factory()->customer()->create();

    $this->actingAs($user);
    Livewire::test(BecomePro::class)->set('acceptProAgreement', true)->call('confirm')->assertRedirect(route('pros.welcome'));

    $user->refresh();
    expect($user->hasRole(Role::Pro->value))->toBeTrue()
        ->and($user->hasRole(Role::Customer->value))->toBeTrue()
        ->and(consentTypes($user))->toBe(['pro_agreement'])
        ->and(Activity::query()->where('description', 'pro role granted')->exists())->toBeTrue();
});

it('leaves a customer unchanged if they do not accept the agreement (AC5)', function (): void {
    $user = User::factory()->customer()->create();

    $this->actingAs($user);
    Livewire::test(BecomePro::class)->call('confirm')->assertHasErrors(['acceptProAgreement']);

    expect($user->fresh()->hasRole(Role::Pro->value))->toBeFalse()
        ->and(Consent::query()->count())->toBe(0);
});

it('sends existing pros straight to their welcome page', function (): void {
    $user = User::factory()->customer()->create();
    $user->assignRole(Role::Pro->value);

    $this->actingAs($user)->get('/pros/become')->assertRedirect(route('pros.welcome'));
});

it('lands a returning pro on the pro welcome page (AC6)', function (): void {
    $pro = User::factory()->pro()->create(['phone_e164' => PRO_PHONE, 'first_name' => 'Sipho']);

    Livewire::test(Login::class)->set('phone', '082 123 4567')->call('sendCode')
        ->set('code', proCode())->call('verifyCode')->assertRedirect(route('pros.welcome'));

    $this->actingAs($pro)->get('/pros/welcome')->assertOk()
        ->assertSee('Thanks, Sipho')
        ->assertSee('Start your application'); // Spec 008 replaced the "opens soon" holding text.
});

it('lets a pro who is also a customer reach both areas (AC7)', function (): void {
    $user = User::factory()->customer()->create();
    $user->assignRole(Role::Pro->value);

    $this->actingAs($user)->get('/pros/welcome')->assertOk()->assertSee('href="'.route('account.home').'"', false);
    $this->actingAs($user)->get('/app')->assertOk();
});

it('keeps the pro panel closed (AC8)', function (): void {
    $this->actingAs(User::factory()->pro()->create())->get('/pro')->assertNotFound();
});

it('sends non-pros from the pro welcome page to the join page (AC9)', function (): void {
    $this->actingAs(User::factory()->customer()->create())->get('/pros/welcome')->assertRedirect(route('pros.join'));

    auth()->logout();
    $this->get('/pros/welcome')->assertRedirect(route('login'));
});

it('refuses admin accounts on the pro sign-up exactly as on customer login (AC10)', function (): void {
    $admin = User::factory()->create(['phone_e164' => PRO_PHONE, 'phone_verified_at' => now()]);
    $admin->assignRole(Role::AdminSupport->value);

    /** @var FakeMessagingChannel $messaging */
    $messaging = app(MessagingChannel::class);

    proLogin()->assertSet('step', LoginStep::Code)
        ->set('code', '000000')->call('verifyCode')->assertHasErrors(['code']);

    $messaging->assertNothingSent();
    $this->assertGuest();
});

it('never lets an admin become a pro', function (): void {
    $admin = User::factory()->customer()->create();
    $admin->assignRole(Role::AdminSupport->value);

    $this->actingAs($admin);
    Livewire::test(BecomePro::class)->set('acceptProAgreement', true)->call('confirm')->assertForbidden();

    expect($admin->fresh()->hasRole(Role::Pro->value))->toBeFalse();
});

it('serves the draft pro agreement (AC11)', function (): void {
    $this->get('/pros/agreement')->assertOk()->assertSee('Draft — not yet in force')->assertSee('2026-10-draft');
});

it('lands a pro who is also a customer on the pro welcome page (AC6)', function (): void {
    $user = User::factory()->customer()->create(['phone_e164' => PRO_PHONE]);
    $user->assignRole(Role::Pro->value);

    Livewire::test(Login::class)->set('phone', '082 123 4567')->call('sendCode')
        ->set('code', proCode())->call('verifyCode')->assertRedirect(route('pros.welcome'));
});

it('shows the welcome page with a log-out button (AC6)', function (): void {
    $this->actingAs(User::factory()->pro()->create(['first_name' => 'Sipho']))->get('/pros/welcome')
        ->assertSee('Thanks, Sipho — tell us about your business so we can check your details.')
        ->assertSee('action="'.route('logout').'"', false);
});

it('sends a logged-in customer following a join-as-pro link to the agreement step', function (): void {
    $this->actingAs(User::factory()->customer()->create())->get('/login?as=pro')->assertRedirect(route('pros.become'));
});

it('keeps pro-only accounts out of the customer area', function (): void {
    $this->actingAs(User::factory()->pro()->create())->get('/app')->assertRedirect(route('pros.welcome'));
});

it('records the pro agreement only once when confirmed twice', function (): void {
    $user = User::factory()->customer()->create();
    $action = app(App\Domain\Accounts\Actions\BecomePro::class);

    $action->handle($user, '127.0.0.1', 'Symfony');
    $action->handle(User::query()->find($user->id), '127.0.0.1', 'Symfony');

    expect(Consent::query()->where('type', ConsentType::ProAgreement)->count())->toBe(1)
        ->and(Activity::query()->where('description', 'pro role granted')->count())->toBe(1);
});
