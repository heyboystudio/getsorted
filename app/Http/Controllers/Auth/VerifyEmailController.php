<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Accounts\Actions\SendEmailVerification;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Handles the signed link from the "confirm your email" message (spec 014, AC10). */
final class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, User $user, string $hash): RedirectResponse
    {
        $signedIn = $request->user();

        // Only the account the link was made for, while signed in, with the current address.
        abort_unless($signedIn instanceof User && $signedIn->is($user), 403);
        abort_unless($user->email !== null && hash_equals(SendEmailVerification::hash($user->email), $hash), 403);

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
            activity()->performedOn($user)->causedBy($user)->log('email verified');
        }

        $next = $user->pendingVerificationRoute();

        return $next === null ? redirect()->intended(route($user->homeRoute())) : redirect()->route($next);
    }
}
