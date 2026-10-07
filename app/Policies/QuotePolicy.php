<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Accounts\Enums\Role;
use App\Domain\Quotes\Enums\QuoteStatus;
use App\Models\Pro;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\User;

/**
 * Pros change only their own quotes; customers see and accept quotes only on
 * their own jobs; admins view only (spec 010, Security). State checks stay in the Actions.
 */
final class QuotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Quote $quote): bool
    {
        return $user->isAdmin() || $this->quotedBy($user, $quote) || $this->forCustomer($user, $quote);
    }

    /**
     * A pro's profile is visible to a customer only while that pro has a live quote on one of the
     * customer's jobs (spec 021, AC19). The same rule gates the profile photo.
     */
    public function viewPro(User $user, Quote $quote): bool
    {
        return $this->forCustomer($user, $quote)
            && in_array($quote->status, [QuoteStatus::Submitted, QuoteStatus::Accepted], true);
    }

    public function revise(User $user, Quote $quote): bool
    {
        return $this->quotedBy($user, $quote);
    }

    public function withdraw(User $user, Quote $quote): bool
    {
        return $this->quotedBy($user, $quote);
    }

    public function accept(User $user, Quote $quote): bool
    {
        return $this->forCustomer($user, $quote);
    }

    public function update(User $user, Quote $quote): bool
    {
        return false;
    }

    public function delete(User $user, Quote $quote): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function quotedBy(User $user, Quote $quote): bool
    {
        return Pro::query()->whereKey($quote->pro_id)->value('user_id') === $user->id;
    }

    private function forCustomer(User $user, Quote $quote): bool
    {
        return $user->hasRole(Role::Customer->value)
            && ServiceJob::query()->whereKey($quote->service_job_id)->value('customer_id') === $user->id;
    }
}
