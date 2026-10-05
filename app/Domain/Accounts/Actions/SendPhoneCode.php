<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Contracts\Data\MessageChannel;
use App\Contracts\Data\OutgoingMessage;
use App\Contracts\MessagingChannel;
use App\Domain\Accounts\Data\SentLoginCodeData;
use App\Domain\Accounts\Enums\OtpPurpose;
use App\Domain\Accounts\Exceptions\CouldNotSendLoginCode;
use App\Domain\Accounts\Exceptions\PhoneAlreadyRegistered;
use App\Domain\Accounts\Support\PhoneNumbers;
use App\Models\PhoneOtp;
use App\Models\User;
use App\Support\AppMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class SendPhoneCode
{
    public function __construct(
        private MessagingChannel $messaging,
    ) {}

    /**
     * Sends a fresh 6-digit code to verify a signed-in user's mobile, replacing
     * any earlier one (spec 014, AC4). Rate limits are applied by the caller.
     *
     * @throws PhoneAlreadyRegistered when another account already verified this number (AC5)
     * @throws CouldNotSendLoginCode
     */
    public function handle(User $user, string $phoneE164, MessageChannel $channel, ?string $ip): SentLoginCodeData
    {
        if (self::isTakenByAnotherAccount($user, $phoneE164)) {
            throw new PhoneAlreadyRegistered('This number is already linked to another account.');
        }

        $sentAt = now();
        $code = $this->generateCode();

        $otp = DB::transaction(function () use ($phoneE164, $channel, $ip, $code): PhoneOtp {
            PhoneOtp::query()
                ->where('phone_e164', $phoneE164)
                ->where('purpose', OtpPurpose::VerifyPhone)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->update(['expires_at' => now()]);

            return PhoneOtp::query()->create([
                'phone_e164' => $phoneE164,
                'code_hash' => self::hash($code),
                'channel' => $channel,
                'purpose' => OtpPurpose::VerifyPhone,
                'expires_at' => now()->addMinutes((int) config('sortd.otp.ttl_minutes')),
                'attempts' => 0,
                'ip' => $ip,
            ]);
        });

        // Sent synchronously after commit: the user is waiting for it, and a
        // failure must be reported on screen rather than retried silently.
        try {
            $this->messaging->send(new OutgoingMessage($phoneE164, 'otp_code', ['code' => $code], $channel));
        } catch (Throwable $exception) {
            $otp->delete();
            // The provider's message carries only its status and error code, never the number (decision 040).
            Log::warning('Verification code could not be sent', ['phone' => PhoneNumbers::maskForLogs($phoneE164), 'channel' => $channel->value, 'reason' => $exception->getMessage()]);

            throw new CouldNotSendLoginCode('Verification code could not be sent.', $exception->getCode(), previous: $exception);
        }

        return new SentLoginCodeData($phoneE164, $channel, $sentAt, AppMode::showsLoginCodes() ? $code : null);
    }

    /** A number is taken once any other account (deleted ones included) holds it. */
    public static function isTakenByAnotherAccount(User $user, string $phoneE164): bool
    {
        return User::withTrashed()->where('phone_e164', $phoneE164)->whereKeyNot($user->getKey())->exists();
    }

    /** HMAC with the app key, so a leaked table cannot be brute-forced offline. */
    public static function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    private function generateCode(): string
    {
        $length = (int) config('sortd.otp.length');

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }
}
