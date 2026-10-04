<?php

declare(strict_types=1);

namespace App\Domain\Matching\Actions;

use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Auth\Access\AuthorizationException;

final class WithdrawWaitlist
{
    /** Delete waitlist requests owned by the user's verified phone. */
    public function handle(User $customer): int
    {
        if ($customer->phone_verified_at === null || $customer->phone_e164 === null) {
            throw new AuthorizationException;
        }

        return WaitlistEntry::query()->where('phone_e164', $customer->phone_e164)->delete();
    }
}
