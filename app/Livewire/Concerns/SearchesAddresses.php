<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Contracts\Data\AddressSuggestion;
use App\Contracts\Data\GeocodedAddress;
use App\Domain\Properties\Support\AddressLookup;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

/**
 * Address search for Livewire forms (spec 015, 020): suggestions while typing and a
 * resolved, geocoded address on pick. There is no manual entry: matching is by
 * distance, so an address without a Places location is of no use.
 */
trait SearchesAddresses
{
    public string $addressQuery = '';

    /** @var list<array{id: string, text: string}> */
    #[Locked]
    public array $addressSuggestions = [];

    /** True once the provider failed or hit a limit; the form asks the person to try again shortly. */
    #[Locked]
    public bool $addressUnavailable = false;

    #[Locked]
    public string $placesToken = '';

    #[Locked]
    public ?string $pickedPlaceId = null;

    #[Locked]
    public ?float $pickedLatitude = null;

    #[Locked]
    public ?float $pickedLongitude = null;

    /** Called after a successful pick. */
    abstract protected function addressPicked(GeocodedAddress $address): void;

    public function updatedAddressQuery(): void
    {
        $this->placesToken = $this->placesToken !== '' ? $this->placesToken : (string) Str::uuid();
        $suggestions = app(AddressLookup::class)->suggest($this->addressQuery, $this->placesToken, $this->addressVisitorKey());

        if ($suggestions === null) {
            $this->addressUnavailable = true;
            $this->addressSuggestions = [];

            return;
        }

        $this->addressUnavailable = false;

        $this->addressSuggestions = array_map(
            fn (AddressSuggestion $suggestion): array => ['id' => $suggestion->placeId, 'text' => $suggestion->description],
            $suggestions,
        );
    }

    public function pickAddress(string $placeId): void
    {
        // Only a place we just suggested can be picked.
        abort_unless(in_array($placeId, array_column($this->addressSuggestions, 'id'), true), 404);

        $address = app(AddressLookup::class)->resolve($placeId, $this->placesToken, $this->addressVisitorKey());

        if (! $address instanceof GeocodedAddress) {
            $this->addressUnavailable = true;
            $this->addressSuggestions = [];

            return;
        }

        $this->pickedPlaceId = $placeId;
        $this->pickedLatitude = $address->latitude;
        $this->pickedLongitude = $address->longitude;
        $this->addressQuery = $address->formattedAddress;
        $this->addressSuggestions = [];
        // A new search after a pick is a new billing session (AC2).
        $this->placesToken = '';

        $this->addressUnavailable = false;
        $this->addressPicked($address);
    }

    /** Forget the picked point, e.g. when the person edits the address text again. */
    protected function forgetPickedAddress(): void
    {
        $this->pickedPlaceId = null;
        $this->pickedLatitude = null;
        $this->pickedLongitude = null;
    }

    private function addressVisitorKey(): string
    {
        return (string) (auth()->id() ?? request()->ip());
    }
}
