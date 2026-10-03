<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class UserPolicy
{
    /** Account permissions are introduced with the approved accounts feature. */
    public function before(User $user, string $ability): bool
    {
        return false;
    }
}
