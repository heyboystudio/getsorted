<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ScopingQuestion;
use App\Models\User;

/**
 * Catalogue access (spec 003). Questions can be removed by editors until jobs
 * store answers to them (Phase 2), when this must change to "never".
 */
final class ScopingQuestionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('catalogue.view');
    }

    public function view(User $user, ScopingQuestion $question): bool
    {
        return $user->can('catalogue.view');
    }

    public function create(User $user): bool
    {
        return $user->can('catalogue.edit');
    }

    public function update(User $user, ScopingQuestion $question): bool
    {
        return $user->can('catalogue.edit');
    }

    public function reorder(User $user): bool
    {
        return $user->can('catalogue.edit');
    }

    public function delete(User $user, ScopingQuestion $question): bool
    {
        return $user->can('catalogue.edit');
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
