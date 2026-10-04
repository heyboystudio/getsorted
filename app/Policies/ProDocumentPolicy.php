<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProDocument;
use App\Models\User;

/** Documents are seen by their pro and by vetting/super admins only (spec 008, AC8). */
final class ProDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return ProPolicy::isVetter($user);
    }

    public function view(User $user, ProDocument $document): bool
    {
        return $document->pro()->value('user_id') === $user->id || ProPolicy::isVetter($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ProDocument $document): bool
    {
        return false;
    }

    public function delete(User $user, ProDocument $document): bool
    {
        return false;
    }
}
