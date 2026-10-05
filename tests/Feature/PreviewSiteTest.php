<?php

declare(strict_types=1);

use App\Contracts\Geocoder;
use App\Contracts\MessagingChannel;
use App\Contracts\PaymentGateway;
use App\Contracts\ScopingAssistant;
use App\Integrations\Fakes\FakeGeocoder;
use App\Integrations\Fakes\FakeMessagingChannel;
use App\Integrations\Fakes\FakePaymentGateway;
use App\Integrations\Fakes\FakeScopingAssistant;
use App\Providers\IntegrationServiceProvider;

/** The private test site (decision 037): fakes there (the banner was removed 2026-10-05), never in staging or production. */
function inEnvironment(string $environment, Closure $check): void
{
    $original = app()->environment();
    app()->detectEnvironment(fn (): string => $environment);

    try {
        $check();
    } finally {
        app()->detectEnvironment(fn (): string => $original);
    }
}

it('uses fake WhatsApp, payments, AI and maps on the preview site', function (): void {
    inEnvironment('preview', function (): void {
        foreach ([MessagingChannel::class, PaymentGateway::class, ScopingAssistant::class, Geocoder::class] as $contract) {
            app()->forgetInstance($contract);
            app()->offsetUnset($contract);
        }
        config()->set('ai.providers.anthropic.key', null);
        (new IntegrationServiceProvider(app()))->register();

        expect(app(MessagingChannel::class))->toBeInstanceOf(FakeMessagingChannel::class)
            ->and(app(PaymentGateway::class))->toBeInstanceOf(FakePaymentGateway::class)
            ->and(app(ScopingAssistant::class))->toBeInstanceOf(FakeScopingAssistant::class)
            ->and(app(Geocoder::class))->toBeInstanceOf(FakeGeocoder::class);
    });
});

it('never fakes providers in staging or production', function (string $environment): void {
    inEnvironment($environment, function (): void {
        app()->offsetUnset(PaymentGateway::class);
        (new IntegrationServiceProvider(app()))->register();

        expect(app()->bound(PaymentGateway::class))->toBeFalse();
    });
})->with(['staging', 'production']);

it('shows no "test site" banner in any environment', function (string $environment): void {
    inEnvironment($environment, function (): void {
        $html = $this->get('/terms')->assertOk()->getContent();

        expect(str_contains((string) $html, 'Test site: fake data only'))->toBeFalse();
    });
})->with(['preview', 'testing', 'production']);
