<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Contracts\Data\MessageChannel;
use App\Contracts\Data\OutgoingMessage;
use App\Contracts\MessagingChannel;
use App\Domain\Accounts\Data\SentLoginCodeData;
use App\Domain\Accounts\Enums\OtpPurpose;
use App\Domain\Accounts\Exceptions\CouldNotSendLoginCode;
use App\Domain\Accounts\Support\PhoneNumbers;
use App\Models\PhoneOtp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class SendLoginCode
{
    public function __construct(
        private MessagingChannel $messaging,
    ) {}

    /**
     * Sends a fresh 6-digit login code, replacing any earlier one. Numbers that
     * may not use phone login (admins, deleted accounts) get an identical,
     * stored but unsent code, so neither the response nor later wrong-code
     * messages reveal them (spec 001, AC3 and AC18). Rate limits are applied by the caller.
     *
     * @throws CouldNotSendLoginCode
     */
    public function handle(string $phoneE164, MessageChannel $channel, ?string $ip): SentLoginCodeData
    {
        $sentAt = now();
        $code = $this->generateCode();

        $otp = DB::transaction(function () use ($phoneE164, $channel, $ip, $code): PhoneOtp {
            PhoneOtp::query()
                ->where('phone_e164', $phoneE164)
                ->where('purpose', OtpPurpose::Login)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->update(['expires_at' => now()]);

            return PhoneOtp::query()->create([
                'phone_e164' => $phoneE164,
                'code_hash' => self::hash($code),
                'channel' => $channel,
                'purpose' => OtpPurpose::Login,
                'expires_at' => now()->addMinutes((int) config('sortd.otp.ttl_minutes')),
                'attempts' => 0,
                'ip' => $ip,
            ]);
        });

        if (self::isBlockedFromPhoneLogin($phoneE164)) {
            return new SentLoginCodeData($phoneE164, $channel, $sentAt, null);
        }

        // Sent synchronously after commit: the customer is waiting for it, and a
        // failure must be reported on screen rather than retried silently.
        try {
            $this->messaging->send(new OutgoingMessage($phoneE164, 'otp_code', ['code' => $code], $channel));
        } catch (Throwable $exception) {
            $otp->delete();
            Log::warning('Login code could not be sent', ['phone' => PhoneNumbers::maskForLogs($phoneE164), 'channel' => $channel->value]);

            throw new CouldNotSendLoginCode('Login code could not be sent.', $exception->getCode(), previous: $exception);
        }

        return new SentLoginCodeData($phoneE164, $channel, $sentAt, app()->environment('local') ? $code : null);
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

    /** Admin accounts must use /admin with MFA; deleted accounts may not log in at all. */
    public static function isBlockedFromPhoneLogin(string $phoneE164): bool
    {
        $user = User::withTrashed()->where('phone_e164', $phoneE164)->first();

        return $user instanceof User && ($user->trashed() || $user->isAdmin());
    }
}
