<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Enums\ConsentType;
use App\Domain\Accounts\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class BecomePro
{
    public function __construct(
        private RecordConsent $recordConsent,
    ) {}

    /**
     * Adds the pro role to an existing phone-verified account that accepted the
     * pro agreement; they keep any customer role (spec 011). Admins cannot be pros.
     *
     * @throws AuthorizationException
     */
    public function handle(User $user, ?string $ip, ?string $userAgent): User
    {
        if ($user->isAdmin() || $user->phone_verified_at === null) {
            throw new AuthorizationException('This account cannot become a pro.');
        }

        if ($user->hasRole(Role::Pro->value)) {
            return $user;
        }

        return DB::transaction(function () use ($user, $ip, $userAgent): User {
            $user->assignRole(Role::Pro->value);

            $this->recordConsent->handle($user, ConsentType::ProAgreement, (string) config('sortd.legal.pro_agreement_version'), $ip, $userAgent);

            activity()->performedOn($user)->causedBy($user)->log('pro role granted');

            return $user;
        });
    }
}
