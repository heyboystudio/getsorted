<?php

declare(strict_types=1);

use App\Contracts\Data\MessageChannel;
use App\Contracts\Data\OutgoingMessage;
use App\Contracts\MessagingChannel;
use App\Integrations\Fakes\FakeMessagingChannel;
use App\Integrations\Twilio\TwilioMessagingChannel;
use App\Providers\IntegrationServiceProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/** Decision 040: WhatsApp and SMS through Twilio. */
function twilioChannel(): TwilioMessagingChannel
{
    return new TwilioMessagingChannel('AC123', 'secret-token', '+15550001111', '+14155238886');
}

it('sends WhatsApp messages with the whatsapp: prefix and the code in the text', function (): void {
    Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);

    $receipt = twilioChannel()->send(new OutgoingMessage('+27821234567', 'otp_code', ['code' => '123456']));

    expect($receipt->providerMessageId)->toBe('SM1')->and($receipt->channel)->toBe(MessageChannel::WhatsApp);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC123/Messages.json'
        && $request['To'] === 'whatsapp:+27821234567'
        && $request['From'] === 'whatsapp:+14155238886'
        && str_contains($request['Body'], '123456')
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('AC123:secret-token')));
});

it('sends SMS from the Twilio number', function (): void {
    Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM2'], 201)]);

    twilioChannel()->send(new OutgoingMessage('+27821234567', 'otp_code', ['code' => '654321'], MessageChannel::Sms));

    Http::assertSent(fn (Request $request): bool => $request['To'] === '+27821234567' && $request['From'] === '+15550001111');
});

it('throws on a Twilio error without echoing the phone number', function (): void {
    Http::fake(['api.twilio.com/*' => Http::response(['code' => 21211, 'message' => 'Invalid To number +27821234567'], 400)]);

    expect(fn () => twilioChannel()->send(new OutgoingMessage('+27821234567', 'otp_code', ['code' => '1'])))
        ->toThrow(fn (RuntimeException $e) => expect($e->getMessage())->toContain('21211')->not->toContain('821234567'));
});

it('uses Twilio on the preview site only when fully configured, and never in tests or local development', function (string $environment, bool $configured, string $expected): void {
    $original = app()->environment();
    app()->detectEnvironment(fn (): string => $environment);
    config()->set('services.twilio', $configured
        ? ['account_sid' => 'AC1', 'auth_token' => 't', 'sms_from' => '+1', 'whatsapp_from' => '+2']
        : ['account_sid' => 'AC1', 'auth_token' => null, 'sms_from' => '+1', 'whatsapp_from' => '+2']);

    try {
        app()->offsetUnset(MessagingChannel::class);
        (new IntegrationServiceProvider(app()))->register();
        expect(app(MessagingChannel::class))->toBeInstanceOf($expected);
    } finally {
        app()->detectEnvironment(fn (): string => $original);
    }
})->with([
    'preview, configured' => ['preview', true, TwilioMessagingChannel::class],
    'preview, missing token' => ['preview', false, FakeMessagingChannel::class],
    'local, configured' => ['local', true, FakeMessagingChannel::class],
    'testing, configured' => ['testing', true, FakeMessagingChannel::class],
    'production, configured' => ['production', true, TwilioMessagingChannel::class],
]);
