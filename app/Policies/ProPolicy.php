<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Accounts\Enums\Role;
use App\Models\Pro;
use App\Models\User;

/**
 * Pros edit only their own application while it is a draft or changes were
 * requested; only vetting and super admins see or decide applications, never
 * their own (spec 008, AC7).
 */
final class ProPolicy
{
    public function viewAny(User $user): bool
    {
        return self::isVetter($user);
    }

    public function view(User $user, Pro $pro): bool
    {
        return $pro->user_id === $user->id || self::isVetter($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Pro->value);
    }

    public function update(User $user, Pro $pro): bool
    {
        return $pro->user_id === $user->id && $pro->status->isEditable();
    }

    public function vet(User $user, Pro $pro): bool
    {
        return self::isVetter($user) && $pro->user_id !== $user->id;
    }

    public function delete(User $user, Pro $pro): bool
    {
        return false;
    }

    public static function isVetter(User $user): bool
    {
        return $user->hasAnyRole([Role::AdminVetting->value, Role::AdminSuper->value]);
    }
}
