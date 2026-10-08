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
use App\Integrations\PayFast\PayFastGateway;
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
            $this->registerPayFast();
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
        $this->registerPayFast();
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

    /** The test site uses PayFast's sandbox once keys are set (spec 023); local development and tests keep the fake. */
    private function registerPayFast(): void
    {
        /** @var array{merchant_id?: string|null, merchant_key?: string|null, passphrase?: string|null, sandbox?: bool, check_source_ip?: bool} $payfast */
        $payfast = (array) config('services.payfast', []);

        if (! filled($payfast['merchant_id'] ?? null) || ! filled($payfast['merchant_key'] ?? null)) {
            return;
        }

        $this->app->singleton(PaymentGateway::class, fn (): PayFastGateway => new PayFastGateway(
            (string) $payfast['merchant_id'],
            (string) $payfast['merchant_key'],
            $payfast['passphrase'] ?? null,
            (bool) ($payfast['sandbox'] ?? true),
            route('webhooks.payfast'),
            (bool) ($payfast['check_source_ip'] ?? true),
        ));
    }

    private function registerPlaces(): void
    {
        $key = config('services.google_places.key');

        if (is_string($key) && $key !== '') {
            $this->app->singleton(Geocoder::class, fn (): GooglePlacesGeocoder => new GooglePlacesGeocoder($key, (int) config('services.google_places.timeout', 3)));
        }
    }
}
