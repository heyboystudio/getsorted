<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Mail\VerifyEmailAddress;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/** Emails a 60-minute signed link that verifies the user's email (spec 014, AC10). */
final class SendEmailVerification
{
    public function handle(User $user): void
    {
        if ($user->email === null || $user->email_verified_at !== null) {
            return;
        }

        Mail::to($user->email)->send(new VerifyEmailAddress($user->first_name, self::link($user)));
    }

    /** The link carries the public ID and a hash of the address, so a changed email invalidates old links. */
    public static function link(User $user): string
    {
        return URL::temporarySignedRoute('verification.email.verify', now()->addMinutes(60), [
            'user' => $user->public_id,
            'hash' => self::hash((string) $user->email),
        ]);
    }

    public static function hash(string $email): string
    {
        return hash_hmac('sha256', mb_strtolower($email), (string) config('app.key'));
    }
}
