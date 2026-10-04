<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Pros\Enums\ProStatus;
use App\Models\ServiceJobInvite;
use App\Models\User;

/** An invite is available only to its currently approved pro (spec 009, AC10). */
final class ServiceJobInvitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, ServiceJobInvite $invite): bool
    {
        return $invite->pro()->where('user_id', $user->id)->where('status', ProStatus::Approved)->exists();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ServiceJobInvite $invite): bool
    {
        return false;
    }

    public function delete(User $user, ServiceJobInvite $invite): bool
    {
        return false;
    }
}
