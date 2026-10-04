<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Enums\ConsentType;
use App\Models\Consent;
use App\Models\User;

final readonly class RecordConsent
{
    /** Stores POPIA evidence of a consent: type, document version, time, IP and browser, plus an audit entry. */
    public function handle(User $user, ConsentType $type, string $version, ?string $ip, ?string $userAgent): Consent
    {
        $consent = $user->consents()->make([
            'type' => $type,
            'version' => $version,
            'granted_at' => now(),
            'ip' => $ip,
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 1000),
        ]);
        $consent->save();

        activity()->performedOn($consent)->causedBy($user)
            ->withProperties(['type' => $type->value, 'version' => $version])
            ->log('consent granted');

        return $consent;
    }
}
