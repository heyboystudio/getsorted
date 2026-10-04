<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Suburb;
use App\Models\User;

/** Suburbs use the catalogue permissions (spec 004): any admin role views; super-admin and support edit; never deleted, only switched off. */
final class SuburbPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('catalogue.view');
    }

    public function view(User $user, Suburb $record): bool
    {
        return $user->can('catalogue.view');
    }

    public function create(User $user): bool
    {
        return $user->can('catalogue.edit');
    }

    public function update(User $user, Suburb $record): bool
    {
        return $user->can('catalogue.edit');
    }

    public function reorder(User $user): bool
    {
        return $user->can('catalogue.edit');
    }

    public function delete(User $user, Suburb $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
