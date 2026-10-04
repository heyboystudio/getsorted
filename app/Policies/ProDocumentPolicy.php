<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProDocument;
use App\Models\User;

/** Document access is closed until the vetting workflow in spec 008. */
final class ProDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, ProDocument $document): bool
    {
        return false;
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
