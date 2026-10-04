<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

/** Catalogue access (spec 003): any admin role views; super-admin and support edit; never deleted, only switched off. */
final class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('catalogue.view');
    }

    public function view(User $user, Service $record): bool
    {
        return $user->can('catalogue.view');
    }

    public function create(User $user): bool
    {
        return $user->can('catalogue.edit');
    }

    public function update(User $user, Service $record): bool
    {
        return $user->can('catalogue.edit');
    }

    public function reorder(User $user): bool
    {
        return $user->can('catalogue.edit');
    }

    public function delete(User $user, Service $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
