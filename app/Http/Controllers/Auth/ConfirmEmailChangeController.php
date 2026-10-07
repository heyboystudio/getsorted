<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Accounts\Actions\SendEmailVerification;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The signed link sent to a new email address: it replaces the old one (spec 021, AC14). */
final class ConfirmEmailChangeController extends Controller
{
    public function __invoke(Request $request, User $user, string $hash): RedirectResponse
    {
        $signedIn = $request->user();

        // Only the account the link was made for, while signed in, and only for the address still waiting.
        abort_unless($signedIn instanceof User && $signedIn->is($user), 403);
        abort_unless($user->pending_email !== null && hash_equals(SendEmailVerification::hash($user->pending_email), $hash), 403);

        $taken = User::query()->withTrashed()->whereKeyNot($user->id)->whereRaw('lower(email) = ?', [mb_strtolower($user->pending_email)])->exists();
        abort_if($taken, 409);

        $user->forceFill(['email' => $user->pending_email, 'pending_email' => null, 'email_verified_at' => now()])->save();
        activity()->performedOn($user)->causedBy($user)->log('email changed');

        return redirect()->route('account.profile')->with('email_changed', true);
    }
}
