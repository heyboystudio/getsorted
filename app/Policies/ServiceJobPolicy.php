<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Accounts\Enums\Role;
use App\Domain\Quotes\Enums\QuoteStatus;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Models\Pro;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\User;

/** Customers see and change only their own jobs; admins view only (spec 005); contact details only for the booked pro (spec 010). */
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

    /** Manual invites and stopping waves (spec 009, AC11). */
    public function manageMatching(User $user, ServiceJob $job): bool
    {
        return $user->hasAnyRole([Role::AdminSupport->value, Role::AdminSuper->value]);
    }

    /** The customer's name, phone and street address: only the pro whose quote was accepted (spec 010, AC9). */
    public function viewContact(User $user, ServiceJob $job): bool
    {
        if ($job->accepted_quote_id === null) {
            return false;
        }

        $quote = Quote::query()->whereKey($job->accepted_quote_id)->where('status', QuoteStatus::Accepted)->first();

        return $quote instanceof Quote && Pro::query()->whereKey($quote->pro_id)->value('user_id') === $user->id;
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
