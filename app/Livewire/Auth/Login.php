<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Domain\Accounts\Support\GoogleSignIn;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Sign in with email + password, or continue with Google (spec 014, AC8).
 * Admins sign in at /admin with their authenticator app, never here.
 */
#[Layout('components.layouts.auth')]
#[Title('Sign in')]
final class Login extends Component
{
    /** Signing in to join as a pro (`/login?as=pro`, spec 011). */
    #[Locked]
    public bool $asPro = false;

    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    #[Locked]
    public bool $linkingGoogle = false;

    public function mount(): void
    {
        $this->asPro = request()->query('as') === 'pro';
        session()->put('auth.as_pro', $this->asPro);
        $link = GoogleSignIn::pendingLink();

        if ($link !== null) {
            $this->linkingGoogle = true;
            $this->email = $link['email'];
        }
    }

    public function login(): void
    {
        if (Auth::check()) {
            $this->redirectRoute('home');

            return;
        }

        $this->resetErrorBag();
        $this->email = mb_strtolower(trim($this->email));
        $this->validate(['email' => ['required', 'string', 'email', 'max:255'], 'password' => ['required', 'string', 'max:200']], [
            'email.required' => __('Enter your email address.'),
            'email.email' => __('Enter a valid email address.'),
            'password.required' => __('Enter your password.'),
        ]);

        $keys = ['login-email:'.hash_hmac('sha256', $this->email, (string) config('app.key')) => [5, 900], 'login-ip:'.request()->ip() => [20, 3600]];

        foreach ($keys as $key => [$max, $decay]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw ValidationException::withMessages(['email' => __('Too many attempts. Try again in :count minutes.', ['count' => (int) ceil(RateLimiter::availableIn($key) / 60)])]);
            }
        }

        $user = User::query()->where('email', $this->email)->first();
        $valid = $user instanceof User && $user->password !== null && ! $user->isAdmin()
            && Hash::check($this->password, $user->password);
        $this->password = '';

        if (! $valid) {
            foreach ($keys as $key => [, $decay]) {
                RateLimiter::hit($key, $decay);
            }

            throw ValidationException::withMessages(['email' => __('That email and password don\'t match an account.')]);
        }

        RateLimiter::clear(array_key_first($keys));
        $this->linkGoogleIfPending($user);
        Auth::login($user, $this->remember);
        session()->regenerate();

        $this->redirectIntended(route($user->homeRoute()));
    }

    public function render(): View
    {
        return view('livewire.auth.login');
    }

    /** Google matched this email before; signing in with the password proves ownership (AC2). */
    private function linkGoogleIfPending(User $user): void
    {
        $link = GoogleSignIn::pendingLink();
        GoogleSignIn::forgetLink();

        if ($link === null || $link['email'] !== $user->email || $user->google_id !== null) {
            return;
        }

        if (User::withTrashed()->where('google_id', $link['id'])->exists()) {
            return;
        }

        $user->forceFill(['google_id' => $link['id'], 'email_verified_at' => $user->email_verified_at ?? now()])->save();
        activity()->performedOn($user)->causedBy($user)->log('google linked');
    }
}
