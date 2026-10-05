<?php

declare(strict_types=1);

namespace App\Integrations\Google;

use App\Contracts\Data\AddressSuggestion;
use App\Contracts\Data\GeocodedAddress;
use App\Contracts\Exceptions\GeocoderUnavailable;
use App\Contracts\Geocoder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Geocoder on Google Places API (New) (spec 015). Only the typed address text
 * and a session token are sent; the key never reaches the browser.
 */
final readonly class GooglePlacesGeocoder implements Geocoder
{
    private const string BASE = 'https://places.googleapis.com/v1';

    /** Bias towards eThekwini; results stay limited to South Africa. */
    private const float BIAS_LAT = -29.85;

    private const float BIAS_LNG = 31.02;

    private const float BIAS_RADIUS_M = 40000.0;

    private const int MAX_SUGGESTIONS = 5;

    public function __construct(private string $apiKey, private int $timeoutSeconds = 3) {}

    public function autocomplete(string $query, string $sessionToken): array
    {
        $response = $this->send(fn (PendingRequest $http): Response => $http->post(self::BASE.'/places:autocomplete', [
            'input' => $query,
            'sessionToken' => $sessionToken,
            'includedRegionCodes' => ['za'],
            'locationBias' => ['circle' => [
                'center' => ['latitude' => self::BIAS_LAT, 'longitude' => self::BIAS_LNG],
                'radius' => self::BIAS_RADIUS_M,
            ]],
        ]));

        $suggestions = [];

        foreach ((array) $response->json('suggestions', []) as $item) {
            $placeId = data_get($item, 'placePrediction.placeId');
            $text = data_get($item, 'placePrediction.text.text');

            if (is_string($placeId) && $placeId !== '' && is_string($text) && $text !== '') {
                $suggestions[] = new AddressSuggestion($placeId, $text);
            }

            if (count($suggestions) === self::MAX_SUGGESTIONS) {
                break;
            }
        }

        return $suggestions;
    }

    public function resolve(string $placeId, string $sessionToken): ?GeocodedAddress
    {
        if (preg_match('/^[A-Za-z0-9_-]{1,300}$/', $placeId) !== 1) {
            return null;
        }

        $response = $this->send(fn (PendingRequest $http): Response => $http
            ->withHeaders(['X-Goog-FieldMask' => 'formattedAddress,location,addressComponents'])
            ->get(self::BASE.'/places/'.$placeId, ['sessionToken' => $sessionToken]), allowNotFound: true);

        if ($response->status() === 404) {
            return null;
        }

        $lat = $response->json('location.latitude');
        $lng = $response->json('location.longitude');

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        $parts = [];

        foreach ((array) $response->json('addressComponents', []) as $component) {
            $text = data_get($component, 'longText');

            foreach ((array) data_get($component, 'types', []) as $type) {
                if (is_string($text) && is_string($type) && ! isset($parts[$type])) {
                    $parts[$type] = $text;
                }
            }
        }

        $areas = array_values(array_unique(array_filter([
            $parts['sublocality_level_2'] ?? null,
            $parts['sublocality_level_1'] ?? null,
            $parts['sublocality'] ?? null,
            $parts['neighborhood'] ?? null,
            $parts['locality'] ?? null,
        ])));

        $street = trim(($parts['street_number'] ?? '').' '.($parts['route'] ?? ''));

        return new GeocodedAddress(
            formattedAddress: (string) $response->json('formattedAddress', ''),
            suburb: $areas[0] ?? null,
            latitude: (float) $lat,
            longitude: (float) $lng,
            streetLine: $street === '' ? null : $street,
            postalCode: isset($parts['postal_code']) && preg_match('/^\d{4}$/', $parts['postal_code']) === 1 ? $parts['postal_code'] : null,
            areaNames: $areas,
        );
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     *
     * @throws GeocoderUnavailable
     */
    private function send(callable $request, bool $allowNotFound = false): Response
    {
        try {
            $response = $request(Http::withHeaders(['X-Goog-Api-Key' => $this->apiKey])
                ->acceptJson()
                ->timeout($this->timeoutSeconds)
                ->connectTimeout($this->timeoutSeconds));
        } catch (ConnectionException) {
            throw new GeocoderUnavailable('Places request timed out.');
        }

        if ($response->successful() || ($allowNotFound && $response->status() === 404)) {
            return $response;
        }

        // Status only: Google error bodies can echo the request.
        throw new GeocoderUnavailable('Places request failed with HTTP '.$response->status().'.');
    }
}
