<?php

declare(strict_types=1);

namespace App\Domain\Properties\Actions;

use App\Domain\Properties\Enums\PropertyType;
use App\Domain\Properties\Exceptions\PropertyLimitReached;
use App\Models\Property;
use App\Models\Suburb;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class SaveProperty
{
    /**
     * Creates or updates a customer's property. The location is the picked
     * address's point when the customer chose a Places suggestion (spec 015),
     * otherwise the suburb's centre point (spec 004, decision 2). The audit
     * entry never contains the street address.
     *
     * @throws PropertyLimitReached
     */
    public function handle(
        User $owner,
        ?Property $property,
        string $label,
        string $streetAddress,
        Suburb $suburb,
        ?string $postalCode,
        PropertyType $propertyType,
        ?Point $location = null,
        ?string $googlePlaceId = null,
    ): Property {
        $property instanceof Property
            ? Gate::forUser($owner)->authorize('update', $property)
            : Gate::forUser($owner)->authorize('create', Property::class);

        return DB::transaction(function () use ($owner, $property, $label, $streetAddress, $suburb, $postalCode, $propertyType, $location, $googlePlaceId): Property {
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
            $property->fill([
                'label' => $label,
                'street_address' => $streetAddress,
                'postal_code' => $postalCode,
                'property_type' => $propertyType,
            ]);
            $property->suburb()->associate($suburb);
            $property->location = $location ?? $suburb->centroid;
            $property->location_source = $location instanceof Point ? 'places' : 'suburb_centroid';
            $property->google_place_id = $location instanceof Point ? $googlePlaceId : null;
            $property->save();

            activity()->performedOn($property)->causedBy($owner)
                // Only the suburb: free text like the label could contain an address.
                ->withProperties(['suburb' => $suburb->slug])
                ->log($isNew ? 'property created' : 'property updated');

            return $property;
        });
    }
}
