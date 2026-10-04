<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Pro;
use App\Models\User;

/** Vetting and pro profile access arrive with spec 008. */
final class ProPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Pro $pro): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Pro $pro): bool
    {
        return false;
    }

    public function delete(User $user, Pro $pro): bool
    {
        return false;
    }
}
