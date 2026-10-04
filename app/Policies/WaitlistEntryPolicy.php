<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WaitlistEntry;

/** Aggregate demand only; individual records have no viewing screen. */
final class WaitlistEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, WaitlistEntry $entry): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, WaitlistEntry $entry): bool
    {
        return false;
    }

    public function delete(User $user, WaitlistEntry $entry): bool
    {
        return false;
    }
}
