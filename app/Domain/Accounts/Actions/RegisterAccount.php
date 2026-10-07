<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Enums\ConsentType;
use App\Domain\Accounts\Enums\Role;
use App\Domain\Accounts\Exceptions\EmailAlreadyRegistered;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

final readonly class RegisterAccount
{
    public function __construct(
        private RecordConsent $recordConsent,
    ) {}

    /**
     * Creates a customer or pro account with an email and either a password or
     * a Google ID, and records their POPIA consents (type, document version,
     * time, IP, browser). Pros also accept the pro agreement (spec 011). The
     * mobile number is verified afterwards (spec 014).
     *
     * @throws EmailAlreadyRegistered
     * @throws InvalidArgumentException for roles that cannot sign themselves up, or no credential
     */
    public function handle(
        Role $role,
        string $firstName,
        string $lastName,
        string $email,
        ?string $password,
        ?string $googleId,
        bool $marketing,
        ?string $ip,
        ?string $userAgent,
    ): User {
        if (! in_array($role, [Role::Customer, Role::Pro], true)) {
            throw new InvalidArgumentException('Only customer and pro accounts can be self-registered.');
        }

        if (($password === null) === ($googleId === null)) {
            throw new InvalidArgumentException('An account needs exactly one of a password or a Google ID.');
        }

        $email = mb_strtolower(trim($email));

        if (User::withTrashed()->where('email', $email)->exists()) {
            throw new EmailAlreadyRegistered('This email already has an account.');
        }

        try {
            return DB::transaction(fn (): User => $this->create($role, $firstName, $lastName, $email, $password, $googleId, $marketing, $ip, $userAgent));
        } catch (UniqueConstraintViolationException) {
            // The SQL in this exception contains personal data, so it is not rethrown or logged.
            throw new EmailAlreadyRegistered('This email already has an account.');
        }
    }

    private function create(Role $role, string $firstName, string $lastName, string $email, ?string $password, ?string $googleId, bool $marketing, ?string $ip, ?string $userAgent): User
    {
        $user = new User;
        $user->forceFill([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => $password === null ? null : Hash::make($password),
            'google_id' => $googleId,
            // Google only signs in verified addresses, so those need no link (AC2).
            'email_verified_at' => $googleId === null ? null : now(),
        ])->save();

        $user->assignRole($role->value);

        $consents = [
            ConsentType::Terms->value => (string) config('getsorted.legal.terms_version'),
            ConsentType::Privacy->value => (string) config('getsorted.legal.privacy_version'),
        ];

        if ($role === Role::Pro) {
            $consents[ConsentType::ProAgreement->value] = (string) config('getsorted.legal.pro_agreement_version');
        }

        if ($marketing) {
            // Marketing opt-in follows the privacy notice version it was given under.
            $consents[ConsentType::Marketing->value] = (string) config('getsorted.legal.privacy_version');
        }

        activity()->performedOn($user)->causedBy($user)->withProperties(['method' => $googleId === null ? 'email' : 'google'])->log('account created');

        foreach ($consents as $type => $version) {
            $this->recordConsent->handle($user, ConsentType::from($type), $version, $ip, $userAgent);
        }

        return $user;
    }
}
