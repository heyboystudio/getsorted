<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Contracts\Data\AddressSuggestion;
use App\Contracts\Data\GeocodedAddress;
use App\Domain\Properties\Queries\MatchSuburbQuery;
use App\Domain\Properties\Support\AddressLookup;
use App\Models\Suburb;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

/**
 * Address search for Livewire forms (spec 015): suggestions while typing, a
 * resolved address and Sortd suburb on pick, and manual entry as the fallback.
 */
trait SearchesAddresses
{
    public string $addressQuery = '';

    /** @var list<array{id: string, text: string}> */
    #[Locked]
    public array $addressSuggestions = [];

    /** True once the provider failed, hit a limit, or the customer chose to type it in. */
    #[Locked]
    public bool $addressManual = false;

    #[Locked]
    public string $placesToken = '';

    #[Locked]
    public ?string $pickedPlaceId = null;

    #[Locked]
    public ?float $pickedLatitude = null;

    #[Locked]
    public ?float $pickedLongitude = null;

    /** Called after a successful pick; $suburb is null when no Sortd suburb matched (AC7). */
    abstract protected function addressPicked(GeocodedAddress $address, ?Suburb $suburb): void;

    public function updatedAddressQuery(): void
    {
        if ($this->addressManual) {
            return;
        }

        $this->placesToken = $this->placesToken !== '' ? $this->placesToken : (string) Str::uuid();
        $suggestions = app(AddressLookup::class)->suggest($this->addressQuery, $this->placesToken, $this->addressVisitorKey());

        if ($suggestions === null) {
            $this->enterAddressManually();

            return;
        }

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
            $this->enterAddressManually();

            return;
        }

        $this->pickedPlaceId = $placeId;
        $this->pickedLatitude = $address->latitude;
        $this->pickedLongitude = $address->longitude;
        $this->addressQuery = $address->formattedAddress;
        $this->addressSuggestions = [];
        // A new search after a pick is a new billing session (AC2).
        $this->placesToken = '';

        $this->addressPicked($address, app(MatchSuburbQuery::class)->handle($address));
    }

    public function enterAddressManually(): void
    {
        $this->addressManual = true;
        $this->addressSuggestions = [];
    }

    /** Forget the picked point, e.g. when the customer changes the suburb by hand. */
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
