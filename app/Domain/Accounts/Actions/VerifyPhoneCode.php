<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Enums\OtpPurpose;
use App\Domain\Accounts\Exceptions\LoginCodeRejected;
use App\Domain\Accounts\Exceptions\PhoneAlreadyRegistered;
use App\Models\PhoneOtp;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class VerifyPhoneCode
{
    /**
     * Checks a phone verification code: unexpired, under the attempt limit,
     * single use, compared in constant time. On success the number becomes the
     * user's verified mobile (spec 014, AC5, AC7); the old number stays until then.
     *
     * @throws LoginCodeRejected
     * @throws PhoneAlreadyRegistered when another account took the number meanwhile
     */
    public function handle(User $user, string $phoneE164, string $code): void
    {
        $maxAttempts = (int) config('getsorted.otp.max_attempts');

        // Wrong attempts must be saved, so the outcome is decided inside the
        // transaction and only thrown after it commits.
        $outcome = DB::transaction(function () use ($phoneE164, $code, $maxAttempts): string|int|null {
            $otp = PhoneOtp::query()
                ->where('phone_e164', $phoneE164)
                ->where('purpose', OtpPurpose::VerifyPhone)
                ->whereNull('consumed_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $otp instanceof PhoneOtp) {
                // A number whose latest code was already used gets "expired", not "incorrect" (AC11).
                $hasUsedCode = PhoneOtp::query()
                    ->where('phone_e164', $phoneE164)
                    ->where('purpose', OtpPurpose::VerifyPhone)
                    ->whereNotNull('consumed_at')
                    ->exists();

                return $hasUsedCode ? LoginCodeRejected::EXPIRED : LoginCodeRejected::INCORRECT;
            }

            if ($otp->expires_at->isPast() || $otp->attempts >= $maxAttempts) {
                return LoginCodeRejected::EXPIRED;
            }

            if (! hash_equals($otp->code_hash, SendPhoneCode::hash($code))) {
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

        if (SendPhoneCode::isTakenByAnotherAccount($user, $phoneE164)) {
            throw new PhoneAlreadyRegistered('This number is already linked to another account.');
        }

        try {
            $user->forceFill(['phone_e164' => $phoneE164, 'phone_verified_at' => now()])->save();
        } catch (UniqueConstraintViolationException) {
            // The SQL in this exception contains personal data, so it is not rethrown or logged.
            throw new PhoneAlreadyRegistered('This number is already linked to another account.');
        }

        activity()->performedOn($user)->causedBy($user)->log('phone verified');
    }
}
