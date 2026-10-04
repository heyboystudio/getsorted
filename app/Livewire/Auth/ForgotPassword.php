<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Emails a password reset link; the answer never reveals whether the email has an account (spec 014, AC9). */
#[Layout('components.layouts.app')]
#[Title('Reset your password')]
final class ForgotPassword extends Component
{
    public string $email = '';

    public bool $sent = false;

    public function send(): void
    {
        $this->email = mb_strtolower(trim($this->email));
        $this->validate(['email' => ['required', 'string', 'email', 'max:255']], [
            'email.required' => __('Enter your email address.'),
            'email.email' => __('Enter a valid email address.'),
        ]);

        $key = 'password-reset:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', __('Too many requests. Please try again later.'));

            return;
        }

        RateLimiter::hit($key, 3600);
        $user = User::query()->where('email', $this->email)->first();

        // Admins reset through an administrator, never a public link.
        if ($user instanceof User && ! $user->isAdmin()) {
            Password::sendResetLink(['email' => $this->email]);
        }

        $this->sent = true;
    }

    public function render(): View
    {
        return view('livewire.auth.forgot-password');
    }
}
