<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Enums\ConsentType;
use App\Domain\Accounts\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RegisterCustomer
{
    /**
     * Creates a customer for a phone number verified in this session and records
     * their POPIA consents (type, document version, time, IP, browser).
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
        return DB::transaction(function () use ($phoneE164, $firstName, $lastName, $email, $marketing, $ip, $userAgent): User {
            $user = User::query()->create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone_e164' => $phoneE164,
                'phone_verified_at' => now(),
            ]);

            $user->assignRole(Role::Customer->value);

            $consents = [
                ConsentType::Terms->value => (string) config('sortd.legal.terms_version'),
                ConsentType::Privacy->value => (string) config('sortd.legal.privacy_version'),
            ];

            if ($marketing) {
                // Marketing opt-in follows the privacy notice version it was given under.
                $consents[ConsentType::Marketing->value] = (string) config('sortd.legal.privacy_version');
            }

            foreach ($consents as $type => $version) {
                $user->consents()->create([
                    'type' => $type,
                    'version' => $version,
                    'granted_at' => now(),
                    'ip' => $ip,
                    'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 1000),
                ]);
            }

            activity()->performedOn($user)->causedBy($user)
                ->withProperties(['consents' => array_keys($consents)])
                ->log('account created');

            return $user;
        });
    }
}
