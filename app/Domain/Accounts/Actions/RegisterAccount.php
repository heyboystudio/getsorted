<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Enums\ConsentType;
use App\Domain\Accounts\Enums\Role;
use App\Domain\Accounts\Exceptions\PhoneAlreadyRegistered;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class RegisterAccount
{
    public function __construct(
        private RecordConsent $recordConsent,
    ) {}

    /**
     * Creates a customer or pro account for a phone number verified in this
     * session and records their POPIA consents (type, document version, time,
     * IP, browser). Pros also accept the pro agreement (spec 011).
     *
     * @throws PhoneAlreadyRegistered when the number was registered meanwhile
     * @throws InvalidArgumentException for roles that cannot sign themselves up
     */
    public function handle(
        Role $role,
        string $phoneE164,
        string $firstName,
        string $lastName,
        ?string $email,
        bool $marketing,
        ?string $ip,
        ?string $userAgent,
    ): User {
        if (! in_array($role, [Role::Customer, Role::Pro], true)) {
            throw new InvalidArgumentException('Only customer and pro accounts can be self-registered.');
        }

        try {
            return DB::transaction(fn (): User => $this->create($role, $phoneE164, $firstName, $lastName, $email, $marketing, $ip, $userAgent));
        } catch (UniqueConstraintViolationException) {
            // The SQL in this exception contains personal data, so it is not rethrown or logged.
            throw new PhoneAlreadyRegistered('This number is already registered.');
        }
    }

    private function create(Role $role, string $phoneE164, string $firstName, string $lastName, ?string $email, bool $marketing, ?string $ip, ?string $userAgent): User
    {
        $user = new User;
        $user->forceFill([
            'first_name' => $firstName,
            'last_name' => $lastName,
            // An email already used by another account is dropped rather than
            // rejected, so the form never reveals which emails have accounts.
            'email' => $email !== null && User::withTrashed()->where('email', $email)->exists() ? null : $email,
            'phone_e164' => $phoneE164,
            'phone_verified_at' => now(),
        ])->save();

        $user->assignRole($role->value);

        $consents = [
            ConsentType::Terms->value => (string) config('sortd.legal.terms_version'),
            ConsentType::Privacy->value => (string) config('sortd.legal.privacy_version'),
        ];

        if ($role === Role::Pro) {
            $consents[ConsentType::ProAgreement->value] = (string) config('sortd.legal.pro_agreement_version');
        }

        if ($marketing) {
            // Marketing opt-in follows the privacy notice version it was given under.
            $consents[ConsentType::Marketing->value] = (string) config('sortd.legal.privacy_version');
        }

        activity()->performedOn($user)->causedBy($user)->log('account created');

        foreach ($consents as $type => $version) {
            $this->recordConsent->handle($user, ConsentType::from($type), $version, $ip, $userAgent);
        }

        return $user;
    }
}
