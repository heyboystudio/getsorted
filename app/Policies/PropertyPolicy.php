<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Accounts\Enums\Role;
use App\Models\Property;
use App\Models\User;

/** Only the customer who owns a property can see or change it (spec 004). Admins have no access yet. */
final class PropertyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Customer->value);
    }

    public function view(User $user, Property $property): bool
    {
        return $this->owns($user, $property);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Customer->value) && $user->phone_verified_at !== null;
    }

    public function update(User $user, Property $property): bool
    {
        return $this->owns($user, $property);
    }

    public function delete(User $user, Property $property): bool
    {
        return $this->owns($user, $property);
    }

    private function owns(User $user, Property $property): bool
    {
        return $user->hasRole(Role::Customer->value) && $property->user_id === $user->id;
    }
}
