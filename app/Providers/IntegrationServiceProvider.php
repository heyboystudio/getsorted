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
use Illuminate\Support\ServiceProvider;

/**
 * Binds third-party contracts to implementations. Local development and tests
 * always use Fakes. Staging and production bind real providers here once they
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
        if (! $this->app->environment(['local', 'testing'])) {
            $this->registerRealProviders();

            return;
        }

        foreach (self::FAKES as $contract => $fake) {
            $this->app->singleton($contract, $fake);
        }
    }

    /** Real providers bind only when configured, so a missing provider still fails loudly. */
    private function registerRealProviders(): void
    {
        if (filled(config('ai.providers.anthropic.key'))) {
            $this->app->singleton(ScopingAssistant::class, AnthropicScopingAssistant::class);
        }
    }
}
