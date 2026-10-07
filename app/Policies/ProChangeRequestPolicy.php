<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Pro;
use App\Models\ProChangeRequest;
use App\Models\User;

/**
 * A pro sees their own change requests; vetting and super admins see and decide them, never their
 * own (spec 021, AC27). Creating and deciding happen only through the domain actions.
 */
final class ProChangeRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return ProPolicy::isVetter($user);
    }

    public function view(User $user, ProChangeRequest $change): bool
    {
        $owner = Pro::query()->whereKey($change->pro_id)->value('user_id');

        return $owner === $user->id || (ProPolicy::isVetter($user) && $owner !== $user->id);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ProChangeRequest $change): bool
    {
        return ProPolicy::isVetter($user) && Pro::query()->whereKey($change->pro_id)->value('user_id') !== $user->id;
    }

    public function delete(User $user, ProChangeRequest $change): bool
    {
        return false;
    }
}
