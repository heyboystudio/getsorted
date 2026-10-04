<?php

declare(strict_types=1);

namespace App\Domain\Properties\Actions;

use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class DeleteProperty
{
    /** Soft-deletes a customer's property; past jobs keep the address. */
    public function handle(User $owner, Property $property): void
    {
        Gate::forUser($owner)->authorize('delete', $property);

        $property->delete();

        activity()->performedOn($property)->causedBy($owner)->log('property deleted');
    }
}
