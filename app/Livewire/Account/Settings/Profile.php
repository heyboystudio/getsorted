<?php

declare(strict_types=1);

namespace App\Livewire\Account\Settings;

use App\Domain\Accounts\Actions\RequestEmailChange;
use App\Domain\Accounts\Exceptions\EmailAlreadyRegistered;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Name, email and mobile (spec 021, AC14). A new email waits for its emailed link; a new
 * mobile goes through the existing code flow, which keeps the old number until the new one
 * is verified.
 */
#[Layout('components.layouts.panel', ['panel' => 'customer'])]
#[Title('Profile')]
final class Profile extends Component
{
    public string $firstName = '';

    public string $lastName = '';

    public string $newEmail = '';

    public bool $saved = false;

    public bool $emailSent = false;

    public function mount(): void
    {
        $user = $this->user();
        $this->firstName = $user->first_name;
        $this->lastName = $user->last_name;
    }

    public function saveName(): void
    {
        $this->saved = false;
        $this->emailSent = false;

        $validated = $this->validate([
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
        ], attributes: ['firstName' => __('first name'), 'lastName' => __('surname')]);

        $user = $this->user();
        $user->forceFill(['first_name' => trim($validated['firstName']), 'last_name' => trim($validated['lastName'])])->save();
        activity()->performedOn($user)->causedBy($user)->log('name changed');

        $this->saved = true;
    }

    public function changeEmail(RequestEmailChange $requestEmailChange): void
    {
        $this->saved = false;
        $this->emailSent = false;
        $key = 'change-email:'.$this->user()->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['newEmail' => __('Please wait a while before trying again.')]);
        }

        $this->validate(['newEmail' => ['required', 'email:rfc', 'max:190']], attributes: ['newEmail' => __('email')]);
        RateLimiter::hit($key, 3600);

        try {
            $requestEmailChange->handle($this->user(), $this->newEmail);
        } catch (EmailAlreadyRegistered) {
            throw ValidationException::withMessages(['newEmail' => __('That email already belongs to another account.')]);
        }

        $this->newEmail = '';
        $this->emailSent = true;
    }

    public function cancelEmailChange(): void
    {
        $user = $this->user();
        $user->forceFill(['pending_email' => null])->save();
    }

    public function render(): View
    {
        return view('livewire.account.settings.profile', ['user' => $this->user()->refresh(), 'emailChanged' => session('email_changed') === true]);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
