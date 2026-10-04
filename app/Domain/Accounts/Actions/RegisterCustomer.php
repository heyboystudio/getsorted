<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Enums\ConsentType;
use App\Domain\Accounts\Enums\Role;
use App\Domain\Accounts\Exceptions\PhoneAlreadyRegistered;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class RegisterCustomer
{
    /**
     * Creates a customer for a phone number verified in this session and records
     * their POPIA consents (type, document version, time, IP, browser).
     *
     * @throws PhoneAlreadyRegistered when the number was registered meanwhile
     */
    public function handle(
        string $phoneE164,
        string $firstName,
        string $lastName,
        ?string $email,
        bool $marketing,
        ?string $ip,
        ?string $userAgent,
    ): User {
        try {
            return DB::transaction(fn (): User => $this->create($phoneE164, $firstName, $lastName, $email, $marketing, $ip, $userAgent));
        } catch (UniqueConstraintViolationException) {
            // The SQL in this exception contains personal data, so it is not rethrown or logged.
            throw new PhoneAlreadyRegistered('This number is already registered.');
        }
    }

    private function create(string $phoneE164, string $firstName, string $lastName, ?string $email, bool $marketing, ?string $ip, ?string $userAgent): User
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

        $user->assignRole(Role::Customer->value);

        $consents = [
            ConsentType::Terms->value => (string) config('sortd.legal.terms_version'),
            ConsentType::Privacy->value => (string) config('sortd.legal.privacy_version'),
        ];

        if ($marketing) {
            // Marketing opt-in follows the privacy notice version it was given under.
            $consents[ConsentType::Marketing->value] = (string) config('sortd.legal.privacy_version');
        }

        activity()->performedOn($user)->causedBy($user)->log('account created');

        foreach ($consents as $type => $version) {
            $consent = $user->consents()->make([
                'type' => $type,
                'version' => $version,
                'granted_at' => now(),
                'ip' => $ip,
                'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 1000),
            ]);
            $consent->save();

            activity()->performedOn($consent)->causedBy($user)
                ->withProperties(['type' => $type, 'version' => $version])
                ->log('consent granted');
        }

        return $user;
    }
}
