<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Sets a new password from an emailed reset link (spec 014, AC9). */
#[Layout('components.layouts.auth')]
#[Title('Choose a new password')]
final class ResetPassword extends Component
{
    #[Locked]
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    public function save(): void
    {
        $this->email = mb_strtolower(trim($this->email));
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'max:200', PasswordRule::min(10)->uncompromised()],
        ], [
            'password.required' => __('Choose a password.'),
            'password.min' => __('Use at least 10 characters.'),
            'password.uncompromised' => __('This password has appeared in a data leak. Please choose a different one.'),
        ]);

        $status = Password::reset(
            ['email' => $this->email, 'password' => $this->password, 'token' => $this->token],
            function (User $user, string $password): void {
                if ($user->isAdmin()) {
                    return;
                }

                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                activity()->performedOn($user)->causedBy($user)->log('password reset');
            },
        );

        $this->password = '';

        if ($status !== Password::PASSWORD_RESET) {
            $this->addError('email', __('This reset link is invalid or has expired. Please ask for a new one.'));

            return;
        }

        session()->flash('status', __('Your password has been changed. Please sign in.'));
        $this->redirectRoute('login');
    }

    public function render(): View
    {
        return view('livewire.auth.reset-password');
    }
}
