<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Geocoder;
use App\Contracts\PaymentGateway;
use App\Contracts\ScopingAssistant;
use App\Integrations\Anthropic\AnthropicScopingAssistant;
use App\Integrations\Fakes\FakeGeocoder;
use App\Integrations\Fakes\FakePaymentGateway;
use App\Integrations\Fakes\FakeScopingAssistant;
use App\Integrations\Google\GooglePlacesGeocoder;
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

        // The test site looks up real addresses once a Places key is set (spec 015).
        if (AppMode::isPreview()) {
            $this->registerPlaces();

            // Siya on Bedrock (decision 043) or Gemini (decision 049); the test site otherwise keeps the fake assistant.
            if ($this->assistantProviderConfigured(includeAnthropic: false)) {
                $this->app->singleton(ScopingAssistant::class, AnthropicScopingAssistant::class);
            }
        }
    }

    /** Real providers bind only when configured, so a missing provider still fails loudly. */
    private function registerRealProviders(): void
    {
        $this->registerPlaces();

        if ($this->assistantProviderConfigured(includeAnthropic: true)) {
            $this->app->singleton(ScopingAssistant::class, AnthropicScopingAssistant::class);
        }
    }

    /** Bedrock needs no key (the server role); Gemini and Anthropic need theirs. */
    private function assistantProviderConfigured(bool $includeAnthropic): bool
    {
        return match (config('getsorted.ai.provider')) {
            'bedrock' => true,
            'gemini' => filled(config('ai.providers.gemini.key')),
            default => $includeAnthropic && filled(config('ai.providers.anthropic.key')),
        };
    }

    private function registerPlaces(): void
    {
        $key = config('services.google_places.key');

        if (is_string($key) && $key !== '') {
            $this->app->singleton(Geocoder::class, fn (): GooglePlacesGeocoder => new GooglePlacesGeocoder($key, (int) config('services.google_places.timeout', 3)));
        }
    }
}
