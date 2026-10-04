<?php

declare(strict_types=1);

use App\Contracts\Data\MessageChannel;
use App\Contracts\Data\OutgoingMessage;
use App\Contracts\MessagingChannel;
use App\Domain\Accounts\Actions\VerifyLoginCode;
use App\Domain\Accounts\Enums\ConsentType;
use App\Domain\Accounts\Enums\LoginStep;
use App\Domain\Accounts\Enums\Role;
use App\Domain\Accounts\Exceptions\LoginCodeRejected;
use App\Domain\Accounts\Support\LoginThrottle;
use App\Integrations\Fakes\FakeMessagingChannel;
use App\Livewire\Auth\Login;
use App\Models\Consent;
use App\Models\PhoneOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Event;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

const PHONE = '+27821234567';

function messaging(): FakeMessagingChannel
{
    return app(MessagingChannel::class);
}

function lastCode(): string
{
    $sent = messaging()->sent();

    return end($sent)->parameters['code'];
}

function requestCode(string $phone = '082 123 4567'): Testable
{
    return Livewire::test(Login::class)->set('phone', $phone)->call('sendCode');
}

function customer(array $attributes = []): User
{
    return User::factory()->customer()->create(['phone_e164' => PHONE, ...$attributes]);
}

// --- Phone entry -----------------------------------------------------------

it('shows the login page to guests', function (): void {
    $this->get('/login')->assertOk()->assertSeeLivewire(Login::class);
});

it('normalises SA mobile numbers to E.164 and sends a code (AC1)', function (string $input): void {
    requestCode($input)->assertHasNoErrors()->assertSet('step', LoginStep::Code);

    messaging()->assertSent('otp_code', fn (OutgoingMessage $message): bool => $message->phoneE164 === PHONE);
    expect(PhoneOtp::query()->sole()->phone_e164)->toBe(PHONE);
})->with(['082 123 4567', '+27 82 123 4567', '27821234567', '0821234567']);

it('rejects invalid and non-mobile numbers without sending (AC2)', function (string $input): void {
    requestCode($input)->assertHasErrors(['phone'])->assertSet('step', LoginStep::Phone);

    messaging()->assertNothingSent();
})->with(['12345', '011 123 4567', '+44 7700 900123', '', 'abc', '+', '++27', str_repeat('9', 40)]);

it('responds the same whether or not the number has an account (AC3)', function (): void {
    $unknown = requestCode('082 123 4567');
    customer(['phone_e164' => '+27831234567']);
    $known = requestCode('083 123 4567');

    expect($known->get('step'))->toBe($unknown->get('step'))
        ->and($known->html())->toContain('We sent a code to')
        ->and($unknown->html())->toContain('We sent a code to');
});

// --- Sending ----------------------------------------------------------------

it('sends a hashed 6-digit code that expires in 10 minutes over WhatsApp (AC4)', function (): void {
    $this->freezeTime();
    requestCode();

    $otp = PhoneOtp::query()->sole();
    $code = lastCode();

    expect($code)->toMatch('/^\d{6}$/')
        ->and($otp->code_hash)->not->toContain($code)
        ->and($otp->expires_at->getTimestamp())->toBe(now()->addMinutes(10)->getTimestamp())
        ->and($otp->channel)->toBe(MessageChannel::WhatsApp);
    messaging()->assertSent('otp_code', fn (OutgoingMessage $message): bool => $message->channel === MessageChannel::WhatsApp);
});

it('invalidates the previous code when a new one is sent (AC5)', function (): void {
    requestCode();
    $first = lastCode();
    $component = requestCode();

    if ($first === lastCode()) {
        $this->markTestSkipped('Random codes collided.');
    }

    $component->set('code', $first)->call('verifyCode')->assertHasErrors(['code']);
});

it('offers SMS only after 30 seconds and sends a new code by SMS (AC6)', function (): void {
    $this->freezeTime();
    $component = requestCode();

    $component->call('sendBySms')->assertHasErrors(['code']);
    messaging()->assertSent('otp_code', times: 1);

    $this->travel(31)->seconds();
    $component->call('sendBySms')->assertHasNoErrors();

    messaging()->assertSent('otp_code', fn (OutgoingMessage $message): bool => $message->channel === MessageChannel::Sms);
    expect(PhoneOtp::query()->latest('id')->first()->channel)->toBe(MessageChannel::Sms);
});

it('limits codes to 3 per number per 15 minutes (AC7)', function (): void {
    foreach (range(1, 3) as $ignored) {
        requestCode()->assertHasNoErrors();
    }

    requestCode('+27 82 123 4567')->assertHasErrors(['phone'])->assertSee('Too many codes requested');
    messaging()->assertSent('otp_code', times: 3);

    $this->travel(16)->minutes();
    requestCode()->assertHasNoErrors();
});

it('limits codes to 10 per IP address per hour (AC7)', function (): void {
    foreach (range(0, 9) as $i) {
        requestCode('0821234'.str_pad((string) $i, 3, '0', STR_PAD_LEFT))->assertHasNoErrors();
    }

    requestCode('0839999999')->assertHasErrors(['phone'])->assertSee('Too many codes requested');
});

// --- Verifying ----------------------------------------------------------------

it('logs in a returning customer with a valid code (AC8, AC15)', function (): void {
    $user = customer(['phone_verified_at' => null]);
    $component = requestCode();
    $sessionBefore = session()->getId();

    $component->set('code', lastCode())->call('verifyCode')->assertRedirect('/app');

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->phone_verified_at)->not->toBeNull()
        ->and(PhoneOtp::query()->sole()->consumed_at)->not->toBeNull()
        ->and(session()->getId())->not->toBe($sessionBefore);
});

it('counts wrong codes and shows attempts left (AC9)', function (): void {
    requestCode()->set('code', '000000')->call('verifyCode')
        ->assertHasErrors(['code'])
        ->assertSee('That code is incorrect')
        ->assertSee('4 attempts left');

    expect(PhoneOtp::query()->sole()->attempts)->toBe(1);
    $this->assertGuest();
});

it('invalidates a code after 5 wrong attempts (AC10)', function (): void {
    customer();
    $component = requestCode();
    $code = lastCode();
    $wrong = $code === '000000' ? '111111' : '000000';

    foreach (range(1, 5) as $ignored) {
        $component->set('code', $wrong)->call('verifyCode');
    }

    $component->set('code', $code)->call('verifyCode')->assertHasErrors(['code'])->assertSee('Request a new one');
    $this->assertGuest();
});

it('rejects expired and used codes (AC11)', function (): void {
    customer();
    $component = requestCode();
    $code = lastCode();

    $this->travel(11)->minutes();
    $component->set('code', $code)->call('verifyCode')->assertHasErrors(['code'])->assertSee('This code has expired');
    $this->assertGuest();
});

it('rejects a code that was already used (AC11)', function (): void {
    customer();
    requestCode();
    $code = lastCode();
    $verify = app(VerifyLoginCode::class);

    expect($verify->handle(PHONE, $code))->toBeInstanceOf(User::class);

    try {
        $verify->handle(PHONE, $code);
        $this->fail('A used code was accepted twice.');
    } catch (LoginCodeRejected $rejected) {
        expect($rejected->reason)->toBe(LoginCodeRejected::EXPIRED);
    }
});

it('limits code checks to 10 per IP per 15 minutes (AC12)', function (): void {
    $component = requestCode();

    foreach (range(1, 10) as $ignored) {
        $component->set('code', '000000')->call('verifyCode');
        PhoneOtp::query()->update(['attempts' => 0]);
    }

    $component->set('code', lastCode())->call('verifyCode')->assertHasErrors(['code'])->assertSee('Too many attempts');
    $this->assertGuest();
});

// --- New vs returning ------------------------------------------------------------

it('asks new customers for their details after a valid code (AC13)', function (): void {
    requestCode()->set('code', lastCode())->call('verifyCode')
        ->assertSet('step', LoginStep::Profile)
        ->assertSee('Tell us about you');

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

it('creates the customer with role and consent records (AC14)', function (): void {
    $component = requestCode()->set('code', lastCode())->call('verifyCode');

    $component->set('firstName', 'Thandi')->set('lastName', 'Nkosi')->set('email', '')
        ->set('acceptTerms', true)->set('acceptPrivacy', true)->set('marketing', false)
        ->call('register')->assertRedirect('/app');

    $user = User::query()->where('phone_e164', PHONE)->sole();
    $this->assertAuthenticatedAs($user);

    expect($user->hasRole(Role::Customer->value))->toBeTrue()
        ->and($user->first_name)->toBe('Thandi')
        ->and($user->email)->toBeNull()
        ->and($user->phone_verified_at)->not->toBeNull()
        ->and($user->public_id)->toHaveLength(26)
        ->and(Consent::query()->where('user_id', $user->id)->pluck('type')->map->value->sort()->values()->all())
        ->toBe([ConsentType::Privacy->value, ConsentType::Terms->value]);

    $consent = Consent::query()->where('type', ConsentType::Terms)->sole();
    expect($consent->version)->toBe('2026-10-draft')
        ->and($consent->ip)->toBe('127.0.0.1')
        ->and($consent->granted_at)->not->toBeNull()
        ->and($consent->user_agent)->toBe('Symfony')
        ->and(Activity::query()->where('description', 'account created')->exists())->toBeTrue()
        ->and(Activity::query()->where('description', 'consent granted')->count())->toBe(2);
});

it('records marketing consent only when ticked (AC14)', function (): void {
    requestCode()->set('code', lastCode())->call('verifyCode')
        ->set('firstName', 'Thandi')->set('lastName', 'Nkosi')
        ->set('acceptTerms', true)->set('acceptPrivacy', true)->set('marketing', true)
        ->call('register');

    expect(Consent::query()->where('type', ConsentType::Marketing)->exists())->toBeTrue();
});

it('requires names and both legal consents (AC13)', function (): void {
    requestCode()->set('code', lastCode())->call('verifyCode')
        ->set('firstName', '')->set('lastName', '')->set('acceptTerms', false)->set('acceptPrivacy', false)
        ->call('register')
        ->assertHasErrors(['firstName', 'lastName', 'acceptTerms', 'acceptPrivacy']);

    expect(User::query()->count())->toBe(0);
});

it('cannot register once the verified phone has lapsed', function (): void {
    $component = requestCode()->set('code', lastCode())->call('verifyCode')->assertSet('step', LoginStep::Profile);

    $this->travel(16)->minutes();

    $component->set('firstName', 'Thandi')->set('lastName', 'Nkosi')->set('acceptTerms', true)->set('acceptPrivacy', true)
        ->call('register')->assertHasErrors(['phone'])->assertSet('step', LoginStep::Phone);

    expect(User::query()->count())->toBe(0);
    $this->assertGuest();
});

it('cannot tamper with the verified phone number from the browser', function (): void {
    requestCode()->set('phoneE164', '+27839999999');
})->throws(CannotUpdateLockedPropertyException::class);

it('keeps customers logged in for 30 days only when asked (AC16)', function (bool $remember): void {
    customer();

    requestCode()->set('code', lastCode())->set('remember', $remember)->call('verifyCode')->assertRedirect('/app');

    $guard = auth()->guard('web');
    expect(Cookie::hasQueued($guard->getRecallerName()))->toBe($remember)
        ->and(new ReflectionProperty($guard, 'rememberDuration')->getValue($guard))->toBe(30 * 24 * 60);
})->with(['remember' => true, 'session only' => false]);

it('logs out and returns home (AC17)', function (): void {
    $this->actingAs(customer())->post('/logout')->assertRedirect('/');
    $this->assertGuest();
});

it('shows the account home only to logged-in customers with a verified phone', function (): void {
    $this->get('/app')->assertRedirect('/login');
    $this->actingAs(customer(['first_name' => 'Thandi']))->get('/app')->assertOk()->assertSee('Hi Thandi');
});

it('keeps admins without a verified phone out of the customer area', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::AdminSuper->value);

    $this->actingAs($admin)->get('/app')->assertForbidden();
});

it('sends logged-in customers from /login to their account home', function (): void {
    $this->actingAs(customer())->get('/login')->assertRedirect('/app');
});

// --- Safety -------------------------------------------------------------------

it('refuses phone login for admin accounts exactly like any other number (AC18)', function (): void {
    $admin = User::factory()->create(['phone_e164' => PHONE, 'phone_verified_at' => now()]);
    $admin->assignRole(Role::AdminSupport->value);
    $wrong = fn (Testable $component): string => $component->set('code', '000000')->call('verifyCode')->errors()->first('code');

    $adminComponent = requestCode()->assertSet('step', LoginStep::Code)->assertSee('We sent a code to');
    messaging()->assertNothingSent();
    $customerComponent = requestCode('083 123 4567');

    foreach (range(1, 6) as $ignored) {
        expect($wrong($adminComponent))->toBe($wrong($customerComponent));
    }

    $this->assertGuest();
});

it('refuses deleted accounts politely, like any other number', function (): void {
    customer()->delete();

    $component = requestCode()->assertSet('step', LoginStep::Code);
    messaging()->assertNothingSent();

    $component->set('code', '123456')->call('verifyCode')->assertHasErrors(['code'])->assertSee('That code is incorrect');
    $this->assertGuest();
});

it('turns a second sign-up for the same number away politely', function (): void {
    $component = requestCode()->set('code', lastCode())->call('verifyCode');
    customer();

    $component->set('firstName', 'Thandi')->set('lastName', 'Nkosi')->set('acceptTerms', true)->set('acceptPrivacy', true)
        ->call('register')->assertHasErrors(['phone'])->assertSee('already has an account')->assertSet('step', LoginStep::Phone);

    expect(User::query()->count())->toBe(1);
});

it('lower-cases emails and never reveals that an email is taken', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);
    $register = function (string $phone, string $email): void {
        requestCode($phone)->set('code', lastCode())->call('verifyCode')
            ->set('firstName', 'Thandi')->set('lastName', 'Nkosi')->set('email', $email)
            ->set('acceptTerms', true)->set('acceptPrivacy', true)
            ->call('register')->assertHasNoErrors()->assertRedirect('/app');
        auth()->logout();
    };

    $register('082 123 4567', '  Thandi@Example.COM ');
    $register('083 123 4567', 'Taken@example.com');

    expect(User::query()->where('phone_e164', PHONE)->value('email'))->toBe('thandi@example.com')
        ->and(User::query()->where('phone_e164', '+27831234567')->value('email'))->toBeNull();
});

it('limits sign-up attempts per IP', function (): void {
    $component = requestCode()->set('code', lastCode())->call('verifyCode');

    foreach (range(1, 5) as $ignored) {
        $component->set('firstName', '')->call('register');
    }

    $component->set('firstName', 'Thandi')->set('lastName', 'Nkosi')->set('acceptTerms', true)->set('acceptPrivacy', true)
        ->call('register')->assertSee('Too many attempts');
    expect(User::query()->count())->toBe(0);
});

it('counts SMS codes toward the rate limit (AC6, AC7)', function (): void {
    $this->freezeTime();
    $component = requestCode();

    foreach (range(1, 2) as $ignored) {
        $this->travel(31)->seconds();
        $component->call('sendBySms')->assertHasNoErrors();
    }

    $this->travel(31)->seconds();
    $component->call('sendBySms')->assertHasErrors(['code'])->assertSee('Too many codes requested');
    messaging()->assertSent('otp_code', times: 3);
});

it('includes a daily per-number cap in the send limits', function (): void {
    $limits = LoginThrottle::sendLimits(PHONE, '10.0.0.1');

    expect($limits)->toHaveCount(3)
        ->and(collect($limits)->map(fn ($limit): array => [$limit->maxAttempts, $limit->decaySeconds])->all())
        ->toBe([[3, 15 * 60], [10, 24 * 60 * 60], [10, 60 * 60]])
        ->and(collect($limits)->pluck('key')->implode(' '))->not->toContain('821234567')
        ->and($limits[0]->key)->not->toContain(hash('sha256', PHONE));
});

it('discards the code and says so when the message cannot be sent', function (): void {
    messaging()->failNextSend();

    requestCode()->assertHasErrors(['phone'])->assertSee("We couldn't send your code")->assertSet('step', LoginStep::Phone);

    expect(PhoneOtp::query()->count())->toBe(0);
});

it('keeps a brand-new customer logged in when asked (AC16)', function (): void {
    requestCode()->set('code', lastCode())->set('remember', true)->call('verifyCode')
        ->set('firstName', 'Thandi')->set('lastName', 'Nkosi')->set('acceptTerms', true)->set('acceptPrivacy', true)
        ->call('register');

    expect(Cookie::hasQueued(auth()->guard('web')->getRecallerName()))->toBeTrue();
});

it('sends logged-in admins from /login to the admin panel', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::AdminSuper->value);

    $this->actingAs($admin)->get('/login')->assertRedirect('/admin');
});

it('stops a login form left open from acting after logging in elsewhere', function (): void {
    $component = Livewire::test(Login::class)->set('phone', '082 123 4567');
    $this->actingAs(customer(['phone_e164' => '+27831234567']));

    $component->call('sendCode')->assertRedirect('/app');
    messaging()->assertNothingSent();
});

it('never writes codes or full phone numbers to the logs (AC19)', function (): void {
    app()->detectEnvironment(fn (): string => 'local');
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
        $logged[] = $event->message.' '.json_encode($event->context);
    });

    $component = requestCode();
    $code = lastCode();
    $component->set('code', '000000')->call('verifyCode');
    $component->set('code', $code)->call('verifyCode');

    expect($logged)->not->toBeEmpty();
    foreach ($logged as $line) {
        expect($line)->not->toContain($code)->not->toContain('821234567');
    }
});

it('shows the code on screen in local development only (AC20)', function (string $environment, bool $shown): void {
    app()->detectEnvironment(fn (): string => $environment);

    $component = requestCode();

    expect(str_contains($component->html(), 'Development: your code is '.lastCode()))->toBe($shown);
})->with([
    'local' => ['local', true],
    'testing' => ['testing', false],
    'staging' => ['staging', false],
    'production' => ['production', false],
]);

it('serves draft terms and privacy pages', function (string $path): void {
    $this->get($path)->assertOk()->assertSee('Draft — not yet in force');
})->with(['/terms', '/privacy']);

it('prunes login codes after 90 days', function (): void {
    requestCode();
    $this->travel(91)->days();
    requestCode('083 123 4567');

    $this->artisan('model:prune', ['--model' => PhoneOtp::class]);

    expect(PhoneOtp::query()->count())->toBe(1);
});
