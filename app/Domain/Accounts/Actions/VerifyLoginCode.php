<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Enums\OtpPurpose;
use App\Domain\Accounts\Exceptions\LoginCodeRejected;
use App\Models\PhoneOtp;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class VerifyLoginCode
{
    /**
     * Checks a login code: unexpired, under the attempt limit, single use,
     * compared in constant time. Returns the existing customer, or null for a
     * new number. Admin and deleted accounts are rejected like a wrong code (AC18).
     *
     * @throws LoginCodeRejected
     */
    public function handle(string $phoneE164, string $code): ?User
    {
        $maxAttempts = (int) config('sortd.otp.max_attempts');

        // Wrong attempts must be saved, so the outcome is decided inside the
        // transaction and only thrown after it commits.
        $outcome = DB::transaction(function () use ($phoneE164, $code, $maxAttempts): string|int|null {
            $otp = PhoneOtp::query()
                ->where('phone_e164', $phoneE164)
                ->where('purpose', OtpPurpose::Login)
                ->whereNull('consumed_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $otp instanceof PhoneOtp) {
                // A number whose latest code was already used gets "expired", not "incorrect" (AC11).
                $hasUsedCode = PhoneOtp::query()
                    ->where('phone_e164', $phoneE164)
                    ->where('purpose', OtpPurpose::Login)
                    ->whereNotNull('consumed_at')
                    ->exists();

                return $hasUsedCode ? LoginCodeRejected::EXPIRED : LoginCodeRejected::INCORRECT;
            }

            if ($otp->expires_at->isPast() || $otp->attempts >= $maxAttempts) {
                return LoginCodeRejected::EXPIRED;
            }

            if (! hash_equals($otp->code_hash, SendLoginCode::hash($code))) {
                $otp->increment('attempts');

                return max(0, $maxAttempts - $otp->attempts);
            }

            $otp->update(['consumed_at' => now()]);

            return null;
        });

        if (is_int($outcome)) {
            throw new LoginCodeRejected(LoginCodeRejected::INCORRECT, $outcome);
        }

        if (is_string($outcome)) {
            throw new LoginCodeRejected($outcome);
        }

        // Blocked numbers only ever hold unsent codes, so this is defence in depth.
        if (SendLoginCode::isBlockedFromPhoneLogin($phoneE164)) {
            throw new LoginCodeRejected(LoginCodeRejected::INCORRECT);
        }

        $user = User::query()->where('phone_e164', $phoneE164)->first();

        if ($user instanceof User && $user->phone_verified_at === null) {
            $user->forceFill(['phone_verified_at' => now()])->save();
        }

        return $user;
    }
}
