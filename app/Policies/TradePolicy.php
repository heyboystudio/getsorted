<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Trade;
use App\Models\User;

/** Catalogue access (spec 003): any admin role views; super-admin and support edit; never deleted, only switched off. */
final class TradePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('catalogue.view');
    }

    public function view(User $user, Trade $record): bool
    {
        return $user->can('catalogue.view');
    }

    public function create(User $user): bool
    {
        return $user->can('catalogue.edit');
    }

    public function update(User $user, Trade $record): bool
    {
        return $user->can('catalogue.edit');
    }

    public function reorder(User $user): bool
    {
        return $user->can('catalogue.edit');
    }

    public function delete(User $user, Trade $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
