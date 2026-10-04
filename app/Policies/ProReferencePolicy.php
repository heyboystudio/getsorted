<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProReference;
use App\Models\User;

/** References are third-party personal data: vetting and super admins only, never for their own application (spec 008). */
final class ProReferencePolicy
{
    public function viewAny(User $user): bool
    {
        return ProPolicy::isVetter($user);
    }

    public function view(User $user, ProReference $reference): bool
    {
        return ProPolicy::isVetter($user) && $reference->pro()->value('user_id') !== $user->id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ProReference $reference): bool
    {
        return false;
    }

    public function delete(User $user, ProReference $reference): bool
    {
        return false;
    }
}
