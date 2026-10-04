<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Geocoder;
use App\Contracts\MessagingChannel;
use App\Contracts\PaymentGateway;
use App\Contracts\ScopingAssistant;
use App\Integrations\Anthropic\AnthropicScopingAssistant;
use App\Integrations\Fakes\FakeGeocoder;
use App\Integrations\Fakes\FakeMessagingChannel;
use App\Integrations\Fakes\FakePaymentGateway;
use App\Integrations\Fakes\FakeScopingAssistant;
use App\Integrations\Twilio\TwilioMessagingChannel;
use App\Support\AppMode;
use Illuminate\Support\ServiceProvider;

/**
 * Binds third-party contracts to implementations. Local development, tests and
 * the private preview site (decision 037) always use Fakes. Staging and production bind real providers here once they
 * are chosen and configured; until then resolving a contract there fails loudly
 * rather than silently using a fake.
 */
final class IntegrationServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    private const array FAKES = [
        PaymentGateway::class => FakePaymentGateway::class,
        MessagingChannel::class => FakeMessagingChannel::class,
        ScopingAssistant::class => FakeScopingAssistant::class,
        Geocoder::class => FakeGeocoder::class,
    ];

    public function register(): void
    {
        if (! AppMode::usesFakeIntegrations()) {
            $this->registerRealProviders();

            return;
        }

        foreach (self::FAKES as $contract => $fake) {
            $this->app->singleton($contract, $fake);
        }

        // The private test site sends real WhatsApp and SMS once Twilio is configured (decision 040).
        if (AppMode::isPreview()) {
            $this->registerTwilio();
        }
    }

    /** Real providers bind only when configured, so a missing provider still fails loudly. */
    private function registerRealProviders(): void
    {
        $this->registerTwilio();

        if (filled(config('ai.providers.anthropic.key'))) {
            $this->app->singleton(ScopingAssistant::class, AnthropicScopingAssistant::class);
        }
    }

    private function registerTwilio(): void
    {
        /** @var array{account_sid?: string|null, auth_token?: string|null, sms_from?: string|null, whatsapp_from?: string|null} $twilio */
        $twilio = (array) config('services.twilio', []);

        if (! filled($twilio['account_sid'] ?? null) || ! filled($twilio['auth_token'] ?? null) || ! filled($twilio['sms_from'] ?? null) || ! filled($twilio['whatsapp_from'] ?? null)) {
            return;
        }

        $this->app->singleton(MessagingChannel::class, fn (): TwilioMessagingChannel => new TwilioMessagingChannel(
            (string) $twilio['account_sid'], (string) $twilio['auth_token'], (string) $twilio['sms_from'], (string) $twilio['whatsapp_from'],
        ));
    }
}
