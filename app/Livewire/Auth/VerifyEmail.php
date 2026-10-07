<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Domain\Accounts\Actions\SendEmailVerification;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** "Check your email": waits for the link to be clicked and can resend it (spec 014, AC10). */
#[Layout('components.layouts.auth')]
#[Title('Check your email')]
final class VerifyEmail extends Component
{
    public bool $resent = false;

    public function mount(): void
    {
        $next = $this->user()->pendingVerificationRoute();

        if ($next !== 'verification.email') {
            $this->redirectRoute($next ?? $this->user()->homeRoute());
        }
    }

    public function resend(SendEmailVerification $sendEmailVerification): void
    {
        $key = 'verify-email:'.$this->user()->id;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->addError('resend', __('Please wait a few minutes before asking again.'));

            return;
        }

        RateLimiter::hit($key, 600);
        $sendEmailVerification->handle($this->user());
        $this->resent = true;
    }

    /** Lets the page notice a link clicked on another device or tab. */
    public function check(): void
    {
        $next = $this->user()->fresh()?->pendingVerificationRoute();

        if ($next !== 'verification.email') {
            $this->redirectRoute($next ?? $this->user()->homeRoute());
        }
    }

    public function render(): View
    {
        return view('livewire.auth.verify-email', ['email' => $this->user()->email]);
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
