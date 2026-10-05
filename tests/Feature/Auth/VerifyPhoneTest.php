<?php

declare(strict_types=1);

use App\Contracts\Data\MessageChannel;
use App\Contracts\Data\OutgoingMessage;
use App\Contracts\MessagingChannel;
use App\Domain\Accounts\Actions\VerifyPhoneCode;
use App\Domain\Accounts\Exceptions\LoginCodeRejected;
use App\Domain\Accounts\Support\LoginThrottle;
use App\Integrations\Fakes\FakeMessagingChannel;
use App\Livewire\Auth\VerifyPhone;
use App\Models\PhoneOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/** Spec 014, AC4–AC7: adding and verifying a mobile after sign-up, with spec 001's code rules. */
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

function newCustomer(): User
{
    return User::factory()->customer()->withoutPhone()->create();
}

function requestCode(string $phone = '082 123 4567', ?User $user = null): Testable
{
    test()->actingAs($user ?? newCustomer());

    return Livewire::test(VerifyPhone::class)->set('phone', $phone)->call('sendCode');
}

it('normalises SA mobile numbers and sends a code by SMS first (AC4, founder 2026-10-05)', function (string $input): void {
    requestCode($input)->assertHasNoErrors()->assertSet('phoneE164', PHONE);

    messaging()->assertSent('otp_code', fn (OutgoingMessage $message): bool => $message->phoneE164 === PHONE && $message->channel === MessageChannel::Sms);
})->with(['082 123 4567', '+27 82 123 4567', '27821234567', '0821234567']);

it('rejects invalid and non-mobile numbers without sending (AC4)', function (string $input): void {
    requestCode($input)->assertHasErrors(['phone'])->assertSet('phoneE164', null);

    messaging()->assertNothingSent();
})->with(['12345', '011 123 4567', '+44 7700 900123', '', 'abc', str_repeat('9', 40)]);

it('sends a hashed 6-digit code that expires in 10 minutes (AC4)', function (): void {
    $this->freezeTime();
    requestCode();

    $otp = PhoneOtp::query()->sole();
    $code = lastCode();

    expect($code)->toMatch('/^\d{6}$/')
        ->and($otp->code_hash)->not->toContain($code)
        ->and($otp->expires_at->getTimestamp())->toBe(now()->addMinutes(10)->getTimestamp());
});

it('verifies the mobile with the right code and continues to where the user was going (AC5, AC6)', function (): void {
    $user = newCustomer();
    session()->put('url.intended', url('/app/properties'));

    requestCode(user: $user)->set('code', lastCode())->call('verifyCode')->assertRedirect('/app/properties');

    $user->refresh();
    expect($user->phone_e164)->toBe(PHONE)->and($user->phone_verified_at)->not->toBeNull()
        ->and(PhoneOtp::query()->sole()->consumed_at)->not->toBeNull()
        ->and(Activity::query()->where('description', 'phone verified')->exists())->toBeTrue();
});

it('refuses a number already linked to another account, before sending (AC5)', function (): void {
    User::factory()->customer()->create(['phone_e164' => PHONE]);

    requestCode()->assertHasErrors(['phone'])->assertSee('already linked to another account');
    messaging()->assertNothingSent();
});

it('counts wrong codes and shows attempts left', function (): void {
    requestCode()->set('code', '000000')->call('verifyCode')
        ->assertHasErrors(['code'])->assertSee('That code is incorrect')->assertSee('4 attempts left');

    expect(PhoneOtp::query()->sole()->attempts)->toBe(1);
});

it('invalidates a code after 5 wrong attempts', function (): void {
    $component = requestCode();
    $code = lastCode();
    $wrong = $code === '000000' ? '111111' : '000000';

    foreach (range(1, 5) as $ignored) {
        $component->set('code', $wrong)->call('verifyCode');
    }

    $component->set('code', $code)->call('verifyCode')->assertHasErrors(['code'])->assertSee('Request a new one');
});

it('rejects expired codes', function (): void {
    $component = requestCode();
    $code = lastCode();

    $this->travel(11)->minutes();
    $component->set('code', $code)->call('verifyCode')->assertHasErrors(['code'])->assertSee('This code has expired');
});

it('rejects a code that was already used', function (): void {
    $user = newCustomer();
    requestCode(user: $user);
    $code = lastCode();
    $verify = app(VerifyPhoneCode::class);
    $verify->handle($user, PHONE, $code);

    try {
        $verify->handle($user, PHONE, $code);
        $this->fail('A used code was accepted twice.');
    } catch (LoginCodeRejected $rejected) {
        expect($rejected->reason)->toBe(LoginCodeRejected::EXPIRED);
    }
});

it('offers WhatsApp only after 30 seconds (AC4)', function (): void {
    $this->freezeTime();
    $component = requestCode()->assertSee('WhatsApp available in');

    $component->call('sendByOtherChannel')->assertHasErrors(['code']);
    $this->travel(31)->seconds();
    $component->call('sendByOtherChannel')->assertHasNoErrors();

    messaging()->assertSent('otp_code', fn (OutgoingMessage $message): bool => $message->channel === MessageChannel::WhatsApp);
});

it('can be switched back to WhatsApp first by setting', function (): void {
    config()->set('sortd.otp.default_channel', 'whatsapp');

    requestCode()->assertSee('SMS available in');
    messaging()->assertSent('otp_code', fn (OutgoingMessage $message): bool => $message->channel === MessageChannel::WhatsApp);
});

it('limits codes to 3 per number per 15 minutes', function (): void {
    $user = newCustomer();
    foreach (range(1, 3) as $ignored) {
        requestCode(user: $user)->assertHasNoErrors();
    }

    requestCode(user: $user)->assertHasErrors(['phone'])->assertSee('Too many codes requested');
    messaging()->assertSent('otp_code', times: 3);
});

it('includes a daily per-number cap in the send limits', function (): void {
    expect(LoginThrottle::sendLimits(PHONE, '10.0.0.1'))->toHaveCount(3);
});

it('keeps the old number until a new one is verified, then switches (AC7)', function (): void {
    $user = User::factory()->customer()->create(['phone_e164' => '+27831234567']);

    $component = requestCode(user: $user);
    expect($user->fresh()->phone_e164)->toBe('+27831234567');

    $component->set('code', lastCode())->call('verifyCode');
    expect($user->fresh()->phone_e164)->toBe(PHONE);
});

it('discards the code and says so when the message cannot be sent', function (): void {
    messaging()->failNextSend();

    requestCode()->assertHasErrors(['phone'])->assertSee("We couldn't send your code");
    expect(PhoneOtp::query()->count())->toBe(0);
});

it('never writes codes or full phone numbers to the logs', function (): void {
    app()->detectEnvironment(fn (): string => 'local');
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
        $logged[] = $event->message.' '.json_encode($event->context);
    });

    $component = requestCode();
    $code = lastCode();
    $component->set('code', $code)->call('verifyCode');

    expect($logged)->not->toBeEmpty();
    foreach ($logged as $line) {
        expect($line)->not->toContain($code)->not->toContain('821234567');
    }
});

it('shows the code on screen in local development and on the private test site only (decision 037)', function (string $environment, bool $shown): void {
    app()->detectEnvironment(fn (): string => $environment);
    $component = requestCode();

    expect(str_contains($component->html(), 'Development: your code is '.lastCode()))->toBe($shown);
})->with([
    'local' => ['local', true],
    'preview' => ['preview', true],
    'testing' => ['testing', false],
    'staging' => ['staging', false],
    'production' => ['production', false],
]);

it('saves the mobile without a code on the test site when codes are switched off (decision 041)', function (): void {
    app()->detectEnvironment(fn (): string => 'preview');
    config()->set('sortd.otp.phone_codes_enabled', false);
    $user = newCustomer();

    requestCode(user: $user)->assertHasNoErrors()->assertRedirect(route('account.home'));

    messaging()->assertNothingSent();
    expect($user->fresh()->phone_e164)->toBe(PHONE)->and($user->fresh()->phone_verified_at)->not->toBeNull();
});

it('still refuses a taken number when codes are switched off', function (): void {
    app()->detectEnvironment(fn (): string => 'preview');
    config()->set('sortd.otp.phone_codes_enabled', false);
    User::factory()->customer()->create(['phone_e164' => PHONE]);

    requestCode()->assertHasErrors(['phone']);
});

it('never skips codes in staging or production, even when switched off', function (string $environment): void {
    app()->detectEnvironment(fn (): string => $environment);
    config()->set('sortd.otp.phone_codes_enabled', false);
    $user = newCustomer();

    requestCode(user: $user);

    messaging()->assertSent('otp_code');
    expect($user->fresh()->phone_verified_at)->toBeNull();
})->with(['staging', 'production']);

it('prunes codes after 90 days', function (): void {
    requestCode();
    $this->travel(91)->days();
    requestCode('083 123 4567');

    $this->artisan('model:prune', ['--model' => PhoneOtp::class]);

    expect(PhoneOtp::query()->count())->toBe(1);
});
