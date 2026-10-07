<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Domain\Accounts\Actions\RegisterAccount;
use App\Domain\Accounts\Actions\SendEmailVerification;
use App\Domain\Accounts\Enums\Role;
use App\Domain\Accounts\Exceptions\EmailAlreadyRegistered;
use App\Domain\Accounts\Support\GoogleSignIn;
use App\Domain\Accounts\Support\LoginThrottle;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Create an account with email + password, or finish a Google sign-up by accepting the terms (spec 014, AC1–AC3).
 * Client accounts register at /register and pro accounts at /pros/register: two separate pages and two separate
 * kinds of account (spec 011, founder 2026-10-07). Nobody gets both roles from one sign-up.
 */
#[Layout('components.layouts.auth')]
final class Register extends Component
{
    #[Locked]
    public bool $asPro = false;

    /** Finishing a Google sign-up: the email comes from Google, not this form. */
    #[Locked]
    public bool $withGoogle = false;

    public string $firstName = '';

    public string $lastName = '';

    public string $email = '';

    public string $password = '';

    public bool $acceptTerms = false;

    public bool $acceptPrivacy = false;

    public bool $acceptProAgreement = false;

    public bool $marketing = false;

    public function mount(string $as = ''): mixed
    {
        // Old links used /register?as=pro; pros have their own page now.
        if ($as !== 'pro' && request()->query('as') === 'pro') {
            return $this->redirectRoute('pros.register', array_filter(['with' => request()->query('with')]), navigate: false);
        }

        $this->asPro = $as === 'pro';
        $google = request()->query('with') === 'google' ? GoogleSignIn::pendingSignUp() : null;

        if ($google !== null) {
            $this->withGoogle = true;
            $this->firstName = $google['first_name'];
            $this->lastName = $google['last_name'];
            $this->email = $google['email'];
        }

        return null;
    }

    public function register(RegisterAccount $registerAccount, SendEmailVerification $sendEmailVerification): void
    {
        if (Auth::check()) {
            $this->redirectRoute('home');

            return;
        }

        $this->resetErrorBag();
        $google = $this->withGoogle ? GoogleSignIn::pendingSignUp() : null;

        if ($this->withGoogle && $google === null) {
            throw ValidationException::withMessages(['firstName' => __('Your Google sign-in timed out. Please continue with Google again.')]);
        }

        $waitSeconds = LoginThrottle::attempt([LoginThrottle::registerLimit(request()->ip())]);

        if ($waitSeconds !== null) {
            throw ValidationException::withMessages(['firstName' => __('Too many attempts. Try again in :minutes minutes.', ['minutes' => (int) ceil($waitSeconds / 60)])]);
        }

        $this->email = $google['email'] ?? mb_strtolower(trim($this->email));

        $validated = $this->validate([
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email:strict', 'max:255'],
            'password' => $google === null ? ['required', 'string', 'max:200', Password::min(10)->uncompromised()] : ['nullable'],
            'acceptTerms' => ['accepted'],
            'acceptPrivacy' => ['accepted'],
            'marketing' => ['boolean'],
            'acceptProAgreement' => $this->asPro ? ['accepted'] : ['boolean'],
        ], [
            'firstName.required' => __('Enter your first name.'),
            'lastName.required' => __('Enter your surname.'),
            'email.required' => __('Enter your email address.'),
            'email.email' => __('Enter a valid email address.'),
            'password.required' => __('Choose a password.'),
            'password.min' => __('Use at least 10 characters.'),
            'password.uncompromised' => __('This password has appeared in a data leak. Please choose a different one.'),
            'acceptTerms.accepted' => __('Please accept the terms of service.'),
            'acceptPrivacy.accepted' => __('Please accept the privacy notice.'),
            'acceptProAgreement.accepted' => __('Please accept the pro agreement.'),
        ]);

        try {
            $user = $registerAccount->handle(
                $this->asPro ? Role::Pro : Role::Customer,
                trim($validated['firstName']),
                trim($validated['lastName']),
                $this->email,
                $google === null ? $validated['password'] : null,
                $google['id'] ?? null,
                (bool) $validated['marketing'],
                request()->ip(),
                request()->userAgent(),
            );
        } catch (EmailAlreadyRegistered) {
            throw ValidationException::withMessages(['email' => __('This email already has an account. Sign in instead.')]);
        }

        GoogleSignIn::forgetSignUp();
        $this->password = '';
        Auth::login($user);
        session()->regenerate();

        if ($user->email_verified_at === null) {
            $sendEmailVerification->handle($user);
        }

        // The verification gate sends them through email and mobile checks, then on (AC3, AC6).
        $this->redirectIntended(route($user->homeRoute()));
    }

    public function render(): View
    {
        return view($this->asPro ? 'livewire.pros.register' : 'livewire.auth.register')
            ->title($this->asPro ? __('Join as a pro') : __('Create your client account'));
    }
}
