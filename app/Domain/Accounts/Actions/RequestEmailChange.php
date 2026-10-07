<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Exceptions\EmailAlreadyRegistered;
use App\Mail\VerifyEmailAddress;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Starts an email change (spec 021, AC14). The new address only waits in `pending_email`; it
 * replaces the old one when its owner opens the emailed link, so a typo can never lock
 * someone out or hand their account to a stranger.
 */
final class RequestEmailChange
{
    /** @throws EmailAlreadyRegistered */
    public function handle(User $user, string $newEmail): void
    {
        $email = mb_strtolower(trim($newEmail));

        if ($email === mb_strtolower((string) $user->email)) {
            return;
        }

        $taken = User::query()->withTrashed()->whereKeyNot($user->id)
            ->where(fn ($query) => $query->whereRaw('lower(email) = ?', [$email])->orWhereRaw('lower(pending_email) = ?', [$email]))
            ->exists();

        if ($taken) {
            throw new EmailAlreadyRegistered;
        }

        $user->forceFill(['pending_email' => $email])->save();
        activity()->performedOn($user)->causedBy($user)->log('email change requested');

        Mail::to($email)->send(new VerifyEmailAddress($user->first_name, self::link($user, $email)));
    }

    /** Valid for an hour, and only for the address it was made for. */
    public static function link(User $user, string $email): string
    {
        return URL::temporarySignedRoute('account.email.confirm', now()->addMinutes(60), [
            'user' => $user->public_id,
            'hash' => SendEmailVerification::hash($email),
        ]);
    }
}
