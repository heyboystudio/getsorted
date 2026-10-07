<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DataRequest;
use App\Models\User;

/** Support and super admins handle download and deletion requests; nobody edits or deletes them (spec 021). */
final class DataRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin_super', 'admin_support']);
    }

    public function view(User $user, DataRequest $request): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, DataRequest $request): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, DataRequest $request): bool
    {
        return false;
    }
}
