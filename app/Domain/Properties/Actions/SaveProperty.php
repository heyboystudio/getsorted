<?php

declare(strict_types=1);

namespace App\Domain\Properties\Actions;

use App\Domain\Properties\Enums\PropertyType;
use App\Domain\Properties\Exceptions\PropertyLimitReached;
use App\Models\Property;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class SaveProperty
{
    /**
     * Creates or updates a customer's property. The address always comes from a Google Places
     * pick, so a new property needs its point (spec 015, 020); an edit may leave the address
     * alone. The audit entry never contains the street address.
     *
     * @throws PropertyLimitReached
     */
    public function handle(
        User $owner,
        ?Property $property,
        string $label,
        ?string $streetAddress,
        ?string $areaLabel,
        ?string $postalCode,
        PropertyType $propertyType,
        ?Point $location = null,
        ?string $googlePlaceId = null,
    ): Property {
        $property instanceof Property
            ? Gate::forUser($owner)->authorize('update', $property)
            : Gate::forUser($owner)->authorize('create', Property::class);

        if (! $property instanceof Property && ($location === null || $streetAddress === null || trim($streetAddress) === '')) {
            throw new InvalidArgumentException('A new property needs an address picked through Places.');
        }

        return DB::transaction(function () use ($owner, $property, $label, $streetAddress, $areaLabel, $postalCode, $propertyType, $location, $googlePlaceId): Property {
            if (! $property instanceof Property) {
                // Locking the owner serialises concurrent creates so the limit holds.
                User::query()->lockForUpdate()->findOrFail($owner->id);

                if ($owner->properties()->count() >= (int) config('sortd.properties.max_per_customer')) {
                    throw new PropertyLimitReached('Property limit reached.');
                }

                $property = new Property;
                $property->user()->associate($owner);
            }

            $isNew = ! $property->exists;
            $property->fill(['label' => $label, 'postal_code' => $postalCode, 'property_type' => $propertyType]);

            if ($location instanceof Point && $streetAddress !== null) {
                $property->fill(['street_address' => $streetAddress]);
                $property->location = $location;
                $property->location_source = 'places';
                $property->google_place_id = $googlePlaceId;
                $property->area_label = $areaLabel;
            }

            $property->save();

            // Never the address or the free-text label: either could hold an address.
            activity()->performedOn($property)->causedBy($owner)
                ->log($isNew ? 'property created' : 'property updated');

            return $property;
        });
    }
}
