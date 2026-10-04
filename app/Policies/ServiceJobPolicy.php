<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Accounts\Enums\Role;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Models\ServiceJob;
use App\Models\User;

/** Customers see and change only their own jobs; admins view only (spec 005). */
final class ServiceJobPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, ServiceJob $job): bool
    {
        return $user->isAdmin() || $this->owns($user, $job);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Customer->value) && $user->phone_verified_at !== null;
    }

    /** Customers may only change their own drafts; status changes go through Actions. */
    public function update(User $user, ServiceJob $job): bool
    {
        return $this->owns($user, $job) && $job->status === ServiceJobStatus::Draft;
    }

    public function delete(User $user, ServiceJob $job): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function owns(User $user, ServiceJob $job): bool
    {
        return $user->hasRole(Role::Customer->value) && $job->customer_id === $user->id;
    }
}
