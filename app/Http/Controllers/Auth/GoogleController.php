<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Accounts\Support\GoogleSignIn;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\BookingStart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * "Continue with Google" (spec 014, AC2, AC8). Signs in a linked account, asks a
 * password account with the same email to sign in once to link, or starts a sign-up.
 */
final class GoogleController extends Controller
{
    public function redirect(Request $request): SymfonyRedirect
    {
        $request->session()->put('auth.as_pro', $request->query('as') === 'pro');
        // "register" means the visitor pressed Google on a sign-up page: it may never sign an existing account in.
        $request->session()->put('auth.google_intent', $request->query('intent') === 'register' ? 'register' : 'login');

        // Socialite's Google driver asks only for openid, profile and email.
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            /** @var GoogleUser $google */
            $google = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect()->route('login')->with('status', __('Google sign-in didn\'t complete. Please try again.'));
        }

        $email = mb_strtolower((string) $google->getEmail());
        $verified = ($google->user['email_verified'] ?? false) === true || ($google->user['verified_email'] ?? false) === true;

        if ($email === '' || ! $verified) {
            return redirect()->route('login')->with('status', __('Your Google account needs a verified email address.'));
        }

        $asPro = $request->session()->get('auth.as_pro') === true;
        $registering = $request->session()->pull('auth.google_intent') === 'register';
        $linked = User::query()->where('google_id', (string) $google->getId())->first();

        if ($registering && ($linked instanceof User || User::withTrashed()->where('email', $email)->exists())) {
            return redirect()->route('login', $asPro ? ['as' => 'pro'] : [])
                ->with('status', __('You already have an account with this email. Sign in instead.'));
        }

        if ($linked instanceof User) {
            if ($linked->isAdmin()) {
                return redirect()->route('login')->with('status', __('Admins sign in at /admin.'));
            }

            Auth::login($linked);
            $request->session()->regenerate();

            return redirect()->intended(BookingStart::landing($linked));
        }

        $existing = User::withTrashed()->where('email', $email)->first();

        if ($existing instanceof User) {
            if ($existing->trashed() || $existing->isAdmin()) {
                return redirect()->route('login')->with('status', __('Please sign in with your email and password.'));
            }

            // Never take over an account silently: the password proves ownership first.
            GoogleSignIn::rememberLink((string) $google->getId(), $email);

            return redirect()->route('login', $asPro ? ['as' => 'pro'] : [])
                ->with('status', __('You already have an account with this email. Sign in with your password once to connect Google.'));
        }

        GoogleSignIn::rememberSignUp([
            'id' => (string) $google->getId(),
            'email' => $email,
            'first_name' => (string) ($google->user['given_name'] ?? ''),
            'last_name' => (string) ($google->user['family_name'] ?? ''),
        ]);

        return redirect()->route('register', array_filter(['with' => 'google', 'as' => $asPro ? 'pro' : null]));
    }
}
